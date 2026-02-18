<?php
/**
 * Assets — Enqueues all front-end and admin CSS/JS for AutoForum.
 *
 * Front-end scripts are ONLY loaded on the page that contains the [auto_forum]
 * shortcode, identified either by the `af_forum_page_id` setting or by the
 * presence of the shortcode on the current post.
 *
 * All script/style handles use the `af-` prefix to avoid clashes.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Assets {

    /** Asset version — cache-buster. Tied to plugin version. */
    private const VER = AF_VERSION;

    public function register_hooks(): void {
        add_action( 'wp_enqueue_scripts',    [ $this, 'maybe_enqueue_frontend' ] );
        add_action( 'af_enqueue_forum_assets', [ $this, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_admin' ] );
    }

    // ── Front-end ─────────────────────────────────────────────────────────────

    /**
     * Enqueue on any page that is (or contains) the forum page.
     * Called on `wp_enqueue_scripts`; a second call via the shortcode action
     * is harmless because WP deduplicates handles.
     */
    public function maybe_enqueue_frontend(): void {
        $settings      = get_option( 'af_settings', [] );
        $forum_page_id = absint( $settings['forum_page_id'] ?? 0 );

        // Load if we are on the designated forum page or if the shortcode fired.
        if (
            ( $forum_page_id && is_page( $forum_page_id ) ) ||
            Shortcode::$is_active
        ) {
            $this->enqueue_frontend();
        }
    }

    public function enqueue_frontend(): void {
        static $done = false;
        if ( $done ) {
            return; // Prevent double-enqueue.
        }
        $done = true;

        $base_url = AF_PLUGIN_URL . 'assets/';

        // ── CSS ───────────────────────────────────────────────────────────────

        wp_enqueue_style(
            'af-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null // External resource — no version.
        );

        wp_enqueue_style(
            'af-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            [],
            '6.5.1'
        );

        wp_enqueue_style( 'af-main',       $base_url . 'css/main.css',       [ 'af-fonts', 'af-fontawesome' ], self::VER );
        wp_enqueue_style( 'af-components', $base_url . 'css/components.css', [ 'af-main' ],       self::VER );
        wp_enqueue_style( 'af-forum',      $base_url . 'css/forum.css',      [ 'af-components' ], self::VER );

        // ── JS — load order mirrors the <script> tags in index.html ───────────

        wp_enqueue_script(
            'af-config',
            $base_url . 'js/config.js',
            [],
            self::VER,
            true // Footer.
        );

        wp_enqueue_script( 'af-state',        $base_url . 'js/state.js',                 [ 'af-config' ],       self::VER, true );
        wp_enqueue_script( 'af-api',          $base_url . 'js/api.js',                   [ 'af-state' ],        self::VER, true );
        wp_enqueue_script( 'af-toast',        $base_url . 'js/components/toast.js',      [ 'af-api' ],          self::VER, true );
        wp_enqueue_script( 'af-modal',        $base_url . 'js/components/modal.js',      [ 'af-toast' ],        self::VER, true );
        wp_enqueue_script( 'af-header',       $base_url . 'js/components/header.js',     [ 'af-modal' ],        self::VER, true );
        wp_enqueue_script( 'af-view-home',    $base_url . 'js/views/home.js',            [ 'af-header' ],       self::VER, true );
        wp_enqueue_script( 'af-view-list',    $base_url . 'js/views/thread-list.js',     [ 'af-view-home' ],    self::VER, true );
        wp_enqueue_script( 'af-view-thread',  $base_url . 'js/views/thread-view.js',     [ 'af-view-list' ],    self::VER, true );
        wp_enqueue_script( 'af-view-dash',    $base_url . 'js/views/dashboard.js',       [ 'af-view-thread' ],  self::VER, true );
        wp_enqueue_script( 'af-view-create',   $base_url . 'js/views/create-topic.js',   [ 'af-view-dash' ],    self::VER, true );
        wp_enqueue_script( 'af-view-profile',  $base_url . 'js/views/user-profile.js',   [ 'af-view-create' ], self::VER, true );
        wp_enqueue_script( 'af-router',        $base_url . 'js/router.js',               [ 'af-view-profile' ], self::VER, true );
        wp_enqueue_script( 'af-ticker',        $base_url . 'js/ticker.js',               [ 'af-router' ],       self::VER, true );

        // Main app entry-point — localize data before this handle.
        wp_enqueue_script( 'af-app', $base_url . 'js/app.js', [ 'af-ticker' ], self::VER, true );

        // ── Localized data (replaces JS config.js hard-codes in WP context) ──

        $settings = get_option( 'af_settings', [] );
        $user     = is_user_logged_in()
            ? ( new Auth_Handler() )->safe_user_data( wp_get_current_user() )
            : null;

        // Ticker data — stored separately under 'af_ticker' option.
        $ticker_raw = get_option( 'af_ticker', [] );
        $ticker_defaults = [
            'enabled'        => true,
            'label'          => 'NEWS',
            'speed'          => 40,
            'pause_on_hover' => true,
            'items'          => [],
        ];
        $ticker = wp_parse_args( is_array( $ticker_raw ) ? $ticker_raw : [], $ticker_defaults );

        // Sanitize items for JS output.
        $ticker_items = array_map( function( $item ) {
            return [
                'text' => esc_html( $item['text'] ?? '' ),
                'icon' => sanitize_key( $item['icon'] ?? 'fa-fire' ),
            ];
        }, (array) ( $ticker['items'] ?? [] ) );

        wp_localize_script( 'af-app', 'AF_DATA', [
            'ajaxUrl'     => esc_url( admin_url( 'admin-ajax.php' ) ),
            'restUrl'     => esc_url( rest_url( 'af/v1/' ) ),
            'restNonce'   => wp_create_nonce( 'wp_rest' ),
            'siteUrl'     => esc_url( home_url() ),
            'pluginUrl'   => esc_url( AF_PLUGIN_URL ),
            'currentUser' => $user,
            'ticker'      => [
                'enabled'       => (bool) $ticker['enabled'],
                'label'         => esc_html( $ticker['label'] ),
                'speed'         => absint( $ticker['speed'] ),
                'pauseOnHover'  => (bool) $ticker['pause_on_hover'],
                'items'         => $ticker_items,
            ],
            'nonces'      => [
                'login'          => wp_create_nonce( 'af_login' ),
                'register'       => wp_create_nonce( 'af_register' ),
                'getUserData'    => wp_create_nonce( 'af_get_user_data' ),
                'getCategories'  => wp_create_nonce( 'af_get_categories' ),
                'getTopics'      => wp_create_nonce( 'af_get_topics' ),
                'getPosts'       => wp_create_nonce( 'af_get_posts' ),
                'viewTopic'      => wp_create_nonce( 'af_view_topic' ),
                'getHomeStats'   => wp_create_nonce( 'af_get_home_stats' ),
                'getUserProfile' => wp_create_nonce( 'af_get_user_profile' ),
                'createTopic'    => wp_create_nonce( 'af_create_topic' ),
                'createPost'     => wp_create_nonce( 'af_create_post' ),
                'thankPost'      => wp_create_nonce( 'af_thank_post' ),
                'search'         => wp_create_nonce( 'af_search' ),
                'deleteTopic'    => wp_create_nonce( 'af_delete_topic' ),
                'deletePost'     => wp_create_nonce( 'af_delete_post' ),
                'editPost'       => wp_create_nonce( 'af_edit_post' ),
                'uploadAttachment' => wp_create_nonce( 'af_upload_attachment' ),
                'heartbeat'        => wp_create_nonce( 'af_heartbeat' ),
            ],
            'settings'    => [
                'primaryColor'   => esc_attr( $settings['primary_color']   ?? '#3b82f6' ),
                'threadsPerPage' => absint( $settings['threads_per_page']  ?? 25 ),
                'postsPerPage'   => absint( $settings['posts_per_page']    ?? 20 ),
                // Boolean cast: stored as 0/1 in DB, JS expects true/false.
                'showDemoData'   => ! empty( $settings['show_demo_data'] ),
            ],
            'i18n'        => [
                'loginRequired'   => __( 'Please log in to continue.', 'autoforum' ),
                'topicCreated'    => __( 'Topic created!', 'autoforum' ),
                'replyPosted'     => __( 'Reply posted!', 'autoforum' ),
                'thanked'         => __( 'Thanks added — content unlocked!', 'autoforum' ),
                'errorGeneric'    => __( 'Something went wrong. Please try again.', 'autoforum' ),
            ],
        ] );

        // Inject CSS custom property override for the chosen primary color.
        $primary = sanitize_hex_color( $settings['primary_color'] ?? '#3b82f6' );
        if ( $primary ) {
            $inline = ":root { --primary: {$primary}; }";
            wp_add_inline_style( 'af-main', $inline );
        }
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function enqueue_admin( string $hook ): void {
        // Only load on AutoForum admin pages.
        if ( strpos( $hook, 'autoforum' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'af-fontawesome-admin',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            [],
            null
        );

        // wp-color-picker must be explicitly enqueued (declaring it as a dep is not enough).
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        wp_enqueue_style(
            'af-admin',
            AF_PLUGIN_URL . 'assets/css/admin.css',
            [ 'wp-color-picker', 'af-fontawesome-admin' ],
            self::VER
        );

        wp_enqueue_script(
            'af-admin',
            AF_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery', 'wp-color-picker' ],
            self::VER,
            true
        );

        wp_localize_script( 'af-admin', 'AF_ADMIN', [
            'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
            'i18n'    => [
                'confirmRevoke'     => __( 'Revoke this license? This cannot be undone.', 'autoforum' ),
                'confirmHwidReset'  => __( 'Force-reset the HWID for this license?', 'autoforum' ),
                'success'           => __( 'Action completed successfully.', 'autoforum' ),
                'error'             => __( 'An error occurred. Please try again.', 'autoforum' ),
            ],
            'icons' => [
                // Automotive & Tuning
                'fa-car-side','fa-car','fa-car-burst','fa-truck-fast','fa-motorcycle','fa-gas-pump',
                'fa-gauge','fa-gauge-high','fa-gauge-simple','fa-gauge-simple-high',
                'fa-screwdriver-wrench','fa-screwdriver','fa-wrench','fa-gear','fa-gears',
                'fa-microchip','fa-bolt','fa-bolt-lightning','fa-fire','fa-fire-flame-curved',
                'fa-temperature-high','fa-temperature-low','fa-plug','fa-plug-circle-bolt',
                'fa-oil-can','fa-road','fa-traffic-light',
                // Forum & Community
                'fa-comments','fa-comment','fa-comment-dots','fa-message','fa-messages',
                'fa-reply','fa-share','fa-thumbs-up','fa-heart','fa-star','fa-flag',
                'fa-trophy','fa-medal','fa-crown','fa-certificate','fa-award','fa-ranking-star',
                'fa-users','fa-user','fa-user-gear','fa-user-shield','fa-user-tie',
                'fa-people-group','fa-handshake',
                // Technical & Tools
                'fa-laptop-code','fa-code','fa-terminal','fa-database','fa-server','fa-network-wired',
                'fa-cpu','fa-memory','fa-hard-drive','fa-usb','fa-cable-car',
                'fa-hammer','fa-toolbox','fa-ruler-combined','fa-compass-drafting',
                'fa-magnifying-glass','fa-magnifying-glass-chart','fa-chart-line','fa-chart-bar',
                'fa-chart-pie','fa-chart-simple','fa-wave-square','fa-signal',
                // Content & Navigation
                'fa-book','fa-book-open','fa-bookmark','fa-file','fa-file-code','fa-file-lines',
                'fa-newspaper','fa-layer-group','fa-list','fa-list-ul','fa-list-check',
                'fa-folder','fa-folder-open','fa-tag','fa-tags','fa-paperclip','fa-link',
                'fa-image','fa-images','fa-video','fa-camera','fa-circle-question',
                'fa-circle-info','fa-circle-exclamation','fa-triangle-exclamation',
                'fa-shield','fa-shield-halved','fa-lock','fa-unlock','fa-key',
                'fa-bell','fa-envelope','fa-paper-plane','fa-inbox','fa-upload','fa-download',
                'fa-globe','fa-earth-europe','fa-location-dot','fa-map','fa-compass',
                'fa-clock','fa-calendar','fa-clock-rotate-left','fa-rotate','fa-arrows-rotate',
                'fa-plus','fa-minus','fa-xmark','fa-check','fa-pen','fa-pen-to-square',
                'fa-trash','fa-eye','fa-eye-slash','fa-bars','fa-ellipsis',
            ],
        ] );
    }
}
