<?php
/**
 * License Manager — WooCommerce integration & HWID logic.
 *
 * Responsibilities:
 *  1. Hook into WooCommerce "order completed" to auto-generate licenses.
 *  2. Handle AJAX "Reset HWID" requests from the user dashboard (front-end).
 *  3. Provide query helpers used by the REST API and shortcode.
 *
 * All database writes use $wpdb->prepare() to prevent SQL injection.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class License_Manager {

    private const NONCE_HWID_RESET     = 'af_user_hwid_reset';
    private const DEFAULT_MAX_RESETS   = 3; // Fallback if not set in af_settings.

    private function get_max_hwid_resets(): int {
        $settings = get_option( 'af_settings', Plugin::default_settings() );
        return absint( $settings['max_hwid_resets'] ?? self::DEFAULT_MAX_RESETS );
    }

    public function register_hooks(): void {
        // WooCommerce: generate license when order status flips to "completed".
        add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_completed' ], 10, 1 );

        // WooCommerce: revoke license on refund.
        add_action( 'woocommerce_order_status_refunded',  [ $this, 'on_order_refunded'  ], 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'on_order_refunded'  ], 10, 1 );

        // WooCommerce Subscriptions — only register if the plugin is active.
        if ( class_exists( '\\WC_Subscriptions' ) ) {
            // Renewal completed — keep license active.
            add_action( 'woocommerce_subscription_renewal_payment_complete', [ $this, 'on_subscription_renewed' ], 10, 2 );
            // Failed renewal payment — suspend license.
            add_action( 'woocommerce_subscription_status_on-hold',           [ $this, 'on_subscription_on_hold' ], 10, 1 );
            // Subscription cancelled or expired — revoke license.
            add_action( 'woocommerce_subscription_status_cancelled',          [ $this, 'on_subscription_ended'  ], 10, 1 );
            add_action( 'woocommerce_subscription_status_expired',            [ $this, 'on_subscription_ended'  ], 10, 1 );
        }

        // Front-end AJAX: user resets their own HWID.
        add_action( 'wp_ajax_af_user_reset_hwid', [ $this, 'ajax_user_reset_hwid' ] );

        // REST API: validate license + bind HWID.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    // ── WooCommerce Hooks ─────────────────────────────────────────────────────

    /**
     * Fires when an order transitions to "completed".
     * Iterates line items — if any are in the configured product ID list, issue a license.
     */
    public function on_order_completed( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $settings        = get_option( 'af_settings', Plugin::default_settings() );
        $watchlist       = array_map( 'absint', (array) ( $settings['woo_product_ids'] ?? [] ) );
        $license_days    = absint( $settings['license_duration'] ?? 365 );
        $user_id         = (int) $order->get_user_id();

        // Guest orders cannot receive licenses (no WP user to attach to).
        if ( ! $user_id ) {
            $order->add_order_note( __( 'AutoForum: guest order — no license issued.', 'autoforum' ) );
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = (int) $item->get_product_id();

            if ( ! in_array( $product_id, $watchlist, true ) ) {
                continue;
            }

            // Avoid issuing a second license for the same order + product.
            if ( $this->license_exists_for_order( $order_id, $product_id ) ) {
                continue;
            }

            $key     = $this->generate_license_key();
            $expires = gmdate( 'Y-m-d H:i:s', strtotime( "+{$license_days} days" ) );

            $inserted = $this->insert_license( [
                'user_id'     => $user_id,
                'license_key' => $key,
                'product_id'  => $product_id,
                'order_id'    => $order_id,
                'expires_at'  => $expires,
            ] );

            if ( $inserted ) {
                // Notify the user via WP email (hooks into WC's mailer system).
                do_action( 'af_license_created', $inserted, $user_id, $order_id );

                $order->add_order_note(
                    sprintf(
                        /* translators: 1: license key */
                        __( 'AutoForum: license issued — %s', 'autoforum' ),
                        $key
                    )
                );
            }
        }
    }

    /**
     * Suspend all licenses attached to a refunded / cancelled order.
     */
    public function on_order_refunded( int $order_id ): void {
        global $wpdb;
        $table = DB_Installer::licenses_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'suspended' WHERE order_id = %d AND status = 'active'",
                $order_id
            )
        );

        do_action( 'af_licenses_suspended', $order_id );
    }

    // ── WooCommerce Subscriptions Hooks ───────────────────────────────────────────

    /**
     * Renewal payment succeeded — re-activate any suspended license tied to this subscription.
     *
     * @param \WC_Subscription $subscription  The subscription object.
     * @param \WC_Order        $renewal_order  The renewal order that was just paid.
     */
    public function on_subscription_renewed( $subscription, $renewal_order ): void {
        global $wpdb;
        $parent_order_id = (int) $subscription->get_parent_id();
        if ( ! $parent_order_id ) {
            return;
        }

        $settings     = get_option( 'af_settings', Plugin::default_settings() );
        $license_days = absint( $settings['license_duration'] ?? 365 );
        $new_expiry   = gmdate( 'Y-m-d H:i:s', strtotime( "+{$license_days} days" ) );

        $table  = DB_Installer::licenses_table();
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'active', expires_at = %s WHERE order_id = %d AND status = 'suspended'",
                $new_expiry,
                $parent_order_id
            )
        );

        if ( $result ) {
            $this->flush_license_cache( (int) $subscription->get_user_id() );
            do_action( 'af_licenses_reactivated', $parent_order_id );
        }
    }

    /**
     * Subscription placed on-hold (failed payment) — suspend the license so the
     * software stops working until the customer pays.
     *
     * @param \WC_Subscription $subscription
     */
    public function on_subscription_on_hold( $subscription ): void {
        global $wpdb;
        $parent_order_id = (int) $subscription->get_parent_id();
        if ( ! $parent_order_id ) {
            return;
        }

        $table = DB_Installer::licenses_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'suspended' WHERE order_id = %d AND status = 'active'",
                $parent_order_id
            )
        );

        $this->flush_license_cache( (int) $subscription->get_user_id() );
        do_action( 'af_licenses_suspended', $parent_order_id );
    }

    /**
     * Subscription cancelled or expired — permanently revoke the license.
     *
     * @param \WC_Subscription $subscription
     */
    public function on_subscription_ended( $subscription ): void {
        global $wpdb;
        $parent_order_id = (int) $subscription->get_parent_id();
        if ( ! $parent_order_id ) {
            return;
        }

        $table = DB_Installer::licenses_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'revoked' WHERE order_id = %d AND status IN ('active','suspended')",
                $parent_order_id
            )
        );

        $this->flush_license_cache( (int) $subscription->get_user_id() );
        do_action( 'af_licenses_revoked', $parent_order_id );
    }

    // ── AJAX: User HWID Reset ─────────────────────────────────────────────────

    /**
     * Handles the user's self-service HWID reset from the dashboard.
     * Enforces cooldown (days) + total reset count cap.
     */
    public function ajax_user_reset_hwid(): void {
        check_ajax_referer( self::NONCE_HWID_RESET, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'autoforum' ) ], 401 );
        }

        $user_id      = get_current_user_id();
        // Per-user-per-IP key (existing) prevents single-IP abuse.
        $per_ip_key   = Utils::rate_limit_key( 'hwid_reset_u' . $user_id );
        // Per-user-only key prevents distributed-IP abuse for the same account.
        $per_user_key = 'af_rl_hwid_reset_user_' . $user_id;
        if ( Utils::is_rate_limited( $per_ip_key, 5, HOUR_IN_SECONDS ) ||
             Utils::is_rate_limited( $per_user_key, 5, HOUR_IN_SECONDS ) ) {
            wp_send_json_error( [
                'message' => __( 'Too many HWID reset attempts. Please try again later.', 'autoforum' ),
                'code'    => 'rate_limited',
            ], 429 );
        }
        Utils::increment_rate_limit( $per_ip_key, HOUR_IN_SECONDS );
        Utils::increment_rate_limit( $per_user_key, HOUR_IN_SECONDS );

        $license_id = absint( $_POST['license_id'] ?? 0 );

        if ( ! $license_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid license ID.', 'autoforum' ) ] );
        }

        $license = $this->get_license( $license_id );

        // Ownership check — users can only reset their own licenses.
        if ( ! $license || (int) $license->user_id !== $user_id ) {
            wp_send_json_error( [ 'message' => __( 'License not found.', 'autoforum' ) ], 403 );
        }

        if ( 'active' !== $license->status ) {
            wp_send_json_error( [ 'message' => __( 'License is not active.', 'autoforum' ) ] );
        }

        // Hard cap on lifetime resets.
        if ( (int) $license->resets_count >= $this->get_max_hwid_resets() ) {
            wp_send_json_error( [
                'message' => __( 'Maximum HWID resets reached. Please contact support.', 'autoforum' ),
                'code'    => 'max_resets',
            ] );
        }

        // Cooldown check.
        $settings = get_option( 'af_settings', Plugin::default_settings() );
        $cooldown_days = absint( $settings['hwid_reset_cooldown'] ?? 7 );

        if ( $license->last_reset ) {
            $next_allowed = strtotime( $license->last_reset ) + ( $cooldown_days * DAY_IN_SECONDS );
            if ( time() < $next_allowed ) {
                $hours_left = ceil( ( $next_allowed - time() ) / HOUR_IN_SECONDS );
                wp_send_json_error( [
                    'message'    => sprintf(
                        /* translators: %d hours */
                        __( 'HWID reset on cooldown. Try again in %d hours.', 'autoforum' ),
                        $hours_left
                    ),
                    'code'       => 'cooldown',
                    'hours_left' => $hours_left,
                ] );
            }
        }

        // All checks passed — perform the reset.
        global $wpdb;
        $result = $wpdb->update(
            DB_Installer::licenses_table(),
            [
                'hwid'         => '',
                'resets_count' => (int) $license->resets_count + 1,
                'last_reset'   => current_time( 'mysql', true ),
            ],
            [ 'id' => $license_id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            wp_send_json_error( [ 'message' => __( 'Database error. Please try again.', 'autoforum' ) ] );
        }

        do_action( 'af_user_hwid_reset', $license_id, $user_id );
        $this->flush_license_cache( $user_id );

        wp_send_json_success( [
            'message'        => __( 'HWID cleared. Bind your new device on next launch.', 'autoforum' ),
            'resets_used'    => (int) $license->resets_count + 1,
            'resets_allowed' => $this->get_max_hwid_resets(),
        ] );
    }

    // ── REST API ──────────────────────────────────────────────────────────────

    public function register_rest_routes(): void {
        $settings = get_option( 'af_settings', Plugin::default_settings() );
        if ( empty( $settings['enable_rest_api'] ) ) {
            return;
        }

        // POST /wp-json/af/v1/licenses/validate
        register_rest_route( 'af/v1', '/licenses/validate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_validate_license' ],
            'permission_callback' => '__return_true', // Public — auth is the license key itself.
            'args'                => [
                'key'  => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => is_string( $v ) && strlen( $v ) >= 10,
                ],
                'hwid' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => is_string( $v ) && strlen( $v ) >= 4,
                ],
            ],
        ] );

        // GET /wp-json/af/v1/license-info  (authenticated users only)
        register_rest_route( 'af/v1', '/license-info', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_license_info' ],
            'permission_callback' => 'is_user_logged_in',
        ] );
    }

    /**
     * Validate a license key + HWID.
     * If the HWID slot is empty, bind this HWID.
     * Returns {status, message, expires_at}.
     */
    public function rest_validate_license( \WP_REST_Request $request ): \WP_REST_Response {
        $key  = $request->get_param( 'key' );
        $hwid = $request->get_param( 'hwid' );

        $license = $this->get_license_by_key( $key );

        if ( ! $license ) {
            return new \WP_REST_Response( [
                'status'  => 'invalid',
                'message' => __( 'License key not found.', 'autoforum' ),
            ], 200 );
        }

        if ( 'active' !== $license->status ) {
            return new \WP_REST_Response( [
                'status'  => $license->status, // suspended / expired / revoked
                'message' => sprintf(
                    /* translators: %s: status */
                    __( 'License is %s.', 'autoforum' ),
                    $license->status
                ),
            ], 200 );
        }

        // Check expiry.
        if ( $license->expires_at && strtotime( $license->expires_at ) < time() ) {
            $this->update_license_status( (int) $license->id, 'expired' );
            $this->flush_license_cache( (int) $license->user_id, $key );
            return new \WP_REST_Response( [
                'status'  => 'expired',
                'message' => __( 'License has expired.', 'autoforum' ),
            ], 200 );
        }

        // HWID binding.
        if ( empty( $license->hwid ) ) {
            // First activation — bind the HWID.
            global $wpdb;
            $result = $wpdb->update(
                DB_Installer::licenses_table(),
                [ 'hwid' => $hwid ],
                [ 'id'   => $license->id ],
                [ '%s' ],
                [ '%d' ]
            );
            if ( false === $result ) {
                return new \WP_REST_Response( [
                    'status'  => 'error',
                    'message' => __( 'Failed to bind device. Please try again.', 'autoforum' ),
                ], 500 );
            }
            $this->flush_license_cache( (int) $license->user_id, $key );
        } elseif ( ! hash_equals( $license->hwid, $hwid ) ) {
            // HWID mismatch — different machine.
            return new \WP_REST_Response( [
                'status'  => 'hwid_mismatch',
                'message' => __( 'License is bound to a different device. Reset HWID from your dashboard.', 'autoforum' ),
            ], 200 );
        }

        return new \WP_REST_Response( [
            'status'     => 'valid',
            'message'    => __( 'License is valid.', 'autoforum' ),
            'expires_at' => $license->expires_at,
            'user_id'    => (int) $license->user_id,
        ], 200 );
    }

    /**
     * Return all licenses for the currently logged-in user.
     */
    public function rest_license_info( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id  = get_current_user_id();
        $licenses = $this->get_licenses_for_user( $user_id );

        $settings = get_option( 'af_settings', Plugin::default_settings() );
        $cooldown = absint( $settings['hwid_reset_cooldown'] ?? 7 );

        $data = array_map( function ( $lic ) use ( $cooldown ) {
            $next_reset = null;
            if ( $lic->last_reset ) {
                $ts         = strtotime( $lic->last_reset ) + ( $cooldown * DAY_IN_SECONDS );
                $next_reset = $ts > time() ? date( 'c', $ts ) : null;
            }
            return [
                'id'             => (int) $lic->id,
                'license_key'    => $lic->license_key,
                'hwid'           => $lic->hwid ?: null,
                'status'         => $lic->status,
                'resets_used'    => (int) $lic->resets_count,
                'resets_allowed' => $this->get_max_hwid_resets(),
                'next_reset_at'  => $next_reset,
                'expires_at'     => $lic->expires_at,
            ];
        }, $licenses );

        return new \WP_REST_Response( $data, 200 );
    }

    // ── Public Query Helpers ──────────────────────────────────────────────────

    /**
     * Returns true if the user has at least one active, non-expired license.
     * Used by Forum_API for premium content gating.
     */
    public function user_has_active_license( int $user_id ): bool {
        if ( ! $user_id ) {
            return false;
        }
        global $wpdb;
        $table = DB_Installer::licenses_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from DB_Installer, no user input in schema.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE user_id = %d
                   AND status = 'active'
                   AND (expires_at IS NULL OR expires_at > NOW())
                 LIMIT 1",
                $user_id
            )
        );
    }

    // ── Database Helpers ──────────────────────────────────────────────────────

    /**
     * Insert a new license record.
     *
     * @return int|false  The new license ID, or false on failure.
     */
    public function insert_license( array $data ): int|false {
        global $wpdb;
        $table = DB_Installer::licenses_table();

        $result = $wpdb->insert(
            $table,
            [
                'user_id'     => absint( $data['user_id'] ),
                'license_key' => sanitize_text_field( $data['license_key'] ),
                'product_id'  => absint( $data['product_id'] ?? 0 ),
                'order_id'    => absint( $data['order_id'] ?? 0 ),
                'hwid'        => sanitize_text_field( $data['hwid'] ?? '' ),
                'status'      => 'active',
                'expires_at'  => $data['expires_at'] ?? null,
            ],
            [ '%d', '%s', '%d', '%d', '%s', '%s', '%s' ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    public function get_license( int $id ): ?object {
        global $wpdb;
        $table = DB_Installer::licenses_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null;
    }

    public function get_license_by_key( string $key ): ?object {
        $cache_key = 'af_lic_key_' . md5( $key );
        $cached    = wp_cache_get( $cache_key, 'autoforum' );
        if ( false !== $cached ) {
            return $cached ?: null;
        }
        global $wpdb;
        $table  = DB_Installer::licenses_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE license_key = %s", $key ) ) ?: null;
        // Cache null results too (store false sentinel) to prevent cache stampede.
        wp_cache_set( $cache_key, $result ?? false, 'autoforum', 5 * MINUTE_IN_SECONDS );
        return $result;
    }

    public function get_licenses_for_user( int $user_id ): array {
        $cache_key = 'af_lic_user_' . $user_id;
        $cached    = wp_cache_get( $cache_key, 'autoforum' );
        if ( false !== $cached ) {
            return $cached;
        }
        global $wpdb;
        $table  = DB_Installer::licenses_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ) ) ?: [];
        wp_cache_set( $cache_key, $result, 'autoforum', 5 * MINUTE_IN_SECONDS );
        return $result;
    }

    private function update_license_status( int $id, string $status ): void {
        global $wpdb;
        $wpdb->update(
            DB_Installer::licenses_table(),
            [ 'status' => $status ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    private function license_exists_for_order( int $order_id, int $product_id ): bool {
        global $wpdb;
        $table = DB_Installer::licenses_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE order_id = %d AND product_id = %d LIMIT 1",
                $order_id,
                $product_id
            )
        );
    }

    // ── Key Generation ────────────────────────────────────────────────────────────

    /**
     * Invalidates all cached license data for a user and a specific key.
     * Call this after any write that changes a license record.
     */
    private function flush_license_cache( int $user_id, string $key = '' ): void {
        wp_cache_delete( 'af_lic_user_' . $user_id, 'autoforum' );
        if ( $key ) {
            wp_cache_delete( 'af_lic_key_' . md5( $key ), 'autoforum' );
        }
    }

    /**
     * Format: ESYT-XXXX-XXXX-XXXX-XXXX (groups of 4 uppercase alphanumerics).
     */
    public function generate_license_key(): string {
        $chars  = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // No I/O to avoid confusion.
        $groups = [];
        for ( $g = 0; $g < 4; $g++ ) {
            $segment = '';
            for ( $c = 0; $c < 4; $c++ ) {
                $segment .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
            }
            $groups[] = $segment;
        }
        return 'ESYT-' . implode( '-', $groups );
    }

    /**
     * Public getter for the front-end nonce (used by Assets class).
     */
    public static function hwid_nonce(): string {
        return wp_create_nonce( self::NONCE_HWID_RESET );
    }
}
