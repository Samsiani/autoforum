<?php
/**
 * Utils — Shared static helper methods used across all AutoForum classes.
 *
 * Centralises logic that was previously duplicated (e.g. get_client_ip)
 * and provides a single place to tighten behaviour in future.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

final class Utils {

    // ── Networking ────────────────────────────────────────────────────────────

    /**
     * Returns the real client IP address.
     *
     * We deliberately do NOT trust X-Forwarded-For without explicit opt-in
     * because that header is trivially spoofed.  If your server sits behind
     * a known proxy (e.g. Nginx, Cloudflare) you can enable the filter below.
     *
     * @return string A validated IPv4/IPv6 address, or '0.0.0.0' on failure.
     */
    public static function get_client_ip(): string {
        /**
         * Filter: af_trust_proxy_headers
         *
         * Set to true only when the application is behind a trusted reverse
         * proxy and X-Forwarded-For / HTTP_CLIENT_IP can be relied upon.
         *
         * @param bool $trust Default false.
         */
        if ( apply_filters( 'af_trust_proxy_headers', false ) ) {
            $candidates = [
                $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',    // Cloudflare.
                $_SERVER['HTTP_X_FORWARDED_FOR']  ?? '',    // Standard proxy header.
                $_SERVER['HTTP_CLIENT_IP']        ?? '',    // Alternative.
            ];

            foreach ( $candidates as $raw ) {
                // X-Forwarded-For can be a comma-separated list; take the first.
                $ip = trim( explode( ',', $raw )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
    }

    // ── Rate Limiting ─────────────────────────────────────────────────────────

    /**
     * Checks if an action is rate-limited for the given key.
     * Returns true if the limit has been reached (caller should abort).
     *
     * @param string $key       Unique transient key (e.g. 'af_login_' + md5(ip)).
     * @param int    $max       Maximum allowed attempts before lockout.
     * @param int    $window    Lockout duration in seconds.
     * @return bool             True = limit reached, False = still allowed.
     */
    public static function is_rate_limited( string $key, int $max, int $window ): bool {
        $attempts = (int) get_transient( $key );
        return $attempts >= $max;
    }

    /**
     * Increments the rate-limit counter for a key.
     * Call this after a failed (not successful) attempt.
     */
    public static function increment_rate_limit( string $key, int $window ): void {
        $attempts = (int) get_transient( $key );
        set_transient( $key, $attempts + 1, $window );
    }

    /**
     * Clears the rate-limit counter for a key (call on success).
     */
    public static function clear_rate_limit( string $key ): void {
        delete_transient( $key );
    }

    // ── Security ──────────────────────────────────────────────────────────────

    /**
     * Builds a rate-limit transient key for a given action + IP.
     */
    public static function rate_limit_key( string $action ): string {
        return 'af_rl_' . $action . '_' . md5( self::get_client_ip() );
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    /**
     * Human-friendly relative time (e.g. "3 hours ago").
     * Wraps human_time_diff() with a consistent format.
     */
    public static function time_ago( string $mysql_utc ): string {
        $ts = strtotime( $mysql_utc );
        if ( ! $ts ) {
            return '';
        }
        /* translators: %s: human-readable time difference */
        return sprintf( __( '%s ago', 'autoforum' ), human_time_diff( $ts, time() ) );
    }

    /**
     * Converts a WordPress role slug to a forum-friendly display label.
     */
    public static function friendly_role( \WP_User $user ): string {
        static $map = [
            'administrator' => 'Admin',
            'editor'        => 'Moderator',
            'author'        => 'Senior Member',
            'contributor'   => 'Member',
            'subscriber'    => 'Member',
        ];
        $role = $user->roles[0] ?? 'subscriber';
        return $map[ $role ] ?? ucfirst( $role );
    }
}
