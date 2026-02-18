<?php
/**
 * Forum API — All forum AJAX endpoints served to the front-end SPA.
 *
 * Handles:
 *  - Fetching categories, topic lists, single topic + posts.
 *  - Creating topics and replies.
 *  - Liking / thanking a post ("thank to unlock").
 *  - Incrementing topic view counter.
 *
 * Security policy applied to every handler:
 *  - check_ajax_referer() — CSRF.
 *  - is_user_logged_in() / current_user_can() — authorisation.
 *  - $wpdb->prepare() — SQL injection prevention.
 *  - sanitize_*() on input, esc_*() on output.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Forum_API {

    private const NONCE_TOPIC        = 'af_create_topic';
    private const NONCE_POST         = 'af_create_post';
    private const NONCE_THANK        = 'af_thank_post';
    private const NONCE_VIEW         = 'af_view_topic';
    private const NONCE_SEARCH       = 'af_search';
    private const NONCE_CATEGORIES   = 'af_get_categories';
    private const NONCE_TOPICS       = 'af_get_topics';
    private const NONCE_POSTS        = 'af_get_posts';
    private const NONCE_HOME_STATS   = 'af_get_home_stats';
    private const NONCE_USER_PROFILE = 'af_get_user_profile';
    private const ALLOWED_TAGS  = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'em'         => [],
        'u'          => [],
        's'          => [],
        'blockquote' => [ 'class' => true ],
        'cite'       => [],
        'code'       => [ 'class' => true ],
        'pre'        => [ 'class' => true ],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'a'          => [ 'href' => true, 'target' => true, 'rel' => true ],
        'img'        => [ 'src' => true, 'alt' => true, 'class' => true ],
        'span'       => [ 'class' => true ],
        'div'        => [ 'class' => true ],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
    ];

    private const SORT_MAP        = [
        'latest'  => 'last_replied',
        'oldest'  => 'created_at',
        'replies' => 'reply_count',
        'views'   => 'views',
    ];
    private const CACHE_GROUP     = 'autoforum';
    private const CACHE_CAT_TTL   = 10 * MINUTE_IN_SECONDS; // Categories change rarely.
    private const CACHE_TOPIC_TTL = 2 * MINUTE_IN_SECONDS;  // Topics change more often.

    public function register_hooks(): void {
        // Public reads (no login required).
        add_action( 'wp_ajax_nopriv_af_get_user_profile', [ $this, 'ajax_get_user_profile' ] );
        add_action( 'wp_ajax_af_get_user_profile',          [ $this, 'ajax_get_user_profile' ] );
        add_action( 'wp_ajax_nopriv_af_get_home_stats',  [ $this, 'ajax_get_home_stats' ] );
        add_action( 'wp_ajax_af_get_home_stats',          [ $this, 'ajax_get_home_stats' ] );
        add_action( 'wp_ajax_nopriv_af_get_categories', [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_nopriv_af_get_topics',     [ $this, 'ajax_get_topics' ] );
        add_action( 'wp_ajax_nopriv_af_get_posts',      [ $this, 'ajax_get_posts' ] );
        add_action( 'wp_ajax_nopriv_af_search',         [ $this, 'ajax_search' ] );
        // Logged-in versions of the same actions.
        add_action( 'wp_ajax_af_get_categories',        [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_af_get_topics',            [ $this, 'ajax_get_topics' ] );
        add_action( 'wp_ajax_af_get_posts',             [ $this, 'ajax_get_posts' ] );
        add_action( 'wp_ajax_af_search',                [ $this, 'ajax_search' ] );
        // View counter (fire-and-forget, no nonce needed — POST still prevents CSRF).
        add_action( 'wp_ajax_nopriv_af_view_topic',     [ $this, 'ajax_view_topic' ] );
        add_action( 'wp_ajax_af_view_topic',            [ $this, 'ajax_view_topic' ] );
        // Authenticated write actions.
        add_action( 'wp_ajax_af_heartbeat',              [ $this, 'ajax_heartbeat' ] );
        add_action( 'wp_ajax_af_create_topic',          [ $this, 'ajax_create_topic' ] );
        add_action( 'wp_ajax_af_create_post',           [ $this, 'ajax_create_post' ] );
        add_action( 'wp_ajax_af_thank_post',            [ $this, 'ajax_thank_post' ] );
        add_action( 'wp_ajax_af_delete_topic',          [ $this, 'ajax_delete_topic' ] );
        add_action( 'wp_ajax_af_delete_post',           [ $this, 'ajax_delete_post' ] );
        add_action( 'wp_ajax_af_edit_post',              [ $this, 'ajax_edit_post' ] );
        add_action( 'wp_ajax_af_upload_attachment',     [ $this, 'ajax_upload_attachment' ] );
    }

    // ── Read: Categories ─────────────────────────────────────────────────────

    public function ajax_get_categories(): void {
        check_ajax_referer( self::NONCE_CATEGORIES, 'nonce' );

        $cache_key = 'af_all_categories';
        $rows      = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false === $rows ) {
            global $wpdb;
            $table = DB_Installer::categories_table();
            $rows  = $wpdb->get_results(
                "SELECT id, name, slug, description, icon, color, parent_id, sort_order, topic_count, post_count
                 FROM {$table}
                 ORDER BY sort_order ASC, name ASC"
            ) ?: [];
            wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_CAT_TTL );
        }

        wp_send_json_success( array_map( [ $this, 'esc_category' ], $rows ) );
    }

    // ── Read: Topics list (with pagination + sort) ────────────────────────────

    public function ajax_get_topics(): void {
        check_ajax_referer( self::NONCE_TOPICS, 'nonce' );

        global $wpdb;

        $cat_id   = absint( $_POST['category_id'] ?? 0 );
        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = min( 50, max( 5, absint( get_option( 'af_settings', [] )['threads_per_page'] ?? 20 ) ) );
        $sort_raw = sanitize_key( $_POST['sort'] ?? 'latest' );
        $sort_col = self::SORT_MAP[ $sort_raw ] ?? 'last_replied';
        $offset   = ( $page - 1 ) * $per_page;

        // Cache only page 1 / default sort — most traffic hits this combination.
        $cache_key    = 'af_topics_' . $cat_id . '_' . $sort_raw . '_p' . $page;
        $cached       = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            wp_send_json_success( $cached );
        }

        $topics_table = DB_Installer::topics_table();
        $users_table  = $wpdb->users;

        $where      = $cat_id ? $wpdb->prepare( 'WHERE t.category_id = %d', $cat_id ) : '';
        $order      = "ORDER BY t.sticky DESC, t.{$sort_col} DESC";
        $limit_sql  = $wpdb->prepare( 'LIMIT %d OFFSET %d', $per_page, $offset );

        // Only select is_premium if the column already exists (safe during DB migration).
        $has_premium_col = $this->column_exists( $topics_table, 'is_premium' );
        $premium_select  = $has_premium_col ? ', t.is_premium' : ', 0 AS is_premium';

        $rows = $wpdb->get_results(
            "SELECT t.id, t.category_id, t.title, t.slug, t.prefix, t.status, t.sticky,
                    t.locked{$premium_select}, t.views, t.reply_count, t.last_replied,
                    u.display_name AS author_name, u.ID AS author_id,
                    lu.display_name AS last_user_name
             FROM {$topics_table} t
             LEFT JOIN {$users_table} u  ON u.ID  = t.user_id
             LEFT JOIN {$users_table} lu ON lu.ID = t.last_user_id
             {$where} {$order} {$limit_sql}"
        );

        if ( $wpdb->last_error ) {
            wp_send_json_error( [ 'message' => 'Database error: ' . $wpdb->last_error ], 500 );
        }

        $total = (int) $wpdb->get_var(
            $cat_id
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$topics_table} WHERE category_id = %d", $cat_id )
                : "SELECT COUNT(*) FROM {$topics_table}"
        );

        $payload = [
            'topics'      => array_map( [ $this, 'esc_topic_row' ], $rows ?: [] ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ];

        wp_cache_set( $cache_key, $payload, self::CACHE_GROUP, self::CACHE_TOPIC_TTL );
        wp_send_json_success( $payload );
    }

    // ── Read: Posts inside a topic ────────────────────────────────────────────

    public function ajax_get_posts(): void {
        check_ajax_referer( self::NONCE_POSTS, 'nonce' );

        global $wpdb;

        $topic_id = absint( $_POST['topic_id'] ?? 0 );
        if ( ! $topic_id ) {
            wp_send_json_error( [ 'message' => __( 'Topic ID required.', 'autoforum' ) ] );
        }

        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page = min( 50, max( 5, absint( get_option( 'af_settings', [] )['posts_per_page'] ?? 15 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $posts_table  = DB_Installer::posts_table();
        $topics_table = DB_Installer::topics_table();
        $thanks_table = DB_Installer::thanks_table();
        $users_table  = $wpdb->users;
        $usermeta     = $wpdb->usermeta;

        $current_uid = get_current_user_id(); // 0 = not logged in.

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.topic_id, p.user_id, p.content_parsed AS content,
                        p.has_attachment, p.thanks_count, p.is_op,
                        p.created_at, p.updated_at,
                        u.display_name AS author_name,
                        um_pc.meta_value AS author_post_count,
                        um_rep.meta_value AS author_reputation,
                        um_loc.meta_value AS author_location,
                        CASE WHEN th.post_id IS NOT NULL THEN 1 ELSE 0 END AS viewer_thanked
                 FROM {$posts_table} p
                 LEFT JOIN {$users_table} u   ON u.ID = p.user_id
                 LEFT JOIN {$usermeta} um_pc  ON um_pc.user_id  = p.user_id AND um_pc.meta_key  = 'af_post_count'
                 LEFT JOIN {$usermeta} um_rep ON um_rep.user_id = p.user_id AND um_rep.meta_key = 'af_reputation'
                 LEFT JOIN {$usermeta} um_loc ON um_loc.user_id = p.user_id AND um_loc.meta_key = 'af_location'
                 LEFT JOIN {$thanks_table} th ON th.post_id = p.id AND th.user_id = %d
                 WHERE p.topic_id = %d
                 ORDER BY p.is_op DESC, p.created_at ASC
                 LIMIT %d OFFSET %d",
                $current_uid,
                $topic_id,
                $per_page,
                $offset
            )
        );

        if ( $wpdb->last_error ) {
            wp_send_json_error( [ 'message' => 'Database error: ' . $wpdb->last_error ], 500 );
        }

        // Fetch topic meta (locked / prefix / title / is_premium) to return alongside posts.
        $has_premium_col = $this->column_exists( $topics_table, 'is_premium' );
        $premium_select  = $has_premium_col ? 'is_premium' : '0 AS is_premium';
        $topic = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, prefix, locked, {$premium_select}, status, reply_count FROM {$topics_table} WHERE id = %d",
                $topic_id
            )
        );

        if ( ! $topic ) {
            wp_send_json_error( [ 'message' => __( 'Topic not found.', 'autoforum' ) ] );
        }

        // ── Premium gate: if this is a premium topic, verify the user has an active license ──
        if ( $topic->is_premium && ! autoforum()->get_license_manager()->user_has_active_license( $current_uid ) ) {
            wp_send_json_error(
                [ 'message' => __( 'This is a premium thread. An active license is required to view it.', 'autoforum' ), 'code' => 'premium_required' ],
                403
            );
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$posts_table} WHERE topic_id = %d", $topic_id )
        );

        // ── Fetch attachments for all returned posts in a single query ──────────
        $attachments_map = [];
        if ( ! empty( $rows ) ) {
            $att_table    = DB_Installer::attachments_table();
            $post_ids     = array_map( fn( $r ) => (int) $r->id, $rows );
            $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
            $att_rows     = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, post_id, file_name, file_path, mime_type, file_size
                     FROM {$att_table}
                     WHERE post_id IN ({$placeholders})
                     ORDER BY id ASC",
                    ...$post_ids
                )
            );
            foreach ( $att_rows ?: [] as $a ) {
                $attachments_map[ (int) $a->post_id ][] = [
                    'id'        => (int) $a->id,
                    'url'       => esc_url_raw( $a->file_path ),
                    'filename'  => esc_html( $a->file_name ),
                    'mime_type' => esc_attr( $a->mime_type ),
                    'file_size' => (int) $a->file_size,
                ];
            }
        }

        $esc_posts = array_map( function ( $r ) use ( $attachments_map ) {
            $row               = $this->esc_post_row( $r );
            $row['attachments'] = $attachments_map[ $row['id'] ] ?? [];
            return $row;
        }, $rows ?: [] );

        wp_send_json_success( [
            'topic'       => $this->esc_topic_detail( $topic ),
            'posts'       => $esc_posts,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    // ── Read: Search ──────────────────────────────────────────────────────────

    public function ajax_search(): void {
        check_ajax_referer( self::NONCE_SEARCH, 'nonce' );

        global $wpdb;

        $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        if ( strlen( $q ) < 3 ) {
            wp_send_json_error( [ 'message' => __( 'Search term must be at least 3 characters.', 'autoforum' ) ] );
        }

        $topics_table = DB_Installer::topics_table();
        $posts_table  = DB_Installer::posts_table();
        $users_table  = $wpdb->users;

        $like = '%' . $wpdb->esc_like( $q ) . '%';

        $topics = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.title, t.slug, t.reply_count, t.last_replied,
                        u.display_name AS author_name
                 FROM {$topics_table} t
                 LEFT JOIN {$users_table} u ON u.ID = t.user_id
                 WHERE t.title LIKE %s
                 ORDER BY t.last_replied DESC LIMIT 10",
                $like
            )
        );

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.topic_id, SUBSTRING(p.content_parsed, 1, 200) AS excerpt,
                        p.created_at, u.display_name AS author_name,
                        t.title AS topic_title, t.slug AS topic_slug
                 FROM {$posts_table} p
                 LEFT JOIN {$users_table} u ON u.ID = p.user_id
                 LEFT JOIN {$topics_table} t ON t.id = p.topic_id
                 WHERE p.content LIKE %s
                 ORDER BY p.created_at DESC LIMIT 10",
                $like
            )
        );

        wp_send_json_success( [
            'topics' => array_map( [ $this, 'esc_topic_row' ], $topics ?: [] ),
            'posts'  => array_map( [ $this, 'esc_post_row' ], $posts ?: [] ),
            'query'  => esc_html( $q ),
        ] );
    }

    // ── Write: View counter ───────────────────────────────────────────────────

    public function ajax_view_topic(): void {
        check_ajax_referer( self::NONCE_VIEW, 'nonce' );

        global $wpdb;

        $topic_id = absint( $_POST['topic_id'] ?? 0 );
        if ( ! $topic_id ) {
            wp_send_json_error();
        }

        // Use a transient to deduplicate views per IP per topic per hour.
        $ip    = $this->get_client_ip();
        $t_key = 'af_view_' . $topic_id . '_' . md5( $ip );
        if ( false === get_transient( $t_key ) ) {
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . DB_Installer::topics_table() . ' SET views = views + 1 WHERE id = %d',
                    $topic_id
                )
            );
            set_transient( $t_key, 1, HOUR_IN_SECONDS );
        }

        wp_send_json_success();
    }

    // ── Write: Create topic ───────────────────────────────────────────────────

    public function ajax_create_topic(): void {
        check_ajax_referer( self::NONCE_TOPIC, 'nonce' );
        $this->require_login();

        global $wpdb;

        $user_id     = get_current_user_id();
        $cat_id      = absint( $_POST['category_id'] ?? 0 );
        $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $prefix      = sanitize_key( $_POST['prefix'] ?? '' );
        $raw_content = wp_unslash( $_POST['content'] ?? '' );
        $content     = wp_kses( $this->parse_bbcode( $raw_content ), self::ALLOWED_TAGS );
        $locked      = ! empty( $_POST['locked'] ) && current_user_can( 'edit_others_posts' ) ? 1 : 0;
        $is_premium  = ! empty( $_POST['lock_content'] ) ? 1 : 0;

        if ( ! $cat_id || ! $title || ! $content ) {
            wp_send_json_error( [ 'message' => __( 'Category, title, and content are all required.', 'autoforum' ) ] );
        }
        if ( strlen( $title ) > 200 ) {
            wp_send_json_error( [ 'message' => __( 'Title must be 200 characters or fewer.', 'autoforum' ) ] );
        }

        // Verify category exists.
        $cat_exists = $wpdb->get_var(
            $wpdb->prepare( 'SELECT id FROM ' . DB_Installer::categories_table() . ' WHERE id = %d', $cat_id )
        );
        if ( ! $cat_exists ) {
            wp_send_json_error( [ 'message' => __( 'Invalid category.', 'autoforum' ) ] );
        }

        $slug = $this->unique_slug( sanitize_title( $title ), DB_Installer::topics_table() );

        $topics_table = DB_Installer::topics_table();
        $now          = current_time( 'mysql', true );

        $wpdb->insert(
            $topics_table,
            [
                'category_id'  => $cat_id,
                'user_id'      => $user_id,
                'title'        => $title,
                'slug'         => $slug,
                'prefix'       => $prefix,
                'status'       => 'open',
                'locked'       => $locked,
                'is_premium'   => $is_premium,
                'last_user_id' => $user_id,
                'last_replied' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ]
        );

        $topic_id = $wpdb->insert_id;
        if ( ! $topic_id ) {
            wp_send_json_error( [ 'message' => __( 'Could not save topic. Please try again.', 'autoforum' ) ] );
        }

        // Insert the opening post.
        $post_id = $this->insert_post( $topic_id, $user_id, $raw_content, $content, true );

        // Update topic's last_post_id.
        $wpdb->update( $topics_table, [ 'last_post_id' => $post_id ], [ 'id' => $topic_id ], [ '%d' ], [ '%d' ] );

        // Increment category counters.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB_Installer::categories_table() . ' SET topic_count = topic_count + 1, post_count = post_count + 1 WHERE id = %d',
                $cat_id
            )
        );

        // Bump user's post count.
        $this->increment_user_post_count( $user_id );

        do_action( 'af_topic_created', $topic_id, $user_id );
        $this->flush_topic_cache( $cat_id );

        wp_send_json_success( [
            'message'  => __( 'Topic created successfully.', 'autoforum' ),
            'topic_id' => $topic_id,
            'slug'     => $slug,
        ] );
    }

    // ── Write: Create reply post ──────────────────────────────────────────────

    public function ajax_create_post(): void {
        check_ajax_referer( self::NONCE_POST, 'nonce' );
        $this->require_login();

        global $wpdb;

        $user_id     = get_current_user_id();
        $topic_id    = absint( $_POST['topic_id'] ?? 0 );
        $raw_content = wp_unslash( $_POST['content'] ?? '' );
        $content     = wp_kses( $this->parse_bbcode( $raw_content ), self::ALLOWED_TAGS );

        if ( ! $topic_id || ! $content ) {
            wp_send_json_error( [ 'message' => __( 'Topic and content are required.', 'autoforum' ) ] );
        }

        $topics_table = DB_Installer::topics_table();

        // Topic must exist and be unlocked.
        $topic = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, locked, category_id FROM {$topics_table} WHERE id = %d", $topic_id )
        );
        if ( ! $topic ) {
            wp_send_json_error( [ 'message' => __( 'Topic not found.', 'autoforum' ) ] );
        }
        if ( $topic->locked && ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'This topic is locked.', 'autoforum' ) ] );
        }

        $post_id = $this->insert_post( $topic_id, $user_id, $raw_content, $content, false );
        $now     = current_time( 'mysql', true );

        // Update topic counters.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$topics_table} SET reply_count = reply_count + 1, last_post_id = %d, last_user_id = %d, last_replied = %s, updated_at = %s WHERE id = %d",
                $post_id, $user_id, $now, $now, $topic_id
            )
        );

        // Update category post_count.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB_Installer::categories_table() . ' SET post_count = post_count + 1 WHERE id = %d',
                $topic->category_id
            )
        );

        $this->increment_user_post_count( $user_id );

        do_action( 'af_post_created', $post_id, $topic_id, $user_id );
        $this->flush_topic_cache( (int) $topic->category_id );

        wp_send_json_success( [
            'message' => __( 'Reply posted.', 'autoforum' ),
            'post_id' => $post_id,
        ] );
    }

    // ── Write: Thank / unlock ─────────────────────────────────────────────────

    public function ajax_thank_post(): void {
        check_ajax_referer( self::NONCE_THANK, 'nonce' );
        $this->require_login();

        global $wpdb;

        $user_id = get_current_user_id();
        $post_id = absint( $_POST['post_id'] ?? 0 );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post.', 'autoforum' ) ] );
        }

        $posts_table  = DB_Installer::posts_table();
        $thanks_table = DB_Installer::thanks_table();

        // Cannot thank your own post.
        $post_owner = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT user_id FROM {$posts_table} WHERE id = %d", $post_id )
        );
        if ( $post_owner === $user_id ) {
            wp_send_json_error( [ 'message' => __( 'You cannot thank your own post.', 'autoforum' ) ] );
        }

        // Check if already thanked.
        $already = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$thanks_table} WHERE user_id = %d AND post_id = %d",
                $user_id, $post_id
            )
        );

        if ( $already ) {
            wp_send_json_success( [
                'thanked'  => true,
                'unlocked' => true,
                'message'  => __( 'Already thanked.', 'autoforum' ),
            ] );
        }

        // Insert thank record.
        $wpdb->insert(
            $thanks_table,
            [ 'user_id' => $user_id, 'post_id' => $post_id, 'created_at' => current_time( 'mysql', true ) ],
            [ '%d', '%d', '%s' ]
        );

        // Increment thanks_count on the post.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$posts_table} SET thanks_count = thanks_count + 1 WHERE id = %d",
                $post_id
            )
        );

        // Reward post author with reputation.
        if ( $post_owner ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1
                     WHERE user_id = %d AND meta_key = 'af_reputation'",
                    $post_owner
                )
            );
        }

        do_action( 'af_post_thanked', $post_id, $user_id, $post_owner );

        wp_send_json_success( [
            'thanked'  => true,
            'unlocked' => true,
            'message'  => __( 'Thanks added — content unlocked!', 'autoforum' ),
        ] );
    }

    // ── Write: Edit post (author within grace period, or mod/admin) ──────────

    public function ajax_edit_post(): void {
        check_ajax_referer( 'af_edit_post', 'nonce' );
        $this->require_login();

        global $wpdb;

        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $raw_content = wp_unslash( $_POST['content'] ?? '' );
        $posts_table = DB_Installer::posts_table();

        if ( ! $post_id || empty( trim( $raw_content ) ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'autoforum' ) ], 400 );
        }

        $post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, is_op, topic_id, created_at FROM {$posts_table} WHERE id = %d",
                $post_id
            )
        );

        if ( ! $post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'autoforum' ) ], 404 );
        }

        $is_author = (int) $post->user_id === get_current_user_id();
        $is_mod    = current_user_can( 'edit_others_posts' );
        $grace     = strtotime( $post->created_at ) + 10 * MINUTE_IN_SECONDS > time();

        if ( ! $is_mod && ! ( $is_author && $grace ) ) {
            wp_send_json_error( [ 'message' => __( 'You cannot edit this post.', 'autoforum' ) ], 403 );
        }

        // Sanitise and store — mirrors insert_post(): raw in content, parsed in content_parsed.
        $content_html = wp_kses( $this->parse_bbcode( $raw_content ), self::ALLOWED_TAGS );

        $updated = $wpdb->update(
            $posts_table,
            [
                'content'        => $raw_content,
                'content_parsed' => $content_html,
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'id' => $post_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => __( 'Could not save the edit.', 'autoforum' ) ], 500 );
        }

        do_action( 'af_post_edited', $post_id, $post->topic_id );

        wp_send_json_success( [
            'message' => __( 'Post updated.', 'autoforum' ),
            'content' => $content_html,
        ] );
    }

    // ── Write: Delete topic (mod/admin only) ──────────────────────────────────

    public function ajax_delete_topic(): void {
        check_ajax_referer( 'af_delete_topic', 'nonce' );
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'autoforum' ) ], 403 );
        }

        global $wpdb;

        $topic_id    = absint( $_POST['topic_id'] ?? 0 );
        $topics_table = DB_Installer::topics_table();
        $posts_table  = DB_Installer::posts_table();

        $topic = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, category_id, reply_count FROM {$topics_table} WHERE id = %d", $topic_id )
        );
        if ( ! $topic ) {
            wp_send_json_error( [ 'message' => __( 'Topic not found.', 'autoforum' ) ] );
        }

        $wpdb->delete( $posts_table,  [ 'topic_id' => $topic_id ], [ '%d' ] );
        $wpdb->delete( $topics_table, [ 'id'       => $topic_id ], [ '%d' ] );

        // Decrement category counters.
        $post_delta = $topic->reply_count + 1; // Include OP post.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB_Installer::categories_table() .
                ' SET topic_count = GREATEST(0, topic_count - 1), post_count = GREATEST(0, post_count - %d) WHERE id = %d',
                $post_delta,
                $topic->category_id
            )
        );

        do_action( 'af_topic_deleted', $topic_id );

        wp_send_json_success( [ 'message' => __( 'Topic deleted.', 'autoforum' ) ] );
    }

    // ── Write: Delete post (mod/admin only or own post within 10 min) ─────────

    public function ajax_delete_post(): void {
        check_ajax_referer( 'af_delete_post', 'nonce' );
        $this->require_login();

        global $wpdb;

        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $posts_table = DB_Installer::posts_table();

        $post = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, user_id, is_op, topic_id, created_at FROM {$posts_table} WHERE id = %d", $post_id )
        );
        if ( ! $post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'autoforum' ) ] );
        }
        if ( $post->is_op ) {
            wp_send_json_error( [ 'message' => __( 'Delete the topic instead of the opening post.', 'autoforum' ) ] );
        }

        $is_author = (int) $post->user_id === get_current_user_id();
        $is_mod    = current_user_can( 'edit_others_posts' );
        $grace     = strtotime( $post->created_at ) + 10 * MINUTE_IN_SECONDS > time();

        if ( ! $is_mod && ! ( $is_author && $grace ) ) {
            wp_send_json_error( [ 'message' => __( 'You cannot delete this post.', 'autoforum' ) ], 403 );
        }

        $wpdb->delete( $posts_table, [ 'id' => $post_id ], [ '%d' ] );

        // Decrement reply_count on topic.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB_Installer::topics_table() . ' SET reply_count = GREATEST(0, reply_count - 1) WHERE id = %d',
                $post->topic_id
            )
        );

        do_action( 'af_post_deleted', $post_id, $post->topic_id );

        wp_send_json_success( [ 'message' => __( 'Post deleted.', 'autoforum' ) ] );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Convert a small subset of BBCode to safe HTML before wp_kses sanitisation.
     * Handles: [quote=Author]...[/quote]
     */
    private function parse_bbcode( string $text ): string {
        // [quote=Author]...[/quote]  →  <blockquote class="af-quote"><cite>Author</cite>...</blockquote>
        $text = preg_replace_callback(
            '/\[quote=([^\]]{1,80})\](.*?)\[\/quote\]/si',
            static function ( array $m ): string {
                $author = esc_html( trim( $m[1] ) );
                $body   = nl2br( esc_html( trim( $m[2] ) ) );
                return '<blockquote class="af-quote"><cite>' . $author . '</cite>' . $body . '</blockquote>';
            },
            $text
        );
        // Bare [quote]...[/quote] (no author)
        $text = preg_replace_callback(
            '/\[quote\](.*?)\[\/quote\]/si',
            static function ( array $m ): string {
                $body = nl2br( esc_html( trim( $m[1] ) ) );
                return '<blockquote class="af-quote">' . $body . '</blockquote>';
            },
            $text
        );
        return $text;
    }

    /**
     * Check whether a column exists in a given table.
     * Used to safely degrade queries during DB migrations.
     */
    private function column_exists( string $table, string $column ): bool {
        global $wpdb;
        $cache_key = 'af_col_' . md5( $table . '.' . $column );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return (bool) $cached;
        }
        $result = $wpdb->get_var(
            $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $table, $column )
        );
        $exists = (bool) $result;
        wp_cache_set( $cache_key, $exists, self::CACHE_GROUP, 300 );
        return $exists;
    }

    private function insert_post( int $topic_id, int $user_id, string $raw, string $parsed, bool $is_op ): int {
        global $wpdb;

        $wpdb->insert(
            DB_Installer::posts_table(),
            [
                'topic_id'       => $topic_id,
                'user_id'        => $user_id,
                'content'        => $raw,
                'content_parsed' => $parsed,
                'is_op'          => $is_op ? 1 : 0,
                'ip_address'     => $this->get_client_ip(),
                'created_at'     => current_time( 'mysql', true ),
                'updated_at'     => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    private function increment_user_post_count( int $user_id ): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1
                 WHERE user_id = %d AND meta_key = 'af_post_count'",
                $user_id
            )
        );
    }

    private function unique_slug( string $base, string $table ): string {
        global $wpdb;
        $slug    = $base;
        $counter = 1;
        while (
            $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) )
        ) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function require_login(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'autoforum' ), 'code' => 'not_logged_in' ], 401 );
        }
    }

    private function get_client_ip(): string {
        return Utils::get_client_ip();
    }

    /**
     * Bust all topic-list cache keys for a given category + the global list.
     * Called after any write that changes the topic index.
     */
    private function flush_topic_cache( int $cat_id = 0 ): void {
        // Flush global topic lists (all sort variants, pages 1–5 should cover 99% of traffic).
        $sorts = array_keys( self::SORT_MAP );
        $pages = range( 1, 5 );
        foreach ( $sorts as $sort ) {
            foreach ( $pages as $page ) {
                wp_cache_delete( 'af_topics_0_' . $sort . '_p' . $page, self::CACHE_GROUP );
                if ( $cat_id ) {
                    wp_cache_delete( 'af_topics_' . $cat_id . '_' . $sort . '_p' . $page, self::CACHE_GROUP );
                }
            }
        }
        // Bust category counts cache (topic_count / post_count just changed).
        wp_cache_delete( 'af_all_categories', self::CACHE_GROUP );
    }

    // ── Write: Secure File Upload ─────────────────────────────────────────────

    /**
     * Handles file uploads from the Create Topic / Reply forms.
     *
     * Security chain:
     *  1. Nonce + login check.
     *  2. current_user_can('read') — all registered forum members can upload.
     *  3. MIME type is detected server-side via wp_check_filetype_and_ext().
     *  4. wp_handle_upload() applies WordPress's own security layer.
     *  5. Result is recorded in af_attachments (not wp_posts).
     */
    public function ajax_upload_attachment(): void {
        check_ajax_referer( 'af_upload_attachment', 'nonce' );
        $this->require_login();

        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to upload files.', 'autoforum' ) ], 403 );
        }

        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => __( 'No file received or upload error.', 'autoforum' ) ] );
        }

        // Allowed MIME types — server-side whitelist (client extension is ignored).
        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'pdf'          => 'application/pdf',
            'zip'          => 'application/zip',
            'bin'          => 'application/octet-stream',
            'log'          => 'text/plain',
            'csv'          => 'text/csv',
        ];

        // Validate the real MIME type (not what the client claims).
        $file_info = wp_check_filetype_and_ext(
            $_FILES['file']['tmp_name'],
            sanitize_file_name( $_FILES['file']['name'] ),
            $allowed_mimes
        );

        if ( ! $file_info['type'] ) {
            wp_send_json_error( [ 'message' => __( 'File type is not permitted.', 'autoforum' ) ] );
        }

        // Size cap (10 MB).
        $max_bytes = 10 * MB_IN_BYTES;
        if ( (int) $_FILES['file']['size'] > $max_bytes ) {
            wp_send_json_error( [ 'message' => __( 'File exceeds the 10 MB size limit.', 'autoforum' ) ] );
        }

        // Let WordPress do the heavy lifting (moves file, sanitizes filename, etc.).
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload( $_FILES['file'], [ 'test_form' => false, 'mimes' => $allowed_mimes ] );

        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( [ 'message' => esc_html( $upload['error'] ) ] );
        }

        // Record the attachment in our custom table.
        global $wpdb;
        $user_id  = get_current_user_id();
        $post_id  = absint( $_POST['post_id'] ?? 0 ); // 0 = not yet attached to a post.
        $filename = sanitize_file_name( basename( $upload['file'] ) );

        $wpdb->insert(
            DB_Installer::attachments_table(),
            [
                'post_id'        => $post_id,
                'user_id'        => $user_id,
                'file_name'      => $filename,
                'file_path'      => $upload['url'],
                'file_size'      => (int) $_FILES['file']['size'],
                'mime_type'      => $upload['type'],
                'download_count' => 0,
                'created_at'     => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s' ]
        );

        $attachment_id = (int) $wpdb->insert_id;

        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => __( 'Could not record attachment.', 'autoforum' ) ] );
        }

        do_action( 'af_attachment_uploaded', $attachment_id, $user_id );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => esc_url( $upload['url'] ),
            'filename'      => esc_html( $filename ),
            'size'          => (int) $_FILES['file']['size'],
            'mime'          => esc_attr( $upload['type'] ),
        ] );
    }

    // ── Escape helpers (safe output) ──────────────────────────────────────────

    private function esc_category( object $r ): array {
        return [
            'id'          => (int) $r->id,
            'name'        => esc_html( $r->name ),
            'slug'        => esc_attr( $r->slug ),
            'description' => esc_html( $r->description ),
            'icon'        => esc_attr( $r->icon ),
            'color'       => esc_attr( $r->color ),
            'parent_id'   => (int) $r->parent_id,
            'sort_order'  => (int) $r->sort_order,
            'topic_count' => (int) $r->topic_count,
            'post_count'  => (int) $r->post_count,
        ];
    }

    private function esc_topic_row( object $r ): array {
        return [
            'id'            => (int) $r->id,
            'category_id'   => (int) ( $r->category_id ?? 0 ),
            'title'         => esc_html( $r->title ),
            'slug'          => esc_attr( $r->slug ?? '' ),
            'prefix'        => esc_html( $r->prefix ?? '' ),
            'status'        => esc_attr( $r->status ?? 'open' ),
            'sticky'        => (bool) ( $r->sticky ?? false ),
            'locked'        => (bool) ( $r->locked ?? false ),
            'is_premium'    => (bool) ( $r->is_premium ?? false ),
            'views'         => (int) ( $r->views ?? 0 ),
            'reply_count'   => (int) ( $r->reply_count ?? 0 ),
            'last_replied'  => esc_attr( $r->last_replied ?? '' ),
            'author_name'   => esc_html( $r->author_name ?? '' ),
            'author_id'     => (int) ( $r->author_id ?? 0 ),
            'last_user_name' => esc_html( $r->last_user_name ?? '' ),
        ];
    }

    // ── Heartbeat: mark user as online ────────────────────────────────────────

    public function ajax_heartbeat(): void {
        check_ajax_referer( 'af_heartbeat', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Not logged in.' ], 401 );
        }

        update_user_meta( $user_id, '_af_last_active', time() );
        wp_send_json_success();
    }

    // ── Read: Home page stats (members, posts, threads, online, top contributors, latest posts) ──

    public function ajax_get_home_stats(): void {
        check_ajax_referer( self::NONCE_HOME_STATS, 'nonce' );

        global $wpdb;

        $topics_table = DB_Installer::topics_table();
        $posts_table  = DB_Installer::posts_table();

        // ── Totals ─────────────────────────────────────────────────────────────
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb / DB_Installer, no user input.
        $member_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
        $post_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table}" );
        $thread_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$topics_table}" );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // ── Online (users active in last 15 min via WP sessions) ──────────────
        $online = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
                  WHERE meta_key = '_af_last_active'
                    AND CAST(meta_value AS UNSIGNED) > %d",
                time() - 15 * MINUTE_IN_SECONDS
            )
        );

        // ── Top Contributors (by post count meta) ─────────────────────────────
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input; meta_key literals are hardcoded.
        $top_rows = $wpdb->get_results(
            "SELECT u.ID as id, u.display_name as name, u.user_login as username,
                    COALESCE(CAST(pm.meta_value AS UNSIGNED), 0) as post_count,
                    COALESCE(CAST(rep.meta_value AS SIGNED), 0) as reputation
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} pm  ON pm.user_id  = u.ID AND pm.meta_key  = 'af_post_count'
             LEFT JOIN {$wpdb->usermeta} rep ON rep.user_id = u.ID AND rep.meta_key = 'af_reputation'
             ORDER BY COALESCE(CAST(pm.meta_value AS UNSIGNED), 0) DESC
             LIMIT 5"
        ) ?: [];

        $top_contributors = array_map( function ( $u, $rank ) {
            return [
                'rank'     => $rank + 1,
                'id'       => (int) $u->id,
                'name'     => esc_html( $u->name ?: $u->username ),
                'username' => esc_html( $u->username ),
                'posts'    => (int) $u->post_count,
                'rep'      => (int) $u->reputation,
                'avatar'   => esc_url( get_avatar_url( (int) $u->id, [ 'size' => 40 ] ) ),
            ];
        }, $top_rows, array_keys( $top_rows ) );

        // ── Latest Posts ──────────────────────────────────────────────────────
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input; all tables from trusted sources.
        $latest_rows = $wpdb->get_results(
            "SELECT p.id, t.title, t.id as topic_id,
                    u.display_name as author_name, u.ID as author_id,
                    p.created_at
             FROM {$posts_table} p
             INNER JOIN {$topics_table} t ON t.id = p.topic_id
             LEFT JOIN  {$wpdb->users}  u ON u.ID = p.user_id
             ORDER BY p.created_at DESC
             LIMIT 5"
        ) ?: [];

        $latest_posts = array_map( function ( $r ) {
            return [
                'topic_id'    => (int) $r->topic_id,
                'title'       => esc_html( $r->title ),
                'author_name' => esc_html( $r->author_name ?: 'Unknown' ),
                'author_id'   => (int) $r->author_id,
                'time'        => human_time_diff( strtotime( $r->created_at ), time() ) . ' ago',
            ];
        }, $latest_rows );

        wp_send_json_success( [
            'members'          => $member_count,
            'posts'            => $post_count,
            'threads'          => $thread_count,
            'online'           => $online,
            'top_contributors' => $top_contributors,
            'latest_posts'     => $latest_posts,
        ] );
    }

    // ── Read: Public user profile ─────────────────────────────────────────────

    public function ajax_get_user_profile(): void {
        check_ajax_referer( self::NONCE_USER_PROFILE, 'nonce' );

        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => __( 'User ID required.', 'autoforum' ) ] );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( [ 'message' => __( 'User not found.', 'autoforum' ) ], 404 );
        }

        $post_count  = (int) get_user_meta( $user_id, 'af_post_count',  true );
        $reputation  = (int) get_user_meta( $user_id, 'af_reputation',  true );
        $location    = esc_html( get_user_meta( $user_id, 'af_location',   true ) );
        $signature   = wp_kses_post( get_user_meta( $user_id, 'af_signature', true ) );
        $joined      = wp_date( 'F Y', strtotime( $user->user_registered ) );
        $avatar      = esc_url( get_avatar_url( $user_id, [ 'size' => 120 ] ) );

        // Resolve friendly role label without exposing internal caps.
        $role = esc_html( Utils::friendly_role( $user ) );

        // Licenses — only show to the license owner or an admin/moderator.
        $viewer_id       = get_current_user_id();
        $viewer_is_admin = current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
        $licenses        = [];
        if ( $viewer_id === $user_id || $viewer_is_admin ) {
            global $wpdb;
            $lic_table = DB_Installer::licenses_table();
            $lic_rows  = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT license_key, status, expires_at FROM {$lic_table}
                     WHERE user_id = %d AND status IN ('active','suspended')
                     ORDER BY created_at DESC",
                    $user_id
                )
            );
            foreach ( $lic_rows as $l ) {
                $licenses[] = [
                    'key'        => esc_html( $l->license_key ),
                    'status'     => esc_attr( $l->status ),
                    'expires_at' => $l->expires_at
                        ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $l->expires_at ) ) )
                        : null,
                ];
            }
        }

        wp_send_json_success( [
            'id'         => $user_id,
            'username'   => esc_html( $user->user_login ),
            'name'       => esc_html( $user->display_name ),
            'role'       => $role,
            'avatar'     => $avatar,
            'joined'     => $joined,
            'postCount'  => $post_count,
            'reputation' => $reputation,
            'location'   => $location,
            'signature'  => $signature,
            'licenses'   => $licenses,
        ] );
    }

    private function esc_topic_detail( object $r ): array {
        return [
            'id'          => (int) $r->id,
            'title'       => esc_html( $r->title ),
            'prefix'      => esc_html( $r->prefix ?? '' ),
            'locked'      => (bool) $r->locked,
            'is_premium'  => (bool) ( $r->is_premium ?? false ),
            'status'      => esc_attr( $r->status ),
            'reply_count' => (int) $r->reply_count,
        ];
    }

    private function esc_post_row( object $r ): array {
        return [
            'id'               => (int) $r->id,
            'topic_id'         => (int) ( $r->topic_id ?? 0 ),
            'user_id'          => (int) ( $r->user_id ?? 0 ),
            'content'          => wp_kses( $r->content ?? '', self::ALLOWED_TAGS ),
            'excerpt'          => isset( $r->excerpt ) ? esc_html( $r->excerpt ) : null,
            'thanks_count'     => (int) ( $r->thanks_count ?? 0 ),
            'is_op'            => (bool) ( $r->is_op ?? false ),
            'viewer_thanked'   => (bool) ( $r->viewer_thanked ?? false ),
            'created_at'       => esc_attr( $r->created_at ?? '' ),
            'author_name'      => esc_html( $r->author_name ?? '' ),
            'author_post_count' => (int) ( $r->author_post_count ?? 0 ),
            'author_reputation' => (int) ( $r->author_reputation ?? 0 ),
            'author_location'  => esc_html( $r->author_location ?? '' ),
            'topic_title'      => isset( $r->topic_title ) ? esc_html( $r->topic_title ) : null,
            'topic_slug'       => isset( $r->topic_slug ) ? esc_attr( $r->topic_slug ) : null,
        ];
    }
}
