<?php
/**
 * Shortcode — Renders the AutoForum SPA into any page/post.
 *
 * Usage: [auto_forum]
 *
 * The shortcode:
 *  1. Signals to Assets that it should enqueue front-end scripts/styles.
 *  2. Captures `templates/forum.php` via output buffering.
 *  3. Returns the buffered HTML to WordPress (never echo directly).
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Shortcode {

    /** Flag so Assets knows the shortcode is active on this request. */
    public static bool $is_active = false;

    public function register_hooks(): void {
        add_shortcode( 'auto_forum', [ $this, 'render' ] );
    }

    /**
     * Shortcode callback.
     *
     * @param array|string $atts Shortcode attributes (none used currently).
     * @return string           HTML to insert in place of [auto_forum].
     */
    public function render( $atts ): string {
        self::$is_active = true;

        // Enqueue assets now (late-enqueue still works if we're in `the_content`).
        do_action( 'af_enqueue_forum_assets' );

        $template = AF_PLUGIN_DIR . 'templates/forum.php';

        if ( ! file_exists( $template ) ) {
            return '<p>' . esc_html__( 'AutoForum: template file not found.', 'autoforum' ) . '</p>';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}
