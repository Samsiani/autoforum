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

// Drop custom tables.
$tables = [
    $wpdb->prefix . 'af_topics',
    $wpdb->prefix . 'af_posts',
    $wpdb->prefix . 'af_licenses',
    $wpdb->prefix . 'af_thanks',
    $wpdb->prefix . 'af_attachments',
    $wpdb->prefix . 'af_categories',
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

// Remove transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_af_%'
        OR option_name LIKE '_transient_timeout_af_%'"
);

// Remove user meta added by the plugin.
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta}
     WHERE meta_key LIKE 'af_%'"
);
