<?php
/**
 * Legacy Auth — HMACSHA512 password bridge for migrated EasyTuner users.
 *
 * Migrated users have their WP password set to the locked placeholder
 * "!migrated-needs-reset", so WP's native authenticator always returns
 * a WP_Error for them.  This filter runs at priority 30 (after WP's own
 * wp_authenticate_username_password at priority 20) and:
 *
 *  1. Detects the failure came from a migrated account (legacy meta present).
 *  2. Verifies the plain-text password with HMACSHA512 using the stored key.
 *  3. On success, re-hashes the password with wp_hash_password() (bcrypt),
 *     deletes the legacy meta, and returns the WP_User — transparent to the
 *     caller.
 *  4. On failure, returns the original WP_Error unchanged.
 *
 * After the first successful login the legacy meta is gone, so subsequent
 * logins go through WP's native path at full speed.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Legacy_Auth {

    /** wp_usermeta keys used to store the migrated HMACSHA512 credentials. */
    private const META_HASH = '_af_legacy_hash';
    private const META_KEY  = '_af_legacy_key';

    public function register_hooks(): void {
        // Priority 30 — runs after WP's own authenticators (priority 20).
        add_filter( 'authenticate', [ $this, 'check_legacy_password' ], 30, 3 );
    }

    // ── Filter callback ───────────────────────────────────────────────────────

    /**
     * @param \WP_User|\WP_Error|null $user     Result from earlier filters.
     * @param string                  $username  Login name or email submitted.
     * @param string                  $password  Plain-text password submitted.
     * @return \WP_User|\WP_Error|null
     */
    public function check_legacy_password( $user, string $username, string $password ) {

        // Already authenticated — nothing to do.
        if ( $user instanceof \WP_User ) {
            return $user;
        }

        // Only intercept authentication errors, not null (no username given).
        if ( ! is_wp_error( $user ) ) {
            return $user;
        }

        // Resolve the WP_User object from the submitted identifier.
        $wp_user = get_user_by( 'login', $username )
                ?: get_user_by( 'email', $username );

        if ( ! $wp_user ) {
            return $user; // Unknown user — keep original error.
        }

        $user_id     = $wp_user->ID;
        $legacy_hash = get_user_meta( $user_id, self::META_HASH, true );
        $legacy_key  = get_user_meta( $user_id, self::META_KEY,  true );

        // No legacy credentials stored — this is a normal WP user.
        if ( empty( $legacy_hash ) || empty( $legacy_key ) ) {
            return $user;
        }

        // Verify HMACSHA512( password, key ) === stored hash.
        if ( ! $this->verify( $password, $legacy_hash, $legacy_key ) ) {
            return $user; // Wrong password — keep original error.
        }

        // ── Success path ──────────────────────────────────────────────────────
        // Re-hash with WP native bcrypt so future logins skip this filter.
        wp_set_password( $password, $user_id );
        delete_user_meta( $user_id, self::META_HASH );
        delete_user_meta( $user_id, self::META_KEY );

        // Re-fetch so WP_User reflects the new password hash.
        clean_user_cache( $user_id );
        return get_user_by( 'id', $user_id );
    }

    // ── HMACSHA512 verification ───────────────────────────────────────────────

    /**
     * Mirrors the .NET logic:
     *   using var hmac = new HMACSHA512(Convert.FromHexString(keyHex));
     *   bool ok = hmac.ComputeHash(Encoding.UTF8.GetBytes(password))
     *               .SequenceEqual(Convert.FromHexString(hashHex));
     *
     * @param string $password  Plain-text password.
     * @param string $hash_hex  Stored PasswordHash as a lowercase hex string.
     * @param string $key_hex   Stored PasswordKey  as a lowercase hex string.
     * @return bool
     */
    private function verify( string $password, string $hash_hex, string $key_hex ): bool {
        $key_bytes  = hex2bin( $key_hex );
        $hash_bytes = hex2bin( $hash_hex );

        if ( $key_bytes === false || $hash_bytes === false ) {
            return false; // Corrupt meta — fail safely.
        }

        $computed = hash_hmac( 'sha512', $password, $key_bytes, true );

        return hash_equals( $computed, $hash_bytes );
    }
}
