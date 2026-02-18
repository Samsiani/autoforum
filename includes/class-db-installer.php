<?php
/**
 * Database Installer — creates and upgrades all custom tables.
 *
 * Uses dbDelta() which is WordPress's safe way to CREATE or ALTER tables.
 * dbDelta() requires very specific SQL formatting — see inline comments.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class DB_Installer {

    /**
     * Run on activation and on version bump.
     * Safe to call multiple times — dbDelta only alters what changed.
     */
    public function install(): void {
        global $wpdb;

        // dbDelta lives in upgrade.php — not loaded on the front-end.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $pfx     = $wpdb->prefix . 'af_';

        // ── 1. Categories ────────────────────────────────────────────────────
        // Stores forum categories (top-level groupings).
        dbDelta( "CREATE TABLE {$pfx}categories (
            id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            name         VARCHAR(150) NOT NULL DEFAULT '',
            slug         VARCHAR(150) NOT NULL DEFAULT '',
            description  TEXT         NOT NULL,
            icon         VARCHAR(80)  NOT NULL DEFAULT '',
            color        VARCHAR(10)  NOT NULL DEFAULT '',
            parent_id    BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            sort_order   SMALLINT(5)  UNSIGNED NOT NULL DEFAULT 0,
            topic_count  BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            post_count   BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   slug (slug),
            KEY          parent_id (parent_id)
        ) $charset;" );

        // ── 2. Topics (Threads) ───────────────────────────────────────────────
        // Each row is one forum thread / topic.
        dbDelta( "CREATE TABLE {$pfx}topics (
            id             BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id    BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            user_id        BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            title          VARCHAR(250) NOT NULL DEFAULT '',
            slug           VARCHAR(250) NOT NULL DEFAULT '',
            prefix         VARCHAR(30)  NOT NULL DEFAULT '',
            status         ENUM('open','closed','hidden') NOT NULL DEFAULT 'open',
            sticky         TINYINT(1)   UNSIGNED NOT NULL DEFAULT 0,
            locked         TINYINT(1)   UNSIGNED NOT NULL DEFAULT 0,
            is_premium     TINYINT(1)   UNSIGNED NOT NULL DEFAULT 0,
            views          BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            reply_count    BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            last_post_id   BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            last_user_id   BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            last_replied   DATETIME     NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            KEY            category_id (category_id),
            KEY            user_id (user_id),
            KEY            sticky_last (sticky, last_replied),
            FULLTEXT KEY   search_title (title)
        ) $charset;" );

        // ── 3. Posts (Replies) ────────────────────────────────────────────────
        // One row per post / reply.
        dbDelta( "CREATE TABLE {$pfx}posts (
            id              BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            topic_id        BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            user_id         BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            content         LONGTEXT     NOT NULL,
            content_parsed  LONGTEXT     NOT NULL,
            has_attachment  TINYINT(1)   UNSIGNED NOT NULL DEFAULT 0,
            thanks_count    BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            is_op           TINYINT(1)   UNSIGNED NOT NULL DEFAULT 0,
            ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY             topic_id (topic_id),
            KEY             user_id (user_id),
            FULLTEXT KEY    search_content (content)
        ) $charset;" );

        // ── 4. Licenses ───────────────────────────────────────────────────────
        // One row per issued license. Linked to WooCommerce order.
        dbDelta( "CREATE TABLE {$pfx}licenses (
            id             BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id        BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            license_key    VARCHAR(64)  NOT NULL DEFAULT '',
            product_id     BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            order_id       BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            hwid           VARCHAR(64)  NOT NULL DEFAULT '',
            resets_count   SMALLINT(5)  UNSIGNED NOT NULL DEFAULT 0,
            last_reset     DATETIME     NULL,
            status         ENUM('active','suspended','expired','revoked') NOT NULL DEFAULT 'active',
            expires_at     DATETIME     NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY    (id),
            UNIQUE KEY     license_key (license_key),
            KEY            user_id (user_id),
            KEY            hwid (hwid),
            KEY            status_expires (status, expires_at)
        ) $charset;" );

        // ── 5. Thanks / Unlock tracking ───────────────────────────────────────
        // Pivot table. A row means user_id has "thanked" post_id → unlocks hidden content.
        dbDelta( "CREATE TABLE {$pfx}thanks (
            user_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            post_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, post_id),
            KEY         post_id (post_id)
        ) $charset;" );

        // ── 6. Attachments ────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$pfx}attachments (
            id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id      BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            user_id      BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            file_name    VARCHAR(255) NOT NULL DEFAULT '',
            file_path    VARCHAR(500) NOT NULL DEFAULT '',
            file_size    BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            mime_type    VARCHAR(100) NOT NULL DEFAULT '',
            download_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY          post_id (post_id),
            KEY          user_id (user_id)
        ) $charset;" );

        // ── 7. Reports ────────────────────────────────────────────────────────
        // User-submitted reports against forum posts.
        // status ENUM intentionally uses 'pending' as default so unreviewed
        // reports always surface in the moderation queue without a WHERE clause.
        dbDelta( "CREATE TABLE {$pfx}reports (
            id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id      BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            reporter_id  BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            reason       VARCHAR(255) NOT NULL DEFAULT '',
            status       ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at  DATETIME     NULL,
            resolver_id  BIGINT(20)   UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY          post_id (post_id),
            KEY          reporter_id (reporter_id),
            KEY          status (status)
        ) $charset;" );

        // Bump stored version.
        update_option( 'af_db_version', AF_DB_VERSION );
    }

    /**
     * Run on plugins_loaded to handle future version upgrades.
     */
    public function maybe_upgrade(): void {
        $installed = get_option( 'af_db_version', '0' );

        if ( version_compare( $installed, AF_DB_VERSION, '<' ) ) {
            $this->install();
        }
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function topics_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_topics';
    }

    public static function posts_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_posts';
    }

    public static function licenses_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_licenses';
    }

    public static function thanks_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_thanks';
    }

    public static function categories_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_categories';
    }

    public static function attachments_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_attachments';
    }

    public static function reports_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'af_reports';
    }
}
