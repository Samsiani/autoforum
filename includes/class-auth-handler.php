<?php
/**
 * Auth Handler — Login, Registration, and AJAX profile actions.
 *
 * All AJAX handlers:
 *  - Verify nonces (CSRF protection).
 *  - Sanitize every input field.
 *  - Escape every output value.
 *  - Rate-limit login attempts via transients.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Auth_Handler {

    private const NONCE_LOGIN          = 'af_login';
    private const NONCE_REGISTER        = 'af_register';
    private const NONCE_PROFILE         = 'af_profile_update';
    private const MAX_ATTEMPTS          = 5;   // Login lockout threshold.
    private const LOCKOUT_SECS          = 900; // 15 minutes.
    private const MAX_REG_ATTEMPTS      = 3;   // Max registrations per IP per hour.
    private const REGISTER_LOCKOUT_SECS = 3600; // 1 hour.

    public function register_hooks(): void {
        add_action( 'wp_ajax_nopriv_af_login',    [ $this, 'ajax_login' ] );
        add_action( 'wp_ajax_nopriv_af_register', [ $this, 'ajax_register' ] );
        add_action( 'wp_ajax_af_logout',           [ $this, 'ajax_logout' ] );
        add_action( 'wp_ajax_af_update_profile',   [ $this, 'ajax_update_profile' ] );
        add_action( 'wp_ajax_af_get_user_data',    [ $this, 'ajax_get_user_data' ] );
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function ajax_login(): void {
        check_ajax_referer( self::NONCE_LOGIN, 'nonce' );

        $lock_key = Utils::rate_limit_key( 'login' );

        // Rate-limit check.
        if ( Utils::is_rate_limited( $lock_key, self::MAX_ATTEMPTS, self::LOCKOUT_SECS ) ) {
            wp_send_json_error( [
                'message' => __( 'Too many login attempts. Please wait 15 minutes.', 'autoforum' ),
                'code'    => 'rate_limited',
            ], 429 );
        }

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $password = $_POST['password'] ?? ''; // Password is not sanitized before auth.

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Username and password are required.', 'autoforum' ) ] );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            Utils::increment_rate_limit( $lock_key, self::LOCKOUT_SECS );
            wp_send_json_error( [
                'message' => __( 'Invalid username or password.', 'autoforum' ),
                'code'    => 'auth_failed',
            ] );
        }

        // Clear rate-limit on success.
        Utils::clear_rate_limit( $lock_key );

        wp_set_current_user( $user->ID, $user->user_login );
        wp_set_auth_cookie( $user->ID, ! empty( $_POST['remember'] ) );

        wp_send_json_success( [
            'message'  => __( 'Logged in successfully.', 'autoforum' ),
            'redirect' => esc_url( apply_filters( 'af_login_redirect', home_url(), $user ) ),
            'user'     => $this->safe_user_data( $user ),
        ] );
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function ajax_register(): void {
        check_ajax_referer( self::NONCE_REGISTER, 'nonce' );

        // Rate-limit registrations per IP to prevent user-spam / DoS.
        $reg_key = Utils::rate_limit_key( 'register' );
        if ( Utils::is_rate_limited( $reg_key, self::MAX_REG_ATTEMPTS, self::REGISTER_LOCKOUT_SECS ) ) {
            wp_send_json_error( [
                'message' => __( 'Too many accounts created from this IP. Please try again in 1 hour.', 'autoforum' ),
                'code'    => 'rate_limited',
            ], 429 );
        }

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = $_POST['password'] ?? '';

        // Validation.
        if ( empty( $username ) ) {
            wp_send_json_error( [ 'message' => __( 'Username is required.', 'autoforum' ) ] );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'A valid email address is required.', 'autoforum' ) ] );
        }
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( [ 'message' => __( 'Password must be at least 8 characters.', 'autoforum' ) ] );
        }
        if ( username_exists( $username ) ) {
            wp_send_json_error( [ 'message' => __( 'That username is already taken.', 'autoforum' ) ] );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'That email address is already registered.', 'autoforum' ) ] );
        }

        // WordPress validates the username format for us.
        if ( ! validate_username( $username ) ) {
            wp_send_json_error( [ 'message' => __( 'Username contains invalid characters.', 'autoforum' ) ] );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
        }

        // Set default role.
        $user = get_user_by( 'id', $user_id );
        $user->set_role( 'subscriber' );

        // Store forum-specific meta.
        update_user_meta( $user_id, 'af_reputation',    0 );
        update_user_meta( $user_id, 'af_post_count',    0 );
        update_user_meta( $user_id, 'af_location',      '' );
        update_user_meta( $user_id, 'af_signature',     '' );
        update_user_meta( $user_id, '_af_last_active',  time() );
        update_user_meta( $user_id, 'af_joined',        current_time( 'mysql', true ) );

        // Auto-login.
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        // Count this successful registration against the IP's hourly allowance.
        Utils::increment_rate_limit( $reg_key, self::REGISTER_LOCKOUT_SECS );

        // Fire WP new user notification (sends admin/user emails).
        wp_new_user_notification( $user_id, null, 'both' );

        do_action( 'af_user_registered', $user_id );

        wp_send_json_success( [
            'message' => __( 'Account created! Welcome to the forum.', 'autoforum' ),
            'user'    => $this->safe_user_data( $user ),
        ] );
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function ajax_logout(): void {
        // wp_logout() validates the nonce internally via the standard referer check.
        check_ajax_referer( 'af_logout', 'nonce' );

        wp_logout();
        wp_send_json_success( [
            'message'  => __( 'Logged out successfully.', 'autoforum' ),
            'redirect' => esc_url( home_url() ),
        ] );
    }

    // ── Profile Update ────────────────────────────────────────────────────────

    public function ajax_update_profile(): void {
        check_ajax_referer( self::NONCE_PROFILE, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Not logged in.', 'autoforum' ) ], 401 );
        }

        $user_id     = get_current_user_id();
        $display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        $bio          = sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) );
        $location     = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
        $signature    = wp_kses(
            wp_unslash( $_POST['signature'] ?? '' ),
            [ 'strong' => [], 'em' => [], 'a' => [ 'href' => true ] ]
        );

        $user_data = [
            'ID'           => $user_id,
            'display_name' => $display_name,
            'description'  => $bio,
        ];

        // Optional email change — requires re-verification in production.
        $new_email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( $new_email && is_email( $new_email ) && $new_email !== wp_get_current_user()->user_email ) {
            if ( email_exists( $new_email ) ) {
                wp_send_json_error( [ 'message' => __( 'That email is already in use.', 'autoforum' ) ] );
            }
            $user_data['user_email'] = $new_email;
        }

        $result = wp_update_user( $user_data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        update_user_meta( $user_id, 'af_location',  $location );
        update_user_meta( $user_id, 'af_signature', $signature );

        wp_send_json_success( [ 'message' => __( 'Profile updated successfully.', 'autoforum' ) ] );
    }

    // ── Get User Data (used by JS on page load) ───────────────────────────────

    public function ajax_get_user_data(): void {
        // Nonce is optional here (read-only, no state change) but added for
        // defence-in-depth as recommended by code review.
        check_ajax_referer( 'af_get_user_data', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_success( [ 'user' => null ] );
        }

        $user = wp_get_current_user();
        wp_send_json_success( [ 'user' => $this->safe_user_data( $user ) ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns a sanitized/escaped array of user data safe to send to the browser.
     * NEVER include password_hash or sensitive meta here.
     */
    public function safe_user_data( \WP_User $user ): array {
        $user_id = $user->ID;

        // Licenses — only active/suspended ones shown on the dashboard.
        global $wpdb;
        $lic_table = \AutoForum\DB_Installer::licenses_table();
        $lic_rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, license_key, status, expires_at FROM {$lic_table}
                 WHERE user_id = %d AND status IN ('active','suspended')
                 ORDER BY created_at DESC",
                $user_id
            )
        );
        $licenses = array_map( fn( $l ) => [
            'id'         => (int) $l->id,
            'key'        => esc_html( $l->license_key ),
            'status'     => esc_attr( $l->status ),
            'expires_at' => $l->expires_at
                ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $l->expires_at ) ) )
                : null,
        ], $lic_rows ?: [] );

        return [
            'id'          => $user_id,
            'username'    => esc_html( $user->user_login ),
            'displayName' => esc_html( $user->display_name ),
            'email'       => esc_html( $user->user_email ),
            'avatar'      => esc_url( get_avatar_url( $user_id, [ 'size' => 150 ] ) ),
            'role'        => esc_html( Utils::friendly_role( $user ) ),
            'reputation'  => (int) get_user_meta( $user_id, 'af_reputation', true ),
            'postCount'   => (int) get_user_meta( $user_id, 'af_post_count', true ),
            'location'    => esc_html( get_user_meta( $user_id, 'af_location', true ) ),
            'signature'   => wp_kses_post( get_user_meta( $user_id, 'af_signature', true ) ),
            'joined'      => esc_html( wp_date( 'M Y', strtotime( $user->user_registered ) ) ),
            'licenses'    => $licenses,
            'nonces'      => [
                'logout'      => wp_create_nonce( 'af_logout' ),
                'profile'     => wp_create_nonce( self::NONCE_PROFILE ),
                'hwid_reset'  => License_Manager::hwid_nonce(),
                'getUserData' => wp_create_nonce( 'af_get_user_data' ),
            ],
        ];
    }

}
// Duplicated get_client_ip() and friendly_role() removed.
// Both now delegate to AutoForum\Utils (class-utils.php).
