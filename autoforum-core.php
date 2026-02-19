<?php
/**
 * Plugin Name:       AutoForum & License Manager
 * Plugin URI:        https://esytuner.com/autoforum
 * Description:       High-performance automotive forum with built-in license & HWID management, WooCommerce integration, and "unlock via thanks" content gating.
 * Version:           1.0.4
 * Author:            EsyTuner Dev Team
 * Author URI:        https://esytuner.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       autoforum
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 *
 * @package AutoForum
 */

defined( 'ABSPATH' ) || exit; // Prevent direct file access.

// ──────────────────────────────────────────────────────────────────────────────
// CONSTANTS
// ──────────────────────────────────────────────────────────────────────────────

define( 'AF_VERSION', '1.0.1' );
define( 'AF_PLUGIN_FILE', __FILE__ );
define( 'AF_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'AF_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'AF_DB_VERSION',  '1.2' );

// ──────────────────────────────────────────────────────────────────────────────
// AUTOLOADER
// ──────────────────────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class_name ): void {
    // Only autoload classes in our namespace prefix.
    if ( strpos( $class_name, 'AutoForum\\' ) !== 0 ) {
        return;
    }

    $relative = str_replace( 'AutoForum\\', '', $class_name );
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );

    // Map namespace segments to file naming convention.
    // AutoForum\DB_Installer  →  includes/class-db-installer.php
    $file_name = 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    $file_path = AF_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $file_name;

    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
} );

// ──────────────────────────────────────────────────────────────────────────────
// ACTIVATION / DEACTIVATION / UNINSTALL HOOKS
// ──────────────────────────────────────────────────────────────────────────────

register_activation_hook( AF_PLUGIN_FILE, [ 'AutoForum\\Plugin', 'activate' ] );
register_deactivation_hook( AF_PLUGIN_FILE, [ 'AutoForum\\Plugin', 'deactivate' ] );
// Uninstall is handled by uninstall.php (WordPress best-practice — runs on delete).

// ──────────────────────────────────────────────────────────────────────────────
// BOOTSTRAP
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Returns the singleton plugin instance.
 * All feature classes are wired up here to avoid polluting global scope.
 */
function autoforum(): AutoForum\Plugin {
    return AutoForum\Plugin::instance();
}

// Kick off after all plugins are loaded so WooCommerce hooks are available.
add_action( 'plugins_loaded', 'autoforum' );
