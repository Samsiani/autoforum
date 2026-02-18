<?php
/**
 * Admin Panel — WordPress backend UI.
 *
 * Registers:
 *  - "AutoForum" top-level menu
 *  - "General Settings" sub-page
 *  - "License Management" sub-page
 *
 * All output is escaped. All form submissions are verified with nonces
 * and capability checks before touching the database.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Admin_Panel {

    private const MENU_SLUG        = 'autoforum';
    private const DASHBOARD_SLUG   = 'autoforum';           // Top-level slug IS the dashboard.
    private const SETTINGS_SLUG    = 'autoforum-settings';
    private const LICENSES_SLUG    = 'autoforum-licenses';
    private const CATEGORIES_SLUG  = 'autoforum-categories';
    private const TICKER_SLUG      = 'autoforum-ticker';
    private const TICKER_OPTION    = 'af_ticker';
    private const TOPICS_SLUG      = 'autoforum-topics';
    private const REPORTS_SLUG     = 'autoforum-reports';
    private const MEMBERS_SLUG     = 'autoforum-members';
    private const ATTACHMENTS_SLUG = 'autoforum-attachments';
    private const OPTION_KEY       = 'af_settings';
    private const NONCE_SETTINGS   = 'af_save_settings';
    private const NONCE_HWID       = 'af_force_hwid_reset';
    private const STATS_TRANSIENT  = 'af_dashboard_stats';   // Cached for 10 min.

    public function register_hooks(): void {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_post_af_save_category',   [ $this, 'handle_save_category' ] );
        add_action( 'admin_post_af_delete_category', [ $this, 'handle_delete_category' ] );
        add_action( 'admin_post_af_save_ticker',      [ $this, 'handle_save_ticker' ] );
        // ── AJAX ─────────────────────────────────────────────────────────────
        add_action( 'wp_ajax_af_force_hwid_reset',    [ $this, 'ajax_force_hwid_reset' ] );
        add_action( 'wp_ajax_af_revoke_license',      [ $this, 'ajax_revoke_license' ] );
        add_action( 'wp_ajax_af_add_license',         [ $this, 'ajax_add_license' ] );
        add_action( 'wp_ajax_af_edit_license',        [ $this, 'ajax_edit_license' ] );
        add_action( 'wp_ajax_af_delete_license',      [ $this, 'ajax_delete_license' ] );
        add_action( 'wp_ajax_af_lic_user_search',     [ $this, 'ajax_lic_user_search' ] );
        add_action( 'wp_ajax_af_resolve_report',      [ $this, 'ajax_resolve_report' ] );
        add_action( 'wp_ajax_af_dismiss_report',      [ $this, 'ajax_dismiss_report' ] );
        add_action( 'wp_ajax_af_delete_report_post',  [ $this, 'ajax_delete_report_post' ] );
        add_action( 'wp_ajax_af_lock_topic',          [ $this, 'ajax_lock_topic' ] );
        add_action( 'wp_ajax_af_pin_topic',           [ $this, 'ajax_pin_topic' ] );
        add_action( 'wp_ajax_af_delete_topic',        [ $this, 'ajax_delete_topic_admin' ] );
        add_action( 'wp_ajax_af_delete_post_admin',   [ $this, 'ajax_delete_post_admin' ] );
        add_action( 'wp_ajax_af_ban_member',          [ $this, 'ajax_ban_member' ] );
        add_action( 'wp_ajax_af_delete_attachment',   [ $this, 'ajax_delete_attachment' ] );
    }

    // ── Menu Registration ─────────────────────────────────────────────────────

    public function register_menus(): void {
        // Top-level entry — points to the new Dashboard page.
        add_menu_page(
            __( 'AutoForum', 'autoforum' ),
            __( 'AutoForum', 'autoforum' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'page_dashboard' ],
            'dashicons-format-chat',
            30
        );

        // ── Submenus (order = display order in sidebar) ───────────────────────

        // 1. Dashboard — explicit entry so the label reads "Dashboard" not "AutoForum".
        add_submenu_page(
            self::MENU_SLUG,
            __( 'AutoForum Dashboard', 'autoforum' ),
            __( 'Dashboard', 'autoforum' ),
            'manage_options',
            self::DASHBOARD_SLUG,
            [ $this, 'page_dashboard' ]
        );

        // 2. Categories — full CRUD for forum categories.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Forum Categories', 'autoforum' ),
            __( 'Categories', 'autoforum' ),
            'manage_options',
            self::CATEGORIES_SLUG,
            [ $this, 'page_categories' ]
        );

        // 3. News Ticker — manage scrolling news items.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'News Ticker', 'autoforum' ),
            __( 'News Ticker', 'autoforum' ),
            'manage_options',
            self::TICKER_SLUG,
            [ $this, 'page_ticker' ]
        );

        // 4. Topics & Posts — moderation queue for threads and replies.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Topics & Posts', 'autoforum' ),
            __( 'Topics & Posts', 'autoforum' ),
            'manage_options',
            self::TOPICS_SLUG,
            [ $this, 'page_topics' ]
        );

        // 4. Reports — user-submitted content reports.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Content Reports', 'autoforum' ),
            __( 'Reports', 'autoforum' ),
            'manage_options',
            self::REPORTS_SLUG,
            [ $this, 'page_reports' ]
        );

        // 5. Members — forum-specific user management.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Forum Members', 'autoforum' ),
            __( 'Members', 'autoforum' ),
            'manage_options',
            self::MEMBERS_SLUG,
            [ $this, 'page_members' ]
        );

        // 6. Attachments — uploaded file management.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Forum Attachments', 'autoforum' ),
            __( 'Attachments', 'autoforum' ),
            'manage_options',
            self::ATTACHMENTS_SLUG,
            [ $this, 'page_attachments' ]
        );

        // 7. Licenses — already implemented.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'License Management', 'autoforum' ),
            __( 'Licenses', 'autoforum' ),
            'manage_options',
            self::LICENSES_SLUG,
            [ $this, 'page_licenses' ]
        );

        // 8. Settings — general plugin settings.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'AutoForum Settings', 'autoforum' ),
            __( 'Settings', 'autoforum' ),
            'manage_options',
            self::SETTINGS_SLUG,
            [ $this, 'page_settings' ]
        );

        // WordPress auto-creates a duplicate top-level submenu entry; remove it
        // so the sidebar shows "Dashboard" as the first item, not "AutoForum".
        remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
    }

    // ── Settings API ──────────────────────────────────────────────────────────

    public function register_settings(): void {
        register_setting(
            'af_settings_group',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => Plugin::default_settings(),
            ]
        );

        // ── Section: General ─────────────────────────────────────────────────

        add_settings_section(
            'af_general',
            __( 'General', 'autoforum' ),
            '__return_false',
            self::SETTINGS_SLUG
        );

        add_settings_field(
            'forum_page_id',
            __( 'Forum Page', 'autoforum' ),
            [ $this, 'field_page_select' ],
            self::SETTINGS_SLUG,
            'af_general',
            [ 'label_for' => 'af_forum_page_id' ]
        );

        add_settings_field(
            'primary_color',
            __( 'Primary Color', 'autoforum' ),
            [ $this, 'field_color_picker' ],
            self::SETTINGS_SLUG,
            'af_general',
            [ 'label_for' => 'af_primary_color' ]
        );

        add_settings_field(
            'threads_per_page',
            __( 'Threads Per Page', 'autoforum' ),
            [ $this, 'field_number' ],
            self::SETTINGS_SLUG,
            'af_general',
            [ 'label_for' => 'af_threads_per_page', 'key' => 'threads_per_page', 'min' => 5, 'max' => 100 ]
        );

        add_settings_field(
            'posts_per_page',
            __( 'Posts Per Page', 'autoforum' ),
            [ $this, 'field_number' ],
            self::SETTINGS_SLUG,
            'af_general',
            [ 'label_for' => 'af_posts_per_page', 'key' => 'posts_per_page', 'min' => 5, 'max' => 100 ]
        );

        // ── Section: License ─────────────────────────────────────────────────

        add_settings_section(
            'af_license',
            __( 'License & HWID Settings', 'autoforum' ),
            '__return_false',
            self::SETTINGS_SLUG
        );

        add_settings_field(
            'hwid_reset_cooldown',
            __( 'HWID Reset Cooldown (days)', 'autoforum' ),
            [ $this, 'field_number' ],
            self::SETTINGS_SLUG,
            'af_license',
            [ 'label_for' => 'af_hwid_reset_cooldown', 'key' => 'hwid_reset_cooldown', 'min' => 1, 'max' => 365 ]
        );

        add_settings_field(
            'license_duration',
            __( 'Default License Duration (days)', 'autoforum' ),
            [ $this, 'field_number' ],
            self::SETTINGS_SLUG,
            'af_license',
            [ 'label_for' => 'af_license_duration', 'key' => 'license_duration', 'min' => 1, 'max' => 3650 ]
        );

        add_settings_field(
            'woo_product_ids',
            __( 'WooCommerce Product IDs (one per line)', 'autoforum' ),
            [ $this, 'field_textarea' ],
            self::SETTINGS_SLUG,
            'af_license',
            [ 'label_for' => 'af_woo_product_ids', 'key' => 'woo_product_ids' ]
        );
    }

    // ── Settings Sanitization ─────────────────────────────────────────────────

    public function sanitize_settings( mixed $input ): array {
        // WordPress may pass false or null on the very first option write.
        if ( ! is_array( $input ) ) {
            $input = [];
        }

        $clean = Plugin::default_settings();

        $clean['forum_page_id']       = absint( $input['forum_page_id'] ?? 0 );
        $clean['threads_per_page']    = min( 100, max( 5, absint( $input['threads_per_page'] ?? 25 ) ) );
        $clean['posts_per_page']      = min( 100, max( 5, absint( $input['posts_per_page'] ?? 20 ) ) );
        $clean['hwid_reset_cooldown'] = min( 365, max( 1, absint( $input['hwid_reset_cooldown'] ?? 7 ) ) );
        $clean['license_duration']    = min( 3650, max( 1, absint( $input['license_duration'] ?? 365 ) ) );
        $clean['enable_rest_api']  = ! empty( $input['enable_rest_api'] );
        $clean['show_demo_data']   = ! empty( $input['show_demo_data'] );

        // Color — allow only valid hex codes. Cast to string first; sanitize_hex_color()
        // triggers a deprecation in PHP 8.1+ when passed null.
        $raw_color = isset( $input['primary_color'] ) ? (string) $input['primary_color'] : '';
        $color = sanitize_hex_color( $raw_color );
        $clean['primary_color'] = $color ?: '#3b82f6';

        // Product IDs — parse textarea (string) or already-stored array value.
        // The fatal TypeError on PHP 8.1+ happens because explode() receives the
        // stored array when WP re-submits existing options. Normalise both forms.
        $raw_product_input = $input['woo_product_ids'] ?? '';
        if ( is_array( $raw_product_input ) ) {
            // Already an array of IDs (option being re-saved without touching the field).
            $raw_ids = $raw_product_input;
        } else {
            // Textarea submission — split on newlines, handle \r\n from Windows browsers.
            $raw_ids = explode( "\n", str_replace( "\r", '', (string) $raw_product_input ) );
        }
        $clean['woo_product_ids'] = array_values(
            array_unique(
                array_filter( array_map( 'absint', $raw_ids ) )
            )
        );

        return $clean;
    }

    // ── Settings Fields ───────────────────────────────────────────────────────

    public function field_page_select( array $args ): void {
        $settings = $this->get_settings();
        wp_dropdown_pages( [
            'name'             => self::OPTION_KEY . '[forum_page_id]',
            'id'               => 'af_forum_page_id',
            'selected'         => $settings['forum_page_id'],
            'show_option_none' => __( '— Select a page —', 'autoforum' ),
            'option_none_value'=> '0',
        ] );
        echo '<p class="description">' . esc_html__( 'The page where the [auto_forum] shortcode lives.', 'autoforum' ) . '</p>';
    }

    public function field_color_picker( array $args ): void {
        $settings = $this->get_settings();
        printf(
            '<input type="color" id="af_primary_color" name="%s[primary_color]" value="%s" class="af-color-picker">
             <p class="description">%s</p>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $settings['primary_color'] ),
            esc_html__( 'Primary accent color used throughout the forum.', 'autoforum' )
        );
    }

    public function field_number( array $args ): void {
        $settings = $this->get_settings();
        $key      = $args['key'];
        $min      = $args['min'] ?? 1;
        $max      = $args['max'] ?? 999;
        printf(
            '<input type="number" id="af_%s" name="%s[%s]" value="%d" min="%d" max="%d" class="small-text">',
            esc_attr( $key ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            absint( $settings[ $key ] ?? 0 ),
            absint( $min ),
            absint( $max )
        );
    }

    public function field_textarea( array $args ): void {
        $settings = $this->get_settings();
        $key      = $args['key'];
        $value    = '';
        if ( 'woo_product_ids' === $key && ! empty( $settings[ $key ] ) ) {
            $value = implode( "\n", array_map( 'absint', (array) $settings[ $key ] ) );
        }
        printf(
            '<textarea id="af_%s" name="%s[%s]" rows="5" class="large-text">%s</textarea>
             <p class="description">%s</p>',
            esc_attr( $key ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_textarea( $value ),
            esc_html__( 'Enter one WooCommerce Product ID per line. Orders containing these products will automatically generate a license.', 'autoforum' )
        );
    }

    public function field_checkbox( array $args ): void {
        $settings = $this->get_settings();
        $key      = $args['key'];
        $label    = $args['label'] ?? '';
        $checked  = ! empty( $settings[ $key ] );
        printf(
            '<label><input type="checkbox" id="af_%s" name="%s[%s]" value="1" %s> %s</label>',
            esc_attr( $key ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            checked( $checked, true, false ),
            esc_html( $label )
        );
    }

    // ── Dashboard Page ────────────────────────────────────────────────────────

    public function page_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'autoforum' ) );
        }

        // ── KPI stats (transient-cached for 10 minutes) ───────────────────────
        // Heavy COUNT(*) aggregates are cached so every admin page-load
        // doesn't hammer the database. Cache is busted on relevant writes.
        $stats = get_transient( self::STATS_TRANSIENT );

        if ( false === $stats ) {
            global $wpdb;

            $stats = [
                // Total published topics.
                'total_topics'    => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . DB_Installer::topics_table() . " WHERE status != 'hidden'"
                ),
                // Total posts (replies).
                'total_posts'     => (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . DB_Installer::posts_table()
                ),
                // Reports awaiting moderator action.
                // Indexed column — uses the KEY status index.
                'pending_reports' => (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . DB_Installer::reports_table() . " WHERE status = %s",
                        'pending'
                    )
                ),
                // Active license seats.
                'active_licenses' => (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . DB_Installer::licenses_table() . " WHERE status = %s",
                        'active'
                    )
                ),
                // Total registered forum members (users with at least one post or topic).
                'total_members'   => (int) $wpdb->get_var(
                    "SELECT COUNT(DISTINCT user_id) FROM " . DB_Installer::topics_table()
                ),
            ];

            // Cache for 10 minutes. Use 0 to disable for debugging.
            set_transient( self::STATS_TRANSIENT, $stats, 10 * MINUTE_IN_SECONDS );
        }

        // ── Recent Activity (last 10 topics — NOT cached; small query) ────────
        // Single LEFT JOIN avoids the N+1 problem of fetching user per row.
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared — table names are
        // internal constants, never user input, so no injection risk.
        $recent_topics = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.title, t.status, t.sticky, t.locked,
                        t.reply_count, t.created_at,
                        u.display_name, u.ID AS user_id,
                        c.name AS category_name
                 FROM "   . DB_Installer::topics_table()      . " t
                 LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
                 LEFT JOIN " . DB_Installer::categories_table() . " c ON c.id = t.category_id
                 WHERE t.status != %s
                 ORDER BY t.created_at DESC
                 LIMIT %d",
                'hidden',
                10
            )
        );
        // phpcs:enable
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-format-chat"></span>
                <?php esc_html_e( 'AutoForum — Dashboard', 'autoforum' ); ?>
            </h1>

            <!-- ── KPI Cards ─────────────────────────────────────────────── -->
            <div class="af-kpi-grid">

                <div class="af-kpi-card">
                    <span class="dashicons dashicons-admin-comments af-kpi-icon"></span>
                    <div class="af-kpi-value"><?php echo number_format_i18n( $stats['total_topics'] ); ?></div>
                    <div class="af-kpi-label"><?php esc_html_e( 'Total Topics', 'autoforum' ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TOPICS_SLUG ) ); ?>" class="af-kpi-link">
                        <?php esc_html_e( 'Manage →', 'autoforum' ); ?>
                    </a>
                </div>

                <div class="af-kpi-card">
                    <span class="dashicons dashicons-format-chat af-kpi-icon"></span>
                    <div class="af-kpi-value"><?php echo number_format_i18n( $stats['total_posts'] ); ?></div>
                    <div class="af-kpi-label"><?php esc_html_e( 'Total Posts', 'autoforum' ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TOPICS_SLUG ) ); ?>" class="af-kpi-link">
                        <?php esc_html_e( 'Manage →', 'autoforum' ); ?>
                    </a>
                </div>

                <?php
                // Highlight pending reports in red when queue is non-empty.
                $report_class = $stats['pending_reports'] > 0 ? ' af-kpi-card--alert' : '';
                ?>
                <div class="af-kpi-card<?php echo esc_attr( $report_class ); ?>">
                    <span class="dashicons dashicons-flag af-kpi-icon"></span>
                    <div class="af-kpi-value"><?php echo number_format_i18n( $stats['pending_reports'] ); ?></div>
                    <div class="af-kpi-label"><?php esc_html_e( 'Pending Reports', 'autoforum' ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::REPORTS_SLUG ) ); ?>" class="af-kpi-link">
                        <?php esc_html_e( 'Review →', 'autoforum' ); ?>
                    </a>
                </div>

                <div class="af-kpi-card">
                    <span class="dashicons dashicons-admin-network af-kpi-icon"></span>
                    <div class="af-kpi-value"><?php echo number_format_i18n( $stats['active_licenses'] ); ?></div>
                    <div class="af-kpi-label"><?php esc_html_e( 'Active Licenses', 'autoforum' ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::LICENSES_SLUG ) ); ?>" class="af-kpi-link">
                        <?php esc_html_e( 'Manage →', 'autoforum' ); ?>
                    </a>
                </div>

                <div class="af-kpi-card">
                    <span class="dashicons dashicons-groups af-kpi-icon"></span>
                    <div class="af-kpi-value"><?php echo number_format_i18n( $stats['total_members'] ); ?></div>
                    <div class="af-kpi-label"><?php esc_html_e( 'Forum Members', 'autoforum' ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MEMBERS_SLUG ) ); ?>" class="af-kpi-link">
                        <?php esc_html_e( 'Manage →', 'autoforum' ); ?>
                    </a>
                </div>

            </div><!-- .af-kpi-grid -->

            <!-- ── Recent Activity ───────────────────────────────────────── -->
            <h2><?php esc_html_e( 'Recent Topics', 'autoforum' ); ?></h2>
            <table class="wp-list-table widefat fixed striped af-dash-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Topic',    'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Author',   'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Replies',  'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Status',   'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Created',  'autoforum' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $recent_topics ) ) : ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2rem;">
                            <?php esc_html_e( 'No topics yet.', 'autoforum' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $recent_topics as $topic ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(
                                    admin_url( 'admin.php?page=' . self::TOPICS_SLUG . '&topic_id=' . absint( $topic->id ) )
                                ); ?>">
                                    <?php echo esc_html( $topic->title ); ?>
                                </a>
                                <?php if ( $topic->sticky ) : ?>
                                    <span class="af-badge af-badge-active" title="<?php esc_attr_e( 'Pinned', 'autoforum' ); ?>">
                                        <?php esc_html_e( 'Pinned', 'autoforum' ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $topic->locked ) : ?>
                                    <span class="af-badge af-badge-warn" title="<?php esc_attr_e( 'Locked', 'autoforum' ); ?>">
                                        <?php esc_html_e( 'Locked', 'autoforum' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $topic->category_name ?: '—' ); ?></td>
                            <td>
                                <?php if ( $topic->user_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( (int) $topic->user_id ) ); ?>">
                                        <?php echo esc_html( $topic->display_name ?: "User #{$topic->user_id}" ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php esc_html_e( '—', 'autoforum' ); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo absint( $topic->reply_count ); ?></td>
                            <td>
                                <?php
                                $sc = match( $topic->status ) {
                                    'open'   => 'af-badge-active',
                                    'closed' => 'af-badge-warn',
                                    default  => 'af-badge-muted',
                                };
                                ?>
                                <span class="af-badge <?php echo esc_attr( $sc ); ?>">
                                    <?php echo esc_html( ucfirst( $topic->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html(
                                    wp_date( get_option( 'date_format' ), strtotime( $topic->created_at ) )
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top:.5rem;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TOPICS_SLUG ) ); ?>">
                    <?php esc_html_e( 'View all topics →', 'autoforum' ); ?>
                </a>
                &nbsp;&nbsp;
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::REPORTS_SLUG ) ); ?>">
                    <?php esc_html_e( 'View pending reports →', 'autoforum' ); ?>
                </a>
            </p>

            <p class="description" style="margin-top:1.5rem;">
                <?php
                printf(
                    /* translators: %s = human-readable time until cache expires */
                    esc_html__( 'KPI counts are cached for up to 10 minutes. %s', 'autoforum' ),
                    '<a href="' . esc_url( add_query_arg( 'af_flush_stats', '1' ) ) . '">' .
                    esc_html__( 'Refresh now', 'autoforum' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
        // Allow admins to bust the transient via ?af_flush_stats=1 in the URL.
        // check_admin_referer not needed here — this is a GET with no state change,
        // and the capability check above already protects it.
        if ( isset( $_GET['af_flush_stats'] ) ) {
            delete_transient( self::STATS_TRANSIENT );
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::DASHBOARD_SLUG ) );
            exit;
        }
    }

    // ── Categories Page ───────────────────────────────────────────────────────

    public function page_categories(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::categories_table();

        // Notice after save/delete redirect.
        $notice = '';
        if ( isset( $_GET['af_notice'] ) ) {
            $notice = sanitize_key( $_GET['af_notice'] );
        }

        // Fetch all categories ordered by sort_order — small table, no pagination needed.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared — table name is an internal constant.
        $categories = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" );

        // Editing an existing category?
        $edit_cat = null;
        if ( isset( $_GET['edit'] ) ) {
            $edit_id  = absint( $_GET['edit'] );
            // Prepared query — user-supplied GET param.
            $edit_cat = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) );
        }
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-category"></span>
                <?php esc_html_e( 'Forum Categories', 'autoforum' ); ?>
            </h1>

            <?php if ( $notice === 'saved' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Category saved.', 'autoforum' ); ?></p></div>
            <?php elseif ( $notice === 'deleted' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Category deleted.', 'autoforum' ); ?></p></div>
            <?php elseif ( $notice === 'error' ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'An error occurred. Please try again.', 'autoforum' ); ?></p></div>
            <?php endif; ?>

            <div class="af-two-col">

                <!-- ── Add / Edit Form ────────────────────────────────────── -->
                <div class="af-col-form">
                    <h2><?php echo $edit_cat
                        ? esc_html__( 'Edit Category', 'autoforum' )
                        : esc_html__( 'Add New Category', 'autoforum' ); ?>
                    </h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="af_save_category">
                        <?php wp_nonce_field( 'af_save_category' ); ?>
                        <?php if ( $edit_cat ) : ?>
                            <input type="hidden" name="category_id" value="<?php echo absint( $edit_cat->id ); ?>">
                        <?php endif; ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="af_cat_name"><?php esc_html_e( 'Name', 'autoforum' ); ?></label></th>
                                <td><input type="text" id="af_cat_name" name="cat_name" class="regular-text" required
                                        value="<?php echo esc_attr( $edit_cat->name ?? '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="af_cat_slug"><?php esc_html_e( 'Slug', 'autoforum' ); ?></label></th>
                                <td>
                                    <input type="text" id="af_cat_slug" name="cat_slug" class="regular-text"
                                           value="<?php echo esc_attr( $edit_cat->slug ?? '' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Leave blank to auto-generate from name.', 'autoforum' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="af_cat_desc"><?php esc_html_e( 'Description', 'autoforum' ); ?></label></th>
                                <td><textarea id="af_cat_desc" name="cat_description" rows="3" class="large-text"><?php
                                    echo esc_textarea( $edit_cat->description ?? '' );
                                ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Icon', 'autoforum' ); ?></label></th>
                                <td class="af-icon-td">
                                    <input type="hidden" id="af_cat_icon" name="cat_icon"
                                           value="<?php echo esc_attr( $edit_cat->icon ?? '' ); ?>">

                                    <!-- Trigger button -->
                                    <button type="button" id="af-icon-picker-btn" class="button af-icon-picker-btn">
                                        <span id="af-icon-preview" class="af-icon-preview">
                                            <?php if ( ! empty( $edit_cat->icon ) ) : ?>
                                                <i class="fa-solid <?php echo esc_attr( $edit_cat->icon ); ?>"></i>
                                            <?php else : ?>
                                                <i class="fa-solid fa-icons"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span id="af-icon-label"><?php
                                            echo ! empty( $edit_cat->icon )
                                                ? esc_html( $edit_cat->icon )
                                                : esc_html__( 'Choose icon…', 'autoforum' );
                                        ?></span>
                                        <i id="af-icon-chevron" class="fa-solid fa-chevron-down" style="margin-left:auto;font-size:11px;opacity:.6"></i>
                                    </button>

                                    <?php $has_icon = ! empty( $edit_cat->icon ); ?>
                                    <button type="button" id="af-icon-clear" class="button"
                                            style="margin-left:6px;<?php echo $has_icon ? '' : 'display:none'; ?>">
                                        <?php esc_html_e( 'Clear', 'autoforum' ); ?>
                                    </button>

                                    <!-- Inline expandable panel -->
                                    <div id="af-icon-panel" class="af-icon-panel" style="display:none">
                                        <div class="af-icon-panel-search">
                                            <input type="search" id="af-icon-search"
                                                   placeholder="<?php esc_attr_e( 'Search icons…', 'autoforum' ); ?>"
                                                   autocomplete="off">
                                        </div>
                                        <div id="af-icon-grid" class="af-icon-grid"></div>
                                    </div>

                                    <p class="description"><?php esc_html_e( 'Click to browse Font Awesome 6 icons.', 'autoforum' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="af_cat_color"><?php esc_html_e( 'Accent Color', 'autoforum' ); ?></label></th>
                                <td><input type="color" id="af_cat_color" name="cat_color" class="af-color-picker"
                                           value="<?php echo esc_attr( $edit_cat->color ?: '#3b82f6' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="af_cat_order"><?php esc_html_e( 'Sort Order', 'autoforum' ); ?></label></th>
                                <td><input type="number" id="af_cat_order" name="cat_sort_order" min="0" max="9999"
                                           class="small-text" value="<?php echo absint( $edit_cat->sort_order ?? 0 ); ?>"></td>
                            </tr>
                        </table>

                        <?php submit_button( $edit_cat
                            ? __( 'Update Category', 'autoforum' )
                            : __( 'Add Category',    'autoforum' )
                        ); ?>

                        <?php if ( $edit_cat ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::CATEGORIES_SLUG ) ); ?>"
                               class="button"><?php esc_html_e( 'Cancel', 'autoforum' ); ?></a>
                        <?php endif; ?>
                    </form>


                </div>

                <!-- ── Category Table ─────────────────────────────────────── -->
                <div class="af-col-list">
                    <h2><?php esc_html_e( 'All Categories', 'autoforum' ); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name',    'autoforum' ); ?></th>
                                <th><?php esc_html_e( 'Slug',    'autoforum' ); ?></th>
                                <th><?php esc_html_e( 'Topics',  'autoforum' ); ?></th>
                                <th><?php esc_html_e( 'Order',   'autoforum' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'autoforum' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $categories ) ) : ?>
                            <tr><td colspan="5" style="text-align:center;padding:2rem;">
                                <?php esc_html_e( 'No categories yet.', 'autoforum' ); ?>
                            </td></tr>
                        <?php else : ?>
                            <?php foreach ( $categories as $cat ) : ?>
                                <tr>
                                    <td>
                                        <?php if ( $cat->icon ) : ?>
                                            <span style="color:<?php echo esc_attr( $cat->color ); ?>">
                                                <?php echo esc_html( $cat->icon ); ?>
                                            </span>
                                        <?php endif; ?>
                                        <strong><?php echo esc_html( $cat->name ); ?></strong>
                                    </td>
                                    <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                    <td><?php echo absint( $cat->topic_count ); ?></td>
                                    <td><?php echo absint( $cat->sort_order ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( add_query_arg( [
                                            'page' => self::CATEGORIES_SLUG,
                                            'edit' => absint( $cat->id ),
                                        ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'Edit', 'autoforum' ); ?>
                                        </a>
                                        &nbsp;
                                        <!-- Delete: POST form with nonce — GET links are CSRF-vulnerable. -->
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                              style="display:inline;"
                                              onsubmit="return confirm('<?php echo esc_js( __( 'Delete this category? Topics inside will become uncategorised.', 'autoforum' ) ); ?>')">
                                            <input type="hidden" name="action"      value="af_delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo absint( $cat->id ); ?>">
                                            <?php wp_nonce_field( 'af_delete_category' ); ?>
                                            <button type="submit" class="button button-small" style="color:#c0392b;">
                                                <?php esc_html_e( 'Delete', 'autoforum' ); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div><!-- .af-two-col -->
        </div>
        <?php
    }

    // ── Topics & Posts Page ───────────────────────────────────────────────────

    public function page_topics(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $per_page    = 25;
        $current_pag = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset      = ( $current_pag - 1 ) * $per_page;

        // Sanitise the search term before use in LIKE — never interpolate raw user input.
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $params = [];
        $where  = "WHERE t.status != 'hidden'";

        if ( $search !== '' ) {
            // $wpdb->esc_like() escapes LIKE wildcards; wrapping in %% is handled by prepare().
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (t.title LIKE %s OR u.display_name LIKE %s)';
            $params = [ $like, $like ];
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_sql = "SELECT COUNT(*)
                      FROM "   . DB_Installer::topics_table() . " t
                      LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
                      {$where}";

        $total = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
            : (int) $wpdb->get_var( $total_sql );

        $data_sql = "SELECT t.id, t.title, t.status, t.sticky, t.locked,
                            t.reply_count, t.views, t.created_at,
                            u.display_name, u.ID AS user_id,
                            c.name AS category_name
                     FROM "   . DB_Installer::topics_table() . " t
                     LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
                     LEFT JOIN " . DB_Installer::categories_table() . " c ON c.id = t.category_id
                     {$where}
                     ORDER BY t.created_at DESC
                     LIMIT %d OFFSET %d";

        $topics = $params
            ? $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, [ $per_page, $offset ] ) ) )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
        // phpcs:enable

        $total_pages = (int) ceil( $total / $per_page );
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-admin-comments"></span>
                <?php esc_html_e( 'Topics & Posts', 'autoforum' ); ?>
            </h1>

            <!-- Search -->
            <form method="get" class="af-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::TOPICS_SLUG ); ?>">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search by title or author…', 'autoforum' ); ?>">
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'autoforum' ); ?></button>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped af-topics-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Topic',    'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Author',   'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Replies',  'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Views',    'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Status',   'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Actions',  'autoforum' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $topics ) ) : ?>
                    <tr><td colspan="7" style="text-align:center;padding:2rem;">
                        <?php esc_html_e( 'No topics found.', 'autoforum' ); ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $topics as $topic ) : ?>
                        <tr id="af-topic-row-<?php echo absint( $topic->id ); ?>">
                            <td>
                                <strong><?php echo esc_html( $topic->title ); ?></strong>
                                <?php if ( $topic->sticky ) : ?>
                                    <span class="af-badge af-badge-active"><?php esc_html_e( 'Pinned', 'autoforum' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $topic->locked ) : ?>
                                    <span class="af-badge af-badge-warn"><?php esc_html_e( 'Locked', 'autoforum' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $topic->category_name ?: '—' ); ?></td>
                            <td>
                                <?php if ( $topic->user_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( (int) $topic->user_id ) ); ?>">
                                        <?php echo esc_html( $topic->display_name ?: "User #{$topic->user_id}" ); ?>
                                    </a>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                            <td><?php echo absint( $topic->reply_count ); ?></td>
                            <td><?php echo absint( $topic->views ); ?></td>
                            <td>
                                <?php $sc = match( $topic->status ) {
                                    'open'   => 'af-badge-active',
                                    'closed' => 'af-badge-warn',
                                    default  => 'af-badge-muted',
                                }; ?>
                                <span class="af-badge <?php echo esc_attr( $sc ); ?>">
                                    <?php echo esc_html( ucfirst( $topic->status ) ); ?>
                                </span>
                            </td>
                            <td class="af-action-cell">
                                <!-- Lock / Unlock — AJAX, nonce per action+id -->
                                <button class="button button-small af-btn-lock-topic"
                                        data-id="<?php echo absint( $topic->id ); ?>"
                                        data-locked="<?php echo absint( $topic->locked ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_lock_topic_' . $topic->id ) ); ?>">
                                    <?php echo $topic->locked
                                        ? esc_html__( 'Unlock', 'autoforum' )
                                        : esc_html__( 'Lock',   'autoforum' ); ?>
                                </button>
                                <!-- Pin / Unpin -->
                                <button class="button button-small af-btn-pin-topic"
                                        data-id="<?php echo absint( $topic->id ); ?>"
                                        data-sticky="<?php echo absint( $topic->sticky ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_pin_topic_' . $topic->id ) ); ?>">
                                    <?php echo $topic->sticky
                                        ? esc_html__( 'Unpin', 'autoforum' )
                                        : esc_html__( 'Pin',   'autoforum' ); ?>
                                </button>
                                <!-- Delete topic -->
                                <button class="button button-small af-btn-delete-topic"
                                        data-id="<?php echo absint( $topic->id ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_delete_topic_admin_' . $topic->id ) ); ?>"
                                        style="color:#c0392b;">
                                    <?php esc_html_e( 'Delete', 'autoforum' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="7" style="font-weight:normal;color:#666;">
                        <?php printf( esc_html__( 'Total: %d topics', 'autoforum' ), absint( $total ) ); ?>
                    </th></tr>
                </tfoot>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom"><div class="tablenav-pages">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_pag,
                    ] ); ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Reports Page ──────────────────────────────────────────────────────────

    public function page_reports(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $per_page    = 20;
        $current_pag = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset      = ( $current_pag - 1 ) * $per_page;

        // Filter by status tab: pending (default) | resolved | dismissed.
        $tab_status = sanitize_key( $_GET['status'] ?? 'pending' );
        if ( ! in_array( $tab_status, [ 'pending', 'resolved', 'dismissed' ], true ) ) {
            $tab_status = 'pending';
        }

        $rt = DB_Installer::reports_table();
        $pt = DB_Installer::posts_table();

        // Single JOIN query — avoids N+1 (no per-row user lookup).
        // Uses the KEY status index on af_reports.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$rt} WHERE status = %s",
            $tab_status
        ) );

        $reports = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id, r.reason, r.status, r.created_at, r.resolved_at,
                    r.post_id,
                    p.content AS post_snippet,
                    p.topic_id,
                    reporter.display_name AS reporter_name,
                    reporter.ID           AS reporter_id,
                    resolver.display_name AS resolver_name
             FROM {$rt} r
             LEFT JOIN {$pt}          p        ON p.id  = r.post_id
             LEFT JOIN {$wpdb->users} reporter ON reporter.ID = r.reporter_id
             LEFT JOIN {$wpdb->users} resolver ON resolver.ID = r.resolver_id
             WHERE r.status = %s
             ORDER BY r.created_at DESC
             LIMIT %d OFFSET %d",
            $tab_status, $per_page, $offset
        ) );
        // phpcs:enable

        $total_pages = (int) ceil( $total / $per_page );

        // Pending count for tab badge.
        $pending_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$rt} WHERE status = %s", 'pending'
        ) );
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-flag"></span>
                <?php esc_html_e( 'Content Reports', 'autoforum' ); ?>
            </h1>

            <!-- Status tabs -->
            <nav class="nav-tab-wrapper">
                <?php foreach ( [ 'pending' => __( 'Pending', 'autoforum' ), 'resolved' => __( 'Resolved', 'autoforum' ), 'dismissed' => __( 'Dismissed', 'autoforum' ) ] as $s => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::REPORTS_SLUG, 'status' => $s ], admin_url( 'admin.php' ) ) ); ?>"
                       class="nav-tab<?php echo $tab_status === $s ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $s === 'pending' && $pending_count > 0 ) : ?>
                            <span class="af-badge af-badge-danger" style="margin-left:4px;"><?php echo absint( $pending_count ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <table class="wp-list-table widefat fixed striped" style="margin-top:1rem;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Reporter',     'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Reason',       'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Post Snippet', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Reported',     'autoforum' ); ?></th>
                        <?php if ( $tab_status !== 'pending' ) : ?>
                            <th><?php esc_html_e( 'Resolved By', 'autoforum' ); ?></th>
                            <th><?php esc_html_e( 'Resolved At', 'autoforum' ); ?></th>
                        <?php endif; ?>
                        <?php if ( $tab_status === 'pending' ) : ?>
                            <th><?php esc_html_e( 'Actions', 'autoforum' ); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $reports ) ) : ?>
                    <tr><td colspan="7" style="text-align:center;padding:2rem;">
                        <?php esc_html_e( 'No reports found.', 'autoforum' ); ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $reports as $rep ) : ?>
                        <tr id="af-report-row-<?php echo absint( $rep->id ); ?>">
                            <td>
                                <?php if ( $rep->reporter_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( (int) $rep->reporter_id ) ); ?>">
                                        <?php echo esc_html( $rep->reporter_name ?: "User #{$rep->reporter_id}" ); ?>
                                    </a>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $rep->reason ); ?></td>
                            <td>
                                <!-- Truncate post content to 120 chars to avoid wall-of-text. -->
                                <span title="<?php echo esc_attr( wp_strip_all_tags( $rep->post_snippet ?? '' ) ); ?>">
                                    <?php echo esc_html( mb_substr( wp_strip_all_tags( $rep->post_snippet ?? '' ), 0, 120 ) ); ?>…
                                </span>
                                <?php if ( $rep->topic_id ) : ?>
                                    <br><small>
                                        <?php printf(
                                            '<a href="%s">%s</a>',
                                            esc_url( admin_url( 'admin.php?page=' . self::TOPICS_SLUG . '&topic_id=' . absint( $rep->topic_id ) ) ),
                                            esc_html__( 'View Topic', 'autoforum' )
                                        ); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $rep->created_at ) ) ); ?></td>
                            <?php if ( $tab_status !== 'pending' ) : ?>
                                <td><?php echo esc_html( $rep->resolver_name ?: '—' ); ?></td>
                                <td><?php echo $rep->resolved_at
                                    ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $rep->resolved_at ) ) )
                                    : '—'; ?>
                                </td>
                            <?php endif; ?>
                            <?php if ( $tab_status === 'pending' ) : ?>
                                <td class="af-action-cell">
                                    <!-- Resolve: marks report handled, post stays. -->
                                    <button class="button button-small af-btn-resolve-report"
                                            data-id="<?php echo absint( $rep->id ); ?>"
                                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_resolve_report_' . $rep->id ) ); ?>">
                                        <?php esc_html_e( 'Resolve', 'autoforum' ); ?>
                                    </button>
                                    <!-- Dismiss: false-positive / no action needed. -->
                                    <button class="button button-small af-btn-dismiss-report"
                                            data-id="<?php echo absint( $rep->id ); ?>"
                                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_dismiss_report_' . $rep->id ) ); ?>">
                                        <?php esc_html_e( 'Dismiss', 'autoforum' ); ?>
                                    </button>
                                    <!-- Delete Post: nuclear option — removes the post from the forum. -->
                                    <button class="button button-small af-btn-delete-report-post"
                                            data-id="<?php echo absint( $rep->id ); ?>"
                                            data-post-id="<?php echo absint( $rep->post_id ); ?>"
                                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_delete_report_post_' . $rep->id ) ); ?>"
                                            style="color:#c0392b;">
                                        <?php esc_html_e( 'Delete Post', 'autoforum' ); ?>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom"><div class="tablenav-pages">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_pag,
                    ] ); ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Members Page ──────────────────────────────────────────────────────────

    public function page_members(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $per_page    = 25;
        $current_pag = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset      = ( $current_pag - 1 ) * $per_page;
        $search      = sanitize_text_field( $_GET['s'] ?? '' );

        // Build WHERE — search against user_login and display_name.
        $where  = '';
        $params = [];
        if ( $search !== '' ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where  = 'WHERE u.user_login LIKE %s OR u.display_name LIKE %s';
            $params = [ $like, $like ];
        }

        // Single query with LEFT JOINs on usermeta for forum-specific counters.
        // This avoids N+1 get_user_meta() calls per row.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_sql = "SELECT COUNT(DISTINCT u.ID)
                      FROM {$wpdb->users} u
                      INNER JOIN " . DB_Installer::topics_table() . " t ON t.user_id = u.ID
                      {$where}";

        $total = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
            : (int) $wpdb->get_var( $total_sql );

        $data_sql = "SELECT u.ID, u.user_login, u.display_name, u.user_email, u.user_registered,
                            COUNT(DISTINCT t.id)  AS topic_count,
                            COUNT(DISTINCT p.id)  AS post_count,
                            MAX(p.created_at)     AS last_active,
                            ban_meta.meta_value   AS is_banned
                     FROM {$wpdb->users} u
                     INNER JOIN " . DB_Installer::topics_table() . " t ON t.user_id = u.ID
                     LEFT JOIN  " . DB_Installer::posts_table()  . " p ON p.user_id = u.ID
                     LEFT JOIN  {$wpdb->usermeta} ban_meta
                                ON ban_meta.user_id = u.ID AND ban_meta.meta_key = 'af_forum_banned'
                     {$where}
                     GROUP BY u.ID
                     ORDER BY last_active DESC
                     LIMIT %d OFFSET %d";

        $members = $params
            ? $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, [ $per_page, $offset ] ) ) )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
        // phpcs:enable

        $total_pages = (int) ceil( $total / $per_page );
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e( 'Forum Members', 'autoforum' ); ?>
            </h1>

            <form method="get" class="af-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::MEMBERS_SLUG ); ?>">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search by username or display name…', 'autoforum' ); ?>">
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'autoforum' ); ?></button>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'User',        'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Topics',      'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Posts',       'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Last Active', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Status',      'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Actions',     'autoforum' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $members ) ) : ?>
                    <tr><td colspan="6" style="text-align:center;padding:2rem;">
                        <?php esc_html_e( 'No forum members found.', 'autoforum' ); ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $members as $member ) : ?>
                        <tr id="af-member-row-<?php echo absint( $member->ID ); ?>">
                            <td>
                                <a href="<?php echo esc_url( get_edit_user_link( (int) $member->ID ) ); ?>">
                                    <strong><?php echo esc_html( $member->display_name ); ?></strong>
                                </a>
                                <br><small><?php echo esc_html( $member->user_email ); ?></small>
                            </td>
                            <td><?php echo absint( $member->topic_count ); ?></td>
                            <td><?php echo absint( $member->post_count ); ?></td>
                            <td>
                                <?php echo $member->last_active
                                    ? esc_html( human_time_diff( strtotime( $member->last_active ), time() ) . ' ' . __( 'ago', 'autoforum' ) )
                                    : '—'; ?>
                            </td>
                            <td>
                                <?php if ( $member->is_banned ) : ?>
                                    <span class="af-badge af-badge-danger"><?php esc_html_e( 'Banned', 'autoforum' ); ?></span>
                                <?php else : ?>
                                    <span class="af-badge af-badge-active"><?php esc_html_e( 'Active', 'autoforum' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="af-action-cell">
                                <!-- Ban / Unban toggle — stored as usermeta af_forum_banned=1 -->
                                <button class="button button-small af-btn-ban-member"
                                        data-id="<?php echo absint( $member->ID ); ?>"
                                        data-banned="<?php echo $member->is_banned ? '1' : '0'; ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_ban_member_' . $member->ID ) ); ?>"
                                        <?php echo $member->is_banned ? '' : 'style="color:#c0392b;"'; ?>>
                                    <?php echo $member->is_banned
                                        ? esc_html__( 'Unban',   'autoforum' )
                                        : esc_html__( 'Ban',     'autoforum' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="6" style="font-weight:normal;color:#666;">
                        <?php printf( esc_html__( 'Total forum members: %d', 'autoforum' ), absint( $total ) ); ?>
                    </th></tr>
                </tfoot>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom"><div class="tablenav-pages">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_pag,
                    ] ); ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Attachments Page ──────────────────────────────────────────────────────

    public function page_attachments(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $per_page    = 25;
        $current_pag = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset      = ( $current_pag - 1 ) * $per_page;
        $search      = sanitize_text_field( $_GET['s'] ?? '' );

        $at     = DB_Installer::attachments_table();
        $where  = '';
        $params = [];

        if ( $search !== '' ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where  = 'WHERE a.file_name LIKE %s OR u.display_name LIKE %s';
            $params = [ $like, $like ];
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_sql = "SELECT COUNT(*)
                      FROM {$at} a
                      LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                      {$where}";

        $total = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
            : (int) $wpdb->get_var( $total_sql );

        $data_sql = "SELECT a.id, a.file_name, a.file_path, a.file_size,
                            a.mime_type, a.download_count, a.created_at,
                            a.post_id,
                            u.display_name, u.ID AS user_id
                     FROM {$at} a
                     LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                     {$where}
                     ORDER BY a.created_at DESC
                     LIMIT %d OFFSET %d";

        $attachments = $params
            ? $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, [ $per_page, $offset ] ) ) )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
        // phpcs:enable

        $total_pages = (int) ceil( $total / $per_page );
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-paperclip"></span>
                <?php esc_html_e( 'Forum Attachments', 'autoforum' ); ?>
            </h1>

            <form method="get" class="af-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::ATTACHMENTS_SLUG ); ?>">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search by filename or uploader…', 'autoforum' ); ?>">
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'autoforum' ); ?></button>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'File',      'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Type',      'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Size',      'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Uploader',  'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Post ID',   'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Downloads', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Uploaded',  'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Actions',   'autoforum' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $attachments ) ) : ?>
                    <tr><td colspan="8" style="text-align:center;padding:2rem;">
                        <?php esc_html_e( 'No attachments found.', 'autoforum' ); ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $attachments as $att ) : ?>
                        <tr id="af-att-row-<?php echo absint( $att->id ); ?>">
                            <td>
                                <a href="<?php echo esc_url( $att->file_path ); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html( $att->file_name ); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html( $att->mime_type ); ?></code></td>
                            <td><?php echo esc_html( size_format( (int) $att->file_size ) ); ?></td>
                            <td>
                                <?php if ( $att->user_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( (int) $att->user_id ) ); ?>">
                                        <?php echo esc_html( $att->display_name ?: "User #{$att->user_id}" ); ?>
                                    </a>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                            <td><?php echo $att->post_id ? absint( $att->post_id ) : '—'; ?></td>
                            <td><?php echo absint( $att->download_count ); ?></td>
                            <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $att->created_at ) ) ); ?></td>
                            <td>
                                <!-- Delete: removes DB record AND physical file via wp_delete_file(). -->
                                <button class="button button-small af-btn-delete-attachment"
                                        data-id="<?php echo absint( $att->id ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'af_delete_attachment_' . $att->id ) ); ?>"
                                        style="color:#c0392b;">
                                    <?php esc_html_e( 'Delete', 'autoforum' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="8" style="font-weight:normal;color:#666;">
                        <?php printf( esc_html__( 'Total attachments: %d', 'autoforum' ), absint( $total ) ); ?>
                    </th></tr>
                </tfoot>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom"><div class="tablenav-pages">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_pag,
                    ] ); ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Form Handlers (admin-post.php) ────────────────────────────────────────

    public function handle_save_category(): void {
        // Auth + CSRF — both must pass before touching the DB.
        check_admin_referer( 'af_save_category' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::categories_table();

        // Sanitise every field — never trust $_POST directly.
        $category_id  = absint( $_POST['category_id'] ?? 0 );
        $name         = sanitize_text_field( $_POST['cat_name'] ?? '' );
        $description  = sanitize_textarea_field( $_POST['cat_description'] ?? '' );
        $icon         = sanitize_text_field( $_POST['cat_icon'] ?? '' );
        $color        = sanitize_hex_color( $_POST['cat_color'] ?? '' ) ?: '#3b82f6';
        $sort_order   = absint( $_POST['cat_sort_order'] ?? 0 );

        // Slug: use supplied value or auto-generate from name.
        $raw_slug = sanitize_text_field( $_POST['cat_slug'] ?? '' );
        $slug     = $raw_slug !== ''
            ? sanitize_title( $raw_slug )
            : sanitize_title( $name );

        if ( $name === '' ) {
            wp_safe_redirect( add_query_arg( [ 'page' => self::CATEGORIES_SLUG, 'af_notice' => 'error' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $data   = compact( 'name', 'slug', 'description', 'icon', 'color', 'sort_order' );
        $format = [ '%s', '%s', '%s', '%s', '%s', '%d' ];

        if ( $category_id > 0 ) {
            $result = $wpdb->update( $table, $data, [ 'id' => $category_id ], $format, [ '%d' ] );
        } else {
            $result = $wpdb->insert( $table, $data, $format );
        }

        $notice = ( false !== $result ) ? 'saved' : 'error';
        wp_safe_redirect( add_query_arg( [ 'page' => self::CATEGORIES_SLUG, 'af_notice' => $notice ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_delete_category(): void {
        check_admin_referer( 'af_delete_category' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'autoforum' ) );
        }

        $category_id = absint( $_POST['category_id'] ?? 0 );
        if ( ! $category_id ) {
            wp_safe_redirect( add_query_arg( [ 'page' => self::CATEGORIES_SLUG, 'af_notice' => 'error' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        global $wpdb;
        $result = $wpdb->delete( DB_Installer::categories_table(), [ 'id' => $category_id ], [ '%d' ] );
        $notice = ( false !== $result ) ? 'deleted' : 'error';

        // Orphan topics — set their category_id to 0 (uncategorised) rather than cascade-delete.
        if ( false !== $result ) {
            $wpdb->update(
                DB_Installer::topics_table(),
                [ 'category_id' => 0 ],
                [ 'category_id' => $category_id ],
                [ '%d' ], [ '%d' ]
            );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => self::CATEGORIES_SLUG, 'af_notice' => $notice ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── AJAX Handlers (Steps 4 & 5) ───────────────────────────────────────────

    public function ajax_resolve_report(): void {
        $id = absint( $_POST['id'] ?? 0 );
        // Per-record nonce prevents replay attacks against other report IDs.
        check_ajax_referer( 'af_resolve_report_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $result = $wpdb->update(
            DB_Installer::reports_table(),
            [
                'status'      => 'resolved',
                'resolved_at' => current_time( 'mysql', true ), // UTC datetime.
                'resolver_id' => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        false !== $result
            ? wp_send_json_success()
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_dismiss_report(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_dismiss_report_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $result = $wpdb->update(
            DB_Installer::reports_table(),
            [
                'status'      => 'dismissed',
                'resolved_at' => current_time( 'mysql', true ),
                'resolver_id' => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        false !== $result
            ? wp_send_json_success()
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_delete_report_post(): void {
        $id      = absint( $_POST['id'] ?? 0 );
        $post_id = absint( $_POST['post_id'] ?? 0 );
        check_ajax_referer( 'af_delete_report_post_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;

        // Delete the forum post record.
        $wpdb->delete( DB_Installer::posts_table(), [ 'id' => $post_id ], [ '%d' ] );

        // Resolve the report — post is gone, job done.
        $wpdb->update(
            DB_Installer::reports_table(),
            [
                'status'      => 'resolved',
                'resolved_at' => current_time( 'mysql', true ),
                'resolver_id' => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        // Also resolve any other pending reports against the same post.
        $wpdb->update(
            DB_Installer::reports_table(),
            [ 'status' => 'resolved', 'resolved_at' => current_time( 'mysql', true ), 'resolver_id' => get_current_user_id() ],
            [ 'post_id' => $post_id, 'status' => 'pending' ],
            [ '%s', '%s', '%d' ], [ '%d', '%s' ]
        );

        wp_send_json_success();
    }

    public function ajax_lock_topic(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_lock_topic_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        // Read current state then toggle — prevents double-lock race.
        global $wpdb;
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT locked FROM ' . DB_Installer::topics_table() . ' WHERE id = %d',
            $id
        ) );
        $new_val = $current ? 0 : 1;

        $result = $wpdb->update(
            DB_Installer::topics_table(),
            [ 'locked' => $new_val ],
            [ 'id'     => $id ],
            [ '%d' ], [ '%d' ]
        );

        false !== $result
            ? wp_send_json_success( [ 'locked' => $new_val ] )
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_pin_topic(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_pin_topic_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT sticky FROM ' . DB_Installer::topics_table() . ' WHERE id = %d',
            $id
        ) );
        $new_val = $current ? 0 : 1;

        $result = $wpdb->update(
            DB_Installer::topics_table(),
            [ 'sticky' => $new_val ],
            [ 'id'     => $id ],
            [ '%d' ], [ '%d' ]
        );

        false !== $result
            ? wp_send_json_success( [ 'sticky' => $new_val ] )
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_delete_topic_admin(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_delete_topic_admin_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        // Cascade: delete all posts in the topic, then the topic itself.
        $wpdb->delete( DB_Installer::posts_table(),  [ 'topic_id' => $id ], [ '%d' ] );
        $wpdb->delete( DB_Installer::topics_table(), [ 'id'       => $id ], [ '%d' ] );

        // Bust dashboard stats cache so counts reflect deletion immediately.
        delete_transient( self::STATS_TRANSIENT );

        wp_send_json_success();
    }

    public function ajax_delete_post_admin(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_delete_post_admin_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $result = $wpdb->delete( DB_Installer::posts_table(), [ 'id' => $id ], [ '%d' ] );

        delete_transient( self::STATS_TRANSIENT );

        false !== $result
            ? wp_send_json_success()
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_ban_member(): void {
        $user_id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_ban_member_' . $user_id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        // Prevent self-ban — admins can't lock themselves out.
        if ( $user_id === get_current_user_id() ) {
            wp_send_json_error( __( 'You cannot ban yourself.', 'autoforum' ) );
        }

        // Toggle the ban state using usermeta — no custom table needed.
        $is_banned = (bool) get_user_meta( $user_id, 'af_forum_banned', true );
        if ( $is_banned ) {
            delete_user_meta( $user_id, 'af_forum_banned' );
            wp_send_json_success( [ 'banned' => false ] );
        } else {
            update_user_meta( $user_id, 'af_forum_banned', 1 );
            wp_send_json_success( [ 'banned' => true ] );
        }
    }

    public function ajax_delete_attachment(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_ajax_referer( 'af_delete_attachment_' . $id, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::attachments_table();

        // Fetch file path BEFORE deleting the DB record.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT file_path FROM {$table} WHERE id = %d", $id ) );
        if ( ! $row ) {
            wp_send_json_error( __( 'Attachment not found.', 'autoforum' ) );
        }

        // Translate URL → absolute server path so wp_delete_file() can unlink it.
        $file_path = str_replace(
            wp_upload_dir()['baseurl'],
            wp_upload_dir()['basedir'],
            $row->file_path
        );

        // Delete DB record first — if file deletion fails the record is still gone,
        // which is the safer outcome (orphan file vs orphan record pointing to nothing).
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        // wp_delete_file() calls unlink() inside a try/catch and fires a hook.
        if ( file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
        }

        wp_send_json_success();
    }

    // ── News Ticker Page ──────────────────────────────────────────────────────

    public function page_ticker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'autoforum' ) );
        }

        $ticker  = $this->get_ticker();
        $items   = $ticker['items']          ?? [];
        $label   = $ticker['label']          ?? 'NEWS';
        $enabled = $ticker['enabled']        ?? true;
        $speed   = $ticker['speed']          ?? 40;
        $pause   = $ticker['pause_on_hover'] ?? true;
        $saved   = isset( $_GET['saved'] ) && '1' === $_GET['saved'];

        $ICON_LIST = [
            'fa-fire','fa-bolt','fa-star','fa-bullhorn','fa-bell',
            'fa-circle-info','fa-triangle-exclamation','fa-trophy',
            'fa-rocket','fa-tag','fa-crown','fa-flag',
            'fa-newspaper','fa-circle-check','fa-microchip',
        ];
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-megaphone"></span>
                <?php esc_html_e( 'News Ticker', 'autoforum' ); ?>
            </h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ticker settings saved.', 'autoforum' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'af_save_ticker', 'af_ticker_nonce' ); ?>
                <input type="hidden" name="action" value="af_save_ticker">

                <!-- Global options -->
                <div class="af-admin-card" style="margin-bottom:1.5rem">
                    <div class="af-card-head"><?php esc_html_e( 'Ticker Options', 'autoforum' ); ?></div>
                    <div class="af-card-body" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;align-items:start">

                        <div class="af-field">
                            <label><?php esc_html_e( 'Enable Ticker', 'autoforum' ); ?></label><br>
                            <label class="af-toggle" style="margin-top:.4rem">
                                <input type="checkbox" name="af_ticker[enabled]" value="1" <?php checked( $enabled ); ?>>
                                <span class="af-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="af-field">
                            <label for="af_ticker_label"><?php esc_html_e( 'Label Text', 'autoforum' ); ?></label>
                            <input type="text" id="af_ticker_label" name="af_ticker[label]"
                                   value="<?php echo esc_attr( $label ); ?>"
                                   maxlength="20" class="regular-text" placeholder="NEWS"
                                   style="margin-top:.4rem">
                            <p class="description"><?php esc_html_e( 'Short badge on the left (e.g. NEWS, LIVE, HOT).', 'autoforum' ); ?></p>
                        </div>

                        <div class="af-field">
                            <label for="af_ticker_speed"><?php esc_html_e( 'Scroll Speed (s)', 'autoforum' ); ?></label>
                            <input type="number" id="af_ticker_speed" name="af_ticker[speed]"
                                   value="<?php echo absint( $speed ); ?>"
                                   min="10" max="120" class="small-text"
                                   style="margin-top:.4rem">
                            <p class="description"><?php esc_html_e( 'Lower = faster. Default: 40.', 'autoforum' ); ?></p>
                        </div>

                        <div class="af-field">
                            <label><?php esc_html_e( 'Pause on Hover', 'autoforum' ); ?></label><br>
                            <label class="af-toggle" style="margin-top:.4rem">
                                <input type="checkbox" name="af_ticker[pause_on_hover]" value="1" <?php checked( $pause ); ?>>
                                <span class="af-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Items list -->
                <div class="af-admin-card">
                    <div class="af-card-head" style="display:flex;justify-content:space-between;align-items:center">
                        <span><?php esc_html_e( 'News Items', 'autoforum' ); ?></span>
                        <button type="button" class="button button-primary" id="af-ticker-add">
                            <span class="dashicons dashicons-plus-alt2" style="margin-top:3px"></span>
                            <?php esc_html_e( 'Add Item', 'autoforum' ); ?>
                        </button>
                    </div>
                    <div class="af-card-body">
                        <p style="color:#666;margin-bottom:.75rem;font-size:.85rem">
                            <?php esc_html_e( 'Drag the ≡ handle to reorder. Each item needs an icon and text.', 'autoforum' ); ?>
                        </p>
                        <div id="af-ticker-items" style="display:flex;flex-direction:column;gap:.625rem">
                            <?php foreach ( $items as $i => $item ) : ?>
                                <div class="af-ticker-item-row" draggable="false"
                                     style="display:grid;grid-template-columns:2rem 160px 1fr auto;gap:.5rem;align-items:center">
                                    <span class="af-drag-handle dashicons dashicons-move"
                                          style="cursor:grab;color:#aaa;font-size:18px" draggable="true"></span>
                                    <select name="af_ticker[items][<?php echo $i; ?>][icon]" class="af-ticker-icon-sel">
                                        <?php foreach ( $ICON_LIST as $ico ) : ?>
                                            <option value="<?php echo esc_attr( $ico ); ?>"
                                                <?php selected( $item['icon'] ?? 'fa-fire', $ico ); ?>>
                                                <?php echo esc_html( str_replace( 'fa-', '', $ico ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text"
                                           name="af_ticker[items][<?php echo $i; ?>][text]"
                                           value="<?php echo esc_attr( $item['text'] ?? '' ); ?>"
                                           class="large-text"
                                           placeholder="<?php esc_attr_e( 'News item text…', 'autoforum' ); ?>">
                                    <button type="button" class="button af-ticker-remove" title="Remove">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( empty( $items ) ) : ?>
                            <p id="af-ticker-empty" style="color:#999;font-style:italic;margin:.5rem 0">
                                <?php esc_html_e( 'No items yet — click "Add Item" to get started.', 'autoforum' ); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Template row (cloned by JS, never submitted) -->
                        <template id="af-ticker-row-tpl">
                            <div class="af-ticker-item-row" draggable="false"
                                 style="display:grid;grid-template-columns:2rem 160px 1fr auto;gap:.5rem;align-items:center">
                                <span class="af-drag-handle dashicons dashicons-move"
                                      style="cursor:grab;color:#aaa;font-size:18px" draggable="true"></span>
                                <select name="" class="af-ticker-icon-sel">
                                    <?php foreach ( $ICON_LIST as $ico ) : ?>
                                        <option value="<?php echo esc_attr( $ico ); ?>">
                                            <?php echo esc_html( str_replace( 'fa-', '', $ico ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="" class="large-text"
                                       placeholder="<?php esc_attr_e( 'News item text…', 'autoforum' ); ?>">
                                <button type="button" class="button af-ticker-remove" title="Remove">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="submit" style="margin-top:1.25rem">
                    <?php submit_button( __( 'Save Ticker', 'autoforum' ), 'primary', 'submit', false ); ?>
                </p>
            </form>
        </div>

        <script>
        (function(){
            let idx       = <?php echo count( $items ); ?>;
            const wrap    = document.getElementById('af-ticker-items');
            const tpl     = document.getElementById('af-ticker-row-tpl');
            const empty   = document.getElementById('af-ticker-empty');

            function reindex() {
                wrap.querySelectorAll('.af-ticker-item-row').forEach(function(row, i){
                    row.querySelector('select').name                  = 'af_ticker[items]['+i+'][icon]';
                    row.querySelector('input[type="text"]').name      = 'af_ticker[items]['+i+'][text]';
                });
                idx = wrap.querySelectorAll('.af-ticker-item-row').length;
                if (empty) empty.style.display = idx === 0 ? '' : 'none';
            }

            // Add
            document.getElementById('af-ticker-add').addEventListener('click', function(){
                const clone = tpl.content.cloneNode(true);
                clone.querySelector('select').name               = 'af_ticker[items]['+idx+'][icon]';
                clone.querySelector('input[type="text"]').name   = 'af_ticker[items]['+idx+'][text]';
                idx++;
                wrap.appendChild(clone);
                wrap.lastElementChild.querySelector('input[type="text"]').focus();
                if (empty) empty.style.display = 'none';
            });

            // Remove
            wrap.addEventListener('click', function(e){
                const btn = e.target.closest('.af-ticker-remove');
                if (!btn) return;
                btn.closest('.af-ticker-item-row').remove();
                reindex();
            });

            // Drag-to-reorder
            let dragging = null;
            wrap.addEventListener('dragstart', function(e){
                const row = e.target.closest('.af-ticker-item-row');
                if (!row) return;
                dragging = row;
                dragging.style.opacity = '.4';
                e.dataTransfer.effectAllowed = 'move';
            });
            wrap.addEventListener('dragover', function(e){
                e.preventDefault();
                const row = e.target.closest('.af-ticker-item-row');
                if (!row || row === dragging) return;
                const rect = row.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) {
                    wrap.insertBefore(dragging, row);
                } else {
                    wrap.insertBefore(dragging, row.nextSibling);
                }
            });
            wrap.addEventListener('dragend', function(){
                if (dragging) dragging.style.opacity = '';
                dragging = null;
                reindex();
            });
            // Make entire row draggable only via handle
            wrap.addEventListener('mousedown', function(e){
                const handle = e.target.closest('.af-drag-handle');
                const row    = handle && handle.closest('.af-ticker-item-row');
                wrap.querySelectorAll('.af-ticker-item-row').forEach(r => r.draggable = false);
                if (row) row.draggable = true;
            });

            // Init empty label
            reindex();
        })();
        </script>
        <?php
    }

    // ── News Ticker Save ──────────────────────────────────────────────────────

    public function handle_save_ticker(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'autoforum' ) );
        }
        check_admin_referer( 'af_save_ticker', 'af_ticker_nonce' );

        $raw     = is_array( $_POST['af_ticker'] ?? null ) ? $_POST['af_ticker'] : [];
        $enabled = ! empty( $raw['enabled'] );
        $label   = sanitize_text_field( wp_unslash( $raw['label'] ?? 'NEWS' ) );
        $speed   = min( 120, max( 10, absint( $raw['speed'] ?? 40 ) ) );
        $pause   = ! empty( $raw['pause_on_hover'] );

        $items = [];
        if ( ! empty( $raw['items'] ) && is_array( $raw['items'] ) ) {
            foreach ( $raw['items'] as $item ) {
                $text = sanitize_text_field( wp_unslash( $item['text'] ?? '' ) );
                $icon = sanitize_key( $item['icon'] ?? 'fa-fire' );
                if ( $text !== '' ) {
                    $items[] = [ 'text' => $text, 'icon' => $icon ];
                }
            }
        }

        update_option( self::TICKER_OPTION, [
            'enabled'        => $enabled,
            'label'          => $label ?: 'NEWS',
            'speed'          => $speed,
            'pause_on_hover' => $pause,
            'items'          => $items,
        ] );

        wp_redirect( add_query_arg( [ 'page' => self::TICKER_SLUG, 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── Ticker Data Helper ────────────────────────────────────────────────────

    private function get_ticker(): array {
        $defaults = [
            'enabled'        => true,
            'label'          => 'NEWS',
            'speed'          => 40,
            'pause_on_hover' => true,
            'items'          => [],
        ];
        $stored = get_option( self::TICKER_OPTION, [] );
        return wp_parse_args( is_array( $stored ) ? $stored : [], $defaults );
    }

    // ── Settings Page ─────────────────────────────────────────────────────────

    public function page_settings(): void {
        }
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-format-chat"></span>
                <?php esc_html_e( 'AutoForum — General Settings', 'autoforum' ); ?>
            </h1>

            <?php settings_errors( self::OPTION_KEY ); ?>

            <form method="post" action="options.php" class="af-settings-form">
                <?php
                settings_fields( 'af_settings_group' );
                do_settings_sections( self::SETTINGS_SLUG );
                submit_button( __( 'Save Settings', 'autoforum' ) );
                ?>
            </form>
        </div>
        <?php
    }

    // ── License Management Page ───────────────────────────────────────────────

    public function page_licenses(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::licenses_table();

        // Pagination.
        $per_page    = 20;
        $current_pag = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset      = ( $current_pag - 1 ) * $per_page;

        // Search.
        $search  = sanitize_text_field( $_GET['s'] ?? '' );
        $where   = '';
        $params  = [];

        if ( $search !== '' ) {
            $where  = 'WHERE l.license_key LIKE %s OR l.hwid LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $params = [ $like, $like, $like, $like ];
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_sql = "SELECT COUNT(*) FROM {$table} l LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id {$where}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) )
            : (int) $wpdb->get_var( $total_sql );

        $data_sql = "SELECT l.*, u.user_login, u.display_name
                     FROM {$table} l
                     LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
                     {$where}
                     ORDER BY l.created_at DESC
                     LIMIT %d OFFSET %d";

        $licenses = $params
            ? $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, [ $per_page, $offset ] ) ) )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
        // phpcs:enable

        $total_pages = (int) ceil( $total / $per_page );

        $nonce_add    = wp_create_nonce( 'af_add_license' );
        $nonce_edit   = wp_create_nonce( 'af_edit_license' );
        $nonce_delete = wp_create_nonce( 'af_delete_license' );
        $nonce_hwid   = wp_create_nonce( self::NONCE_HWID );
        $nonce_revoke = wp_create_nonce( 'af_revoke_bulk' ); // per-row nonces generated inline
        ?>
        <div class="wrap af-admin-wrap">
            <h1 class="af-admin-title">
                <span class="dashicons dashicons-admin-network"></span>
                <?php esc_html_e( 'License Management', 'autoforum' ); ?>
                <button type="button" class="page-title-action af-lic-add-btn">
                    + <?php esc_html_e( 'Add License', 'autoforum' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::LICENSES_SLUG . '&action=export' ) ); ?>"
                   class="page-title-action">
                    <?php esc_html_e( 'Export CSV', 'autoforum' ); ?>
                </a>
            </h1>

            <!-- Search form -->
            <form method="get" class="af-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::LICENSES_SLUG ); ?>">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search key, HWID, or username…', 'autoforum' ); ?>">
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'autoforum' ); ?></button>
                    <?php if ( $search ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::LICENSES_SLUG ) ); ?>"
                           class="button"><?php esc_html_e( 'Clear', 'autoforum' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped af-license-table">
                <thead>
                    <tr>
                        <th style="width:42px"><?php esc_html_e( 'ID', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'User', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'License Key', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'HWID', 'autoforum' ); ?></th>
                        <th style="width:54px"><?php esc_html_e( 'Resets', 'autoforum' ); ?></th>
                        <th style="width:80px"><?php esc_html_e( 'Status', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Expires', 'autoforum' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'autoforum' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $licenses ) ) : ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;">
                            <?php esc_html_e( 'No licenses found.', 'autoforum' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $licenses as $lic ) : ?>
                        <?php
                        $status_class = match ( $lic->status ) {
                            'active'    => 'af-badge-active',
                            'suspended' => 'af-badge-warn',
                            'expired'   => 'af-badge-muted',
                            'revoked'   => 'af-badge-danger',
                            default     => '',
                        };
                        ?>
                        <tr id="af-lic-row-<?php echo absint( $lic->id ); ?>">
                            <td><?php echo absint( $lic->id ); ?></td>
                            <td>
                                <?php if ( $lic->user_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( (int) $lic->user_id ) ); ?>">
                                        <?php echo esc_html( $lic->display_name ?: $lic->user_login ?: "User #{$lic->user_id}" ); ?>
                                    </a>
                                <?php else : ?>
                                    <span style="color:#999"><?php esc_html_e( '— unassigned —', 'autoforum' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code class="af-lic-key"><?php echo esc_html( $lic->license_key ); ?></code></td>
                            <td>
                                <?php if ( $lic->hwid ) : ?>
                                    <code style="font-size:.78em"><?php echo esc_html( $lic->hwid ); ?></code>
                                <?php else : ?>
                                    <span style="color:#999"><?php esc_html_e( 'Unbound', 'autoforum' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center"><?php echo absint( $lic->resets_count ); ?></td>
                            <td>
                                <span class="af-badge <?php echo esc_attr( $status_class ); ?> af-lic-status">
                                    <?php echo esc_html( ucfirst( $lic->status ) ); ?>
                                </span>
                            </td>
                            <td class="af-lic-expires">
                                <?php echo $lic->expires_at
                                    ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $lic->expires_at ) ) )
                                    : esc_html__( 'Never', 'autoforum' ); ?>
                            </td>
                            <td class="af-action-cell">
                                <!-- Edit -->
                                <button type="button"
                                    class="button button-small af-btn-edit-license"
                                    data-id="<?php echo absint( $lic->id ); ?>"
                                    data-key="<?php echo esc_attr( $lic->license_key ); ?>"
                                    data-uid="<?php echo absint( $lic->user_id ); ?>"
                                    data-uname="<?php echo esc_attr( $lic->display_name ?: $lic->user_login ); ?>"
                                    data-status="<?php echo esc_attr( $lic->status ); ?>"
                                    data-expires="<?php echo esc_attr( $lic->expires_at ? substr( $lic->expires_at, 0, 10 ) : '' ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_edit ); ?>">
                                    <?php esc_html_e( 'Edit', 'autoforum' ); ?>
                                </button>
                                <!-- Reset HWID -->
                                <button type="button"
                                    class="button button-small af-btn-hwid-reset"
                                    data-id="<?php echo absint( $lic->id ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_hwid ); ?>"
                                    title="<?php esc_attr_e( 'Clear HWID so user can bind a new device.', 'autoforum' ); ?>">
                                    <?php esc_html_e( 'Reset HWID', 'autoforum' ); ?>
                                </button>
                                <!-- Delete -->
                                <button type="button"
                                    class="button button-small af-btn-delete-license"
                                    data-id="<?php echo absint( $lic->id ); ?>"
                                    data-nonce="<?php echo esc_attr( $nonce_delete ); ?>"
                                    style="color:#c0392b;">
                                    <?php esc_html_e( 'Delete', 'autoforum' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="8" style="font-weight:normal;color:#666;">
                            <?php printf( esc_html__( 'Total licenses: %d', 'autoforum' ), absint( $total ) ); ?>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php echo paginate_links( [
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $current_pag,
                        ] ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div><!-- .wrap -->

        <!-- ── Add / Edit modal ──────────────────────────────────────────────── -->
        <div id="af-lic-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:6px;padding:28px 32px;width:480px;max-width:96vw;box-shadow:0 8px 40px rgba(0,0,0,.3);position:relative;">
                <button type="button" id="af-lic-modal-close"
                    style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#555;">&times;</button>
                <h2 id="af-lic-modal-title" style="margin:0 0 20px"><?php esc_html_e( 'License', 'autoforum' ); ?></h2>

                <table class="form-table" style="margin:0">
                    <tr>
                        <th style="width:130px"><label for="af-lic-f-key"><?php esc_html_e( 'License Key', 'autoforum' ); ?></label></th>
                        <td>
                            <input type="text" id="af-lic-f-key" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank to auto-generate', 'autoforum' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="af-lic-f-user"><?php esc_html_e( 'Assign to User', 'autoforum' ); ?></label></th>
                        <td>
                            <input type="text" id="af-lic-f-user-search" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Type username or name…', 'autoforum' ); ?>" autocomplete="off">
                            <div id="af-lic-user-suggestions"
                                 style="background:#fff;border:1px solid #ccc;border-top:none;max-height:160px;overflow-y:auto;display:none;position:absolute;width:280px;z-index:10;"></div>
                            <input type="hidden" id="af-lic-f-uid" value="0">
                            <p class="description" id="af-lic-assigned-label"></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="af-lic-f-status"><?php esc_html_e( 'Status', 'autoforum' ); ?></label></th>
                        <td>
                            <select id="af-lic-f-status">
                                <option value="active"><?php esc_html_e( 'Active', 'autoforum' ); ?></option>
                                <option value="suspended"><?php esc_html_e( 'Suspended', 'autoforum' ); ?></option>
                                <option value="expired"><?php esc_html_e( 'Expired', 'autoforum' ); ?></option>
                                <option value="revoked"><?php esc_html_e( 'Revoked', 'autoforum' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="af-lic-f-expires"><?php esc_html_e( 'Expires', 'autoforum' ); ?></label></th>
                        <td>
                            <input type="date" id="af-lic-f-expires">
                            <label style="margin-left:8px">
                                <input type="checkbox" id="af-lic-f-never"> <?php esc_html_e( 'Never', 'autoforum' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p style="margin-top:20px;display:flex;gap:8px;align-items:center">
                    <button type="button" id="af-lic-modal-save" class="button button-primary">
                        <?php esc_html_e( 'Save', 'autoforum' ); ?>
                    </button>
                    <button type="button" id="af-lic-modal-cancel" class="button">
                        <?php esc_html_e( 'Cancel', 'autoforum' ); ?>
                    </button>
                    <span id="af-lic-modal-msg" style="margin-left:8px;font-size:.85rem;"></span>
                </p>
            </div>
        </div>

        <script>
        jQuery(function($){
            var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonceAdd  = '<?php echo esc_js( $nonce_add ); ?>';
            var nonceEdit = '<?php echo esc_js( $nonce_edit ); ?>';
            var nonceDel  = '<?php echo esc_js( $nonce_delete ); ?>';
            var nonceHwid = '<?php echo esc_js( $nonce_hwid ); ?>';

            var $modal    = $('#af-lic-modal');
            var $mTitle   = $('#af-lic-modal-title');
            var $fKey     = $('#af-lic-f-key');
            var $fUserSrc = $('#af-lic-f-user-search');
            var $fUid     = $('#af-lic-f-uid');
            var $fStatus  = $('#af-lic-f-status');
            var $fExpires = $('#af-lic-f-expires');
            var $fNever   = $('#af-lic-f-never');
            var $saveBtn  = $('#af-lic-modal-save');
            var $msg      = $('#af-lic-modal-msg');
            var $suggest  = $('#af-lic-user-suggestions');

            var editId = 0;

            function openModal(mode, data) {
                editId = mode === 'edit' ? data.id : 0;
                $mTitle.text(mode === 'edit' ? 'Edit License #' + data.id : 'Add License');
                $fKey.val(mode === 'edit' ? data.key : '').prop('readonly', mode === 'edit');
                $fStatus.val(data.status || 'active');
                $fExpires.val(data.expires || '');
                $fNever.prop('checked', !data.expires);
                $fExpires.prop('disabled', !data.expires);
                $fUid.val(data.uid || 0);
                $fUserSrc.val(data.uname || '');
                $('#af-lic-assigned-label').text(data.uid ? 'Currently: ' + (data.uname || 'User #' + data.uid) : '');
                $msg.text('');
                $modal.css('display','flex');
            }

            function closeModal() { $modal.hide(); }

            $('#af-lic-modal-close, #af-lic-modal-cancel').on('click', closeModal);
            $modal.on('click', function(e){ if ($(e.target).is($modal)) closeModal(); });

            // Never checkbox toggles date input
            $fNever.on('change', function(){
                $fExpires.prop('disabled', this.checked).val(this.checked ? '' : $fExpires.val());
            });

            // Add button
            $('.af-lic-add-btn').on('click', function(){
                openModal('add', {});
            });

            // Edit button
            $(document).on('click', '.af-btn-edit-license', function(){
                var $b = $(this);
                openModal('edit', {
                    id:      $b.data('id'),
                    key:     $b.data('key'),
                    uid:     $b.data('uid'),
                    uname:   $b.data('uname'),
                    status:  $b.data('status'),
                    expires: $b.data('expires'),
                });
            });

            // User autocomplete
            var userTimer;
            $fUserSrc.on('input', function(){
                clearTimeout(userTimer);
                var q = $(this).val().trim();
                if (q.length < 2) { $suggest.hide(); return; }
                userTimer = setTimeout(function(){
                    $.post(ajaxUrl, { action: 'af_lic_user_search', q: q, nonce: nonceEdit }, function(res){
                        if (!res.success || !res.data.length) { $suggest.hide(); return; }
                        $suggest.empty().show();
                        $.each(res.data, function(_, u){
                            $('<div>').text(u.display_name + ' (@' + u.login + ')').css({
                                padding:'6px 10px', cursor:'pointer'
                            }).hover(function(){ $(this).css('background','#f0f0f0'); },
                                     function(){ $(this).css('background',''); })
                            .on('click', function(){
                                $fUid.val(u.id);
                                $fUserSrc.val(u.display_name);
                                $('#af-lic-assigned-label').text('Assigning to: ' + u.display_name);
                                $suggest.hide();
                            })
                            .appendTo($suggest);
                        });
                    });
                }, 280);
            });
            $(document).on('click', function(e){
                if (!$(e.target).closest('#af-lic-f-user-search, #af-lic-user-suggestions').length) $suggest.hide();
            });

            // Save
            $saveBtn.on('click', function(){
                var action = editId ? 'af_edit_license' : 'af_add_license';
                var nonce  = editId ? nonceEdit : nonceAdd;
                var data   = {
                    action:     action,
                    nonce:      nonce,
                    license_id: editId,
                    license_key:$fKey.val().trim(),
                    user_id:    $fUid.val(),
                    status:     $fStatus.val(),
                    expires_at: $fNever.prop('checked') ? '' : $fExpires.val(),
                };
                $saveBtn.prop('disabled', true).text('Saving…');
                $msg.text('');
                $.post(ajaxUrl, data, function(res){
                    if (res.success) {
                        $msg.css('color','green').text('✔ Saved.');
                        setTimeout(function(){ location.reload(); }, 700);
                    } else {
                        $msg.css('color','red').text(res.data || 'Error.');
                        $saveBtn.prop('disabled', false).text('Save');
                    }
                }).fail(function(){
                    $msg.css('color','red').text('Request failed.');
                    $saveBtn.prop('disabled', false).text('Save');
                });
            });

            // Force HWID Reset
            $(document).on('click', '.af-btn-hwid-reset', function(){
                var $btn = $(this), id = $btn.data('id'), nonce = $btn.data('nonce');
                if (!confirm('Clear HWID for this license? The user can then bind a new device.')) return;
                $btn.prop('disabled', true).text('…');
                $.post(ajaxUrl, { action: 'af_force_hwid_reset', license_id: id, nonce: nonce }, function(res){
                    if (res.success) {
                        $btn.closest('tr').find('code').last().replaceWith('<span style="color:#999">Unbound</span>');
                        $btn.text('Reset HWID').prop('disabled', false);
                    } else {
                        alert(res.data || 'Error.');
                        $btn.text('Reset HWID').prop('disabled', false);
                    }
                });
            });

            // Delete
            $(document).on('click', '.af-btn-delete-license', function(){
                var $btn = $(this), id = $btn.data('id'), nonce = $btn.data('nonce');
                if (!confirm('Permanently delete license #' + id + '? This cannot be undone.')) return;
                $btn.prop('disabled', true).text('…');
                $.post(ajaxUrl, { action: 'af_delete_license', license_id: id, nonce: nonce }, function(res){
                    if (res.success) {
                        $('#af-lic-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                    } else {
                        alert(res.data || 'Error.');
                        $btn.text('Delete').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // ── AJAX Handlers ─────────────────────────────────────────────────────────

    public function ajax_force_hwid_reset(): void {
        check_ajax_referer( self::NONCE_HWID, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        $license_id = absint( $_POST['license_id'] ?? 0 );
        if ( ! $license_id ) {
            wp_send_json_error( __( 'Invalid license ID.', 'autoforum' ) );
        }

        global $wpdb;
        $table  = DB_Installer::licenses_table();
        $result = $wpdb->update(
            $table,
            [ 'hwid' => '', 'resets_count' => 0 ],
            [ 'id'   => $license_id ],
            [ '%s',  '%d' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            wp_send_json_error( __( 'Database error.', 'autoforum' ) );
        }

        // Log admin action.
        do_action( 'af_admin_hwid_reset', $license_id, get_current_user_id() );

        wp_send_json_success();
    }

    public function ajax_revoke_license(): void {
        $license_id = absint( $_POST['license_id'] ?? 0 );
        check_ajax_referer( 'af_revoke_' . $license_id, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        if ( ! $license_id ) {
            wp_send_json_error( __( 'Invalid license ID.', 'autoforum' ) );
        }

        global $wpdb;
        $result = $wpdb->update(
            DB_Installer::licenses_table(),
            [ 'status' => 'revoked' ],
            [ 'id'     => $license_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            wp_send_json_error( __( 'Database error.', 'autoforum' ) );
        }

        do_action( 'af_license_revoked', $license_id, get_current_user_id() );

        wp_send_json_success();
    }

    public function ajax_add_license(): void {
        check_ajax_referer( 'af_add_license', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::licenses_table();

        $key = sanitize_text_field( $_POST['license_key'] ?? '' );
        if ( $key === '' ) {
            $key = strtoupper( implode( '-', str_split( bin2hex( random_bytes( 10 ) ), 5 ) ) );
        }

        // Ensure uniqueness.
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE license_key = %s", $key ) ) ) {
            wp_send_json_error( __( 'License key already exists.', 'autoforum' ) );
        }

        $user_id    = absint( $_POST['user_id'] ?? 0 );
        $status     = in_array( $_POST['status'] ?? '', [ 'active','suspended','expired','revoked' ], true )
                      ? $_POST['status'] : 'active';
        $expires_raw = sanitize_text_field( $_POST['expires_at'] ?? '' );
        $expires_at  = ( $expires_raw && strtotime( $expires_raw ) ) ? $expires_raw . ' 23:59:59' : null;

        $inserted = $wpdb->insert(
            $table,
            [
                'user_id'     => $user_id ?: 0,
                'license_key' => $key,
                'status'      => $status,
                'expires_at'  => $expires_at,
                'created_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', $expires_at ? '%s' : 'NULL', '%s' ]
        );

        if ( ! $inserted ) {
            wp_send_json_error( __( 'Database error — could not insert license.', 'autoforum' ) );
        }

        wp_send_json_success( [ 'license_id' => $wpdb->insert_id, 'license_key' => $key ] );
    }

    public function ajax_edit_license(): void {
        check_ajax_referer( 'af_edit_license', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        $license_id = absint( $_POST['license_id'] ?? 0 );
        if ( ! $license_id ) {
            wp_send_json_error( __( 'Invalid license ID.', 'autoforum' ) );
        }

        global $wpdb;
        $table = DB_Installer::licenses_table();

        $status     = in_array( $_POST['status'] ?? '', [ 'active','suspended','expired','revoked' ], true )
                      ? $_POST['status'] : 'active';
        $user_id    = absint( $_POST['user_id'] ?? 0 );
        $expires_raw = sanitize_text_field( $_POST['expires_at'] ?? '' );
        $expires_at  = ( $expires_raw && strtotime( $expires_raw ) ) ? $expires_raw . ' 23:59:59' : null;

        // Also allow changing the key on edit if provided.
        $new_key = sanitize_text_field( $_POST['license_key'] ?? '' );

        $data   = [ 'status' => $status, 'user_id' => $user_id, 'expires_at' => $expires_at ];
        $format = [ '%s', '%d', $expires_at ? '%s' : 'NULL' ];
        if ( $new_key !== '' ) {
            $data['license_key'] = $new_key;
            $format[]            = '%s';
        }

        $result = $wpdb->update( $table, $data, [ 'id' => $license_id ], $format, [ '%d' ] );

        if ( false === $result ) {
            wp_send_json_error( __( 'Database error.', 'autoforum' ) );
        }

        wp_send_json_success();
    }

    public function ajax_delete_license(): void {
        check_ajax_referer( 'af_delete_license', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        $license_id = absint( $_POST['license_id'] ?? 0 );
        if ( ! $license_id ) {
            wp_send_json_error( __( 'Invalid license ID.', 'autoforum' ) );
        }

        global $wpdb;
        $result = $wpdb->delete( DB_Installer::licenses_table(), [ 'id' => $license_id ], [ '%d' ] );

        false !== $result
            ? wp_send_json_success()
            : wp_send_json_error( __( 'Database error.', 'autoforum' ) );
    }

    public function ajax_lic_user_search(): void {
        check_ajax_referer( 'af_edit_license', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'autoforum' ) );
        }

        $q = sanitize_text_field( $_POST['q'] ?? '' );
        if ( strlen( $q ) < 2 ) {
            wp_send_json_success( [] );
        }

        $users = get_users( [
            'search'         => '*' . $q . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => 10,
            'fields'         => [ 'ID', 'user_login', 'display_name' ],
        ] );

        $result = array_map( fn( $u ) => [
            'id'           => (int) $u->ID,
            'login'        => $u->user_login,
            'display_name' => $u->display_name,
        ], $users );

        wp_send_json_success( $result );
    }

    // ── Asset Enqueueing ──────────────────────────────────────────────────────

    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our own pages.
        if ( strpos( $hook, 'autoforum' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'af-admin',
            AF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AF_VERSION
        );

        wp_enqueue_script(
            'af-admin',
            AF_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            AF_VERSION,
            true
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function get_settings(): array {
        $defaults = Plugin::default_settings();
        $stored   = get_option( self::OPTION_KEY, [] );
        // get_option() can return false when the row doesn't exist yet.
        return wp_parse_args( is_array( $stored ) ? $stored : [], $defaults );
    }
}
