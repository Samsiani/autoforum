<?php
/**
 * Main Plugin orchestrator.
 *
 * Singleton that boots every sub-system and wires all WordPress hooks.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

final class Plugin {

    /** @var Plugin|null */
    private static ?Plugin $instance = null;

    // Sub-system references (lazy-instantiated via getters).
    private DB_Installer    $db;
    private Auth_Handler    $auth;
    private License_Manager $licenses;
    private Admin_Panel     $admin;
    private Forum_API       $api;
    private Shortcode       $shortcode;
    private Assets          $assets;
    // Utils is a static-only class; no instance needed — referenced here for clarity.
    // Utils::get_client_ip(), Utils::friendly_role(), etc. are called by other classes.

    // ── Singleton ────────────────────────────────────────────────────────────

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db       = new DB_Installer();
        $this->auth     = new Auth_Handler();
        $this->licenses = new License_Manager();
        $this->admin    = new Admin_Panel();
        $this->api      = new Forum_API();
        $this->shortcode = new Shortcode();
        $this->assets   = new Assets();

        $this->init_hooks();
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    private function init_hooks(): void {
        // Internationalisation.
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Run DB migrations whenever the schema version is behind.
        $this->db->maybe_upgrade();

        // Each sub-system self-registers its own hooks.
        $this->auth->register_hooks();
        $this->licenses->register_hooks();
        $this->admin->register_hooks();
        $this->api->register_hooks();
        $this->shortcode->register_hooks();
        $this->assets->register_hooks();
    }

    // ── Activation / Deactivation ────────────────────────────────────────────

    /**
     * Called on plugin activation.
     * Runs DB install, flushes rewrite rules, sets default options.
     */
    public static function activate(): void {
        // Must instantiate DB installer directly here (singleton not yet running).
        ( new DB_Installer() )->install();

        // Store the DB schema version so we can run migrations later.
        update_option( 'af_db_version', AF_DB_VERSION );

        // Set sensible defaults only if not already set.
        add_option( 'af_settings', self::default_settings() );

        // We need rewrite rules if the shortcode is on a page.
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'autoforum',
            false,
            dirname( plugin_basename( AF_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * Returns the default settings array stored in wp_options.
     */
    public static function default_settings(): array {
        return [
            'forum_page_id'       => 0,
            'posts_per_page'      => 20,
            'threads_per_page'    => 25,
            'primary_color'       => '#3b82f6',
            'hwid_reset_cooldown' => 7,     // days
            'license_duration'    => 365,   // days
            'woo_product_ids'     => [],    // WooCommerce product IDs that grant licenses
            'enable_rest_api'     => true,
            'show_demo_data'      => false, // When true the SPA displays hardcoded mock data.
        ];
    }

    // Prevent cloning / unserialization of the singleton.
    public function __clone() {}
    public function __wakeup() {}
}
