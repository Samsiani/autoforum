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
    private Legacy_Auth     $legacy_auth;
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
        $this->db          = new DB_Installer();
        $this->auth        = new Auth_Handler();
        $this->legacy_auth = new Legacy_Auth();
        $this->licenses    = new License_Manager();
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

        // GitHub-based automatic updates.
        $this->init_update_checker();

        // Run DB migrations whenever the schema version is behind.
        $this->db->register_hooks();

        // Each sub-system self-registers its own hooks.
        $this->auth->register_hooks();
        $this->legacy_auth->register_hooks();
        $this->licenses->register_hooks();
        $this->admin->register_hooks();
        $this->api->register_hooks();
        $this->shortcode->register_hooks();
        $this->assets->register_hooks();
    }

    // ── Update Checker ───────────────────────────────────────────────────────

    /**
     * Initialises the YahnisElsts Plugin Update Checker (v5) pointing at the
     * Samsiani/autoforum GitHub repository.
     *
     * The checker looks for a .zip asset attached to each GitHub Release.
     * Releases are created automatically by the release.yml GitHub Action
     * whenever a PR is merged to main.
     */
    private function init_update_checker(): void {
        $puc_path = AF_PLUGIN_DIR . 'vendor/plugin-update-checker/load-v5p6.php';
        if ( ! file_exists( $puc_path ) ) {
            return; // Library not present — fail silently.
        }

        require_once $puc_path;

        $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Samsiani/autoforum/',
            AF_PLUGIN_FILE,
            'autoforum'
        );

        // Use the .zip file attached to each GitHub Release rather than the
        // raw source archive so that the vendor/ directory is included.
        $checker->getVcsApi()->enableReleaseAssets();
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
            'max_hwid_resets'     => 3,     // lifetime reset cap per license
            'license_duration'    => 365,   // days
            'woo_product_ids'     => [],    // WooCommerce product IDs that grant licenses
            'enable_rest_api'     => true,
            'show_demo_data'      => false, // When true the SPA displays hardcoded mock data.
        ];
    }

    public function get_license_manager(): License_Manager {
        return $this->licenses;
    }

    // Prevent cloning / unserialization of the singleton.
    public function __clone() {}
    public function __wakeup() {}
}
