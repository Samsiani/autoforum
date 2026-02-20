<?php
/**
 * Uninstall script — runs when the admin clicks "Delete" on the plugin.
 *
 * WARNING: This PERMANENTLY deletes all plugin data.
 * WordPress calls this file directly, not via the plugin bootstrap, so we
 * must verify the uninstall constant ourselves.
 *
 * @package AutoForum
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Honour "keep data" setting — abort early if admin chose to preserve plugin data.
if ( get_option( 'af_keep_data_on_uninstall' ) ) {
    return;
}

// Drop custom tables.
$tables = [
    $wpdb->prefix . 'af_topics',
    $wpdb->prefix . 'af_posts',
    $wpdb->prefix . 'af_licenses',
    $wpdb->prefix . 'af_thanks',
    $wpdb->prefix . 'af_attachments',
    $wpdb->prefix . 'af_categories',
    $wpdb->prefix . 'af_reports',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove all plugin options.
$options = [
    'af_settings',
    'af_db_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients — use esc_like() so literal underscores are not treated as
// LIKE wildcards, then append the real wildcard suffix.
$like_transient = $wpdb->esc_like( '_transient_af_' ) . '%';
$like_timeout   = $wpdb->esc_like( '_transient_timeout_af_' ) . '%';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $like_transient,
        $like_timeout
    )
);

// Remove user meta added by the plugin — explicit key list prevents accidental
// deletion of any third-party plugin data that happens to share the 'af_' prefix.
$meta_keys    = [
    'af_reputation',
    'af_post_count',
    'af_location',
    'af_signature',
    '_af_last_active',
    'af_joined',
    'af_forum_banned',
    '_af_legacy_hash',
    '_af_legacy_key',
];
$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})",
        ...$meta_keys
    )
);
