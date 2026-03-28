<?php
/**
 * Translations — Editable string translations for AutoForum.
 *
 * All user-facing strings are stored here with defaults.
 * Overrides are saved in wp_options as 'af_translations'.
 * The admin Translations page lets users edit any string.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Translations {

    private const OPTION_KEY = 'af_translations';

    /** @var array Cached merged translations (defaults + overrides). */
    private static ?array $cache = null;

    /**
     * Get a translated string by key.
     *
     * @param string $key     The translation key.
     * @param string $fallback Optional fallback if key not found.
     * @return string
     */
    public static function get( string $key, string $fallback = '' ): string {
        $all = self::all();
        return $all[ $key ] ?? $fallback ?: $key;
    }

    /**
     * Get all translations (defaults merged with saved overrides).
     */
    public static function all(): array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }
        $defaults  = self::defaults();
        $overrides = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $overrides ) ) {
            $overrides = [];
        }
        // Only override non-empty values.
        $merged = $defaults;
        foreach ( $overrides as $k => $v ) {
            if ( '' !== trim( $v ) ) {
                $merged[ $k ] = $v;
            }
        }
        self::$cache = $merged;
        return self::$cache;
    }

    /**
     * Save translation overrides.
     */
    public static function save( array $overrides ): void {
        $defaults = self::defaults();
        $clean    = [];
        foreach ( $overrides as $k => $v ) {
            if ( ! isset( $defaults[ $k ] ) ) {
                continue; // Ignore unknown keys.
            }
            $v = sanitize_text_field( wp_unslash( $v ) );
            // Only save if different from default.
            if ( $v !== $defaults[ $k ] ) {
                $clean[ $k ] = $v;
            }
        }
        update_option( self::OPTION_KEY, $clean, false );
        self::$cache = null; // Clear cache.
    }

    /**
     * Get translation groups for the admin UI.
     * Each group has a label and an array of keys.
     */
    public static function groups(): array {
        return [
            'general' => [
                'label' => 'General',
                'keys'  => [
                    'site_name', 'home', 'sign_in', 'register', 'log_out', 'loading',
                    'go_home', 'save_changes', 'cancel', 'delete', 'edit', 'search',
                ],
            ],
            'auth' => [
                'label' => 'Authentication',
                'keys'  => [
                    'sign_in_required', 'please_sign_in', 'username', 'password',
                    'email', 'remember_me', 'forgot_password', 'create_account',
                    'username_password_required', 'signing_in', 'welcome_back',
                    'login_failed', 'all_fields_required', 'password_min_8',
                    'creating_account', 'account_created_welcome', 'registration_failed',
                    'signed_out', 'login_required_page',
                ],
            ],
            'password_strength' => [
                'label' => 'Password Strength',
                'keys'  => [
                    'pw_weak', 'pw_fair', 'pw_good', 'pw_strong', 'pw_perfect',
                ],
            ],
            'navigation' => [
                'label' => 'Navigation',
                'keys'  => [
                    'nav_home', 'nav_ecu_tuning', 'nav_software', 'nav_support',
                    'new_topic', 'dashboard', 'edit_profile', 'my_licenses',
                ],
            ],
            'forum_home' => [
                'label' => 'Forum Home',
                'keys'  => [
                    'forum_categories', 'threads', 'posts', 'forum_stats',
                    'members', 'online', 'top_contributors', 'rep',
                    'no_data', 'latest_posts', 'no_recent_posts',
                    'join_community', 'join_community_desc',
                ],
            ],
            'thread_list' => [
                'label' => 'Thread List',
                'keys'  => [
                    'could_not_load_threads', 'unexpected_error', 'no_topics_yet',
                    'be_first_to_post', 'latest_activity', 'newest_first',
                    'most_active', 'most_viewed', 'thread', 'last_reply',
                    'replies', 'views', 'pinned', 'locked', 'premium',
                    'prev', 'next', 'sign_in_to_post',
                ],
            ],
            'thread_view' => [
                'label' => 'Thread View',
                'keys'  => [
                    'could_not_load_thread', 'premium_thread', 'premium_thread_desc',
                    'premium_unlock_desc', 'view_my_licenses', 'sign_in_to_reply',
                    'post_a_reply', 'write_reply_placeholder', 'file_upload_hint',
                    'post_reply', 'sign_in_to_like', 'unlocking', 'premium_unlocked',
                    'could_not_unlock', 'unlock_premium', 'sign_in_to_quote',
                    'reply_area_unavailable', 'quoting_author', 'sign_in_to_report',
                    'report_submitted', 'could_not_report', 'delete_post_confirm',
                    'post_deleted', 'could_not_delete_post', 'post_too_short',
                    'post_updated', 'could_not_save_edit', 'subscribed',
                    'link_copied', 'quote',
                ],
            ],
            'editor' => [
                'label' => 'Text Editor',
                'keys'  => [
                    'bold', 'italic', 'underline', 'heading', 'code_block',
                    'quote_block', 'ordered_list', 'unordered_list', 'link', 'image',
                    'bold_text', 'italic_text', 'underlined_text', 'heading_text',
                    'code_here', 'quoted_text', 'list_item', 'enter_url', 'enter_image_url',
                ],
            ],
            'create_topic' => [
                'label' => 'Create Topic',
                'keys'  => [
                    'must_sign_in_to_create', 'create_new_topic', 'category',
                    'select_category', 'thread_prefix', 'none', 'title',
                    'title_placeholder', 'content', 'content_placeholder',
                    'tags', 'tags_placeholder', 'tags_hint', 'attachments',
                    'drag_drop_files', 'lock_content_label', 'lock_content_desc',
                    'save_draft', 'post_topic', 'posting_tips_title',
                    'tip_clear_title', 'tip_vehicle_info', 'tip_software_version',
                    'tip_share_logs', 'tip_search_first', 'tip_be_respectful',
                    'forum_rules_title', 'forum_rules_desc', 'read_full_rules',
                    'draft_saved', 'select_category_error', 'enter_title_error',
                    'title_too_short', 'enter_content_error', 'content_too_short',
                    'posting', 'uploading_files', 'files_failed_upload',
                    'topic_posted', 'topic_post_failed', 'max_attachments',
                    'file_type_not_allowed', 'file_too_large',
                ],
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'keys'  => [
                    'overview', 'total_posts', 'reputation', 'active_licenses',
                    'account_summary', 'member_since', 'posts_contributed',
                    'reputation_earned', 'active_licenses_count',
                    'my_posts', 'posts_made', 'browse_forum',
                    'no_posts_yet', 'start_discussion',
                    'profile_settings', 'username_no_change', 'display_name',
                    'email_address', 'location', 'location_placeholder',
                    'about_me', 'about_me_placeholder', 'forum_signature',
                    'signature_placeholder', 'profile_saved',
                    'could_not_save_settings',
                ],
            ],
            'security' => [
                'label' => 'Security',
                'keys'  => [
                    'security', 'change_password', 'current_password',
                    'new_password', 'min_8_chars', 'confirm_password',
                    'repeat_password', 'update_password', 'password_updated',
                    'active_sessions', 'current_session',
                ],
            ],
            'licenses' => [
                'label' => 'Licenses',
                'keys'  => [
                    'easytuner_pro_license', 'active', 'inactive',
                    'user_id', 'device', 'device_activated', 'device_not_activated',
                    'expires', 'refresh_status', 'disconnect',
                    'connect_et_account', 'connect_et_desc',
                    'et_email', 'et_email_placeholder', 'et_password',
                    'et_password_placeholder', 'connect_account',
                    'please_fill_both', 'connecting', 'et_connected',
                    'connection_failed', 'et_disconnected', 'could_not_disconnect',
                    'checking', 'license_refreshed', 'could_not_check_license',
                    'hwid_reset_success', 'hwid_reset_cooldown', 'could_not_reset_hwid',
                ],
            ],
            'user_profile' => [
                'label' => 'User Profile',
                'keys'  => [
                    'user_not_found', 'user_not_found_desc', 'member_since_label',
                    'signature_label', 'licenses_label', 'key', 'status', 'never',
                ],
            ],
            'account_update' => [
                'label' => 'Account Update Modal',
                'keys'  => [
                    'update_your_account', 'update_account_desc',
                    'account_updated', 'could_not_save',
                ],
            ],
            'ticker' => [
                'label' => 'Ticker',
                'keys'  => [
                    'resume_ticker', 'pause_ticker',
                ],
            ],
            'errors' => [
                'label' => 'Error Messages',
                'keys'  => [
                    'error_generic', 'login_required', 'topic_created',
                    'reply_posted', 'thanks_unlocked',
                ],
            ],
        ];
    }

    /**
     * All default string values.
     */
    public static function defaults(): array {
        return [
            // ── General ──
            'site_name'              => 'EsyTuner Forum',
            'home'                   => 'Home',
            'sign_in'                => 'Sign In',
            'register'               => 'Register',
            'log_out'                => 'Log Out',
            'loading'                => 'Loading',
            'go_home'                => 'Go Home',
            'save_changes'           => 'Save Changes',
            'cancel'                 => 'Cancel',
            'delete'                 => 'Delete',
            'edit'                   => 'Edit',
            'search'                 => 'Search',

            // ── Auth ──
            'sign_in_required'       => 'Sign In Required',
            'please_sign_in'         => 'Please sign in to access your dashboard.',
            'username'               => 'Username',
            'password'               => 'Password',
            'email'                  => 'Email',
            'remember_me'            => 'Remember Me',
            'forgot_password'        => 'Forgot Password?',
            'create_account'         => 'Create Account',
            'username_password_required' => 'Username and password are required.',
            'signing_in'             => 'Signing in…',
            'welcome_back'           => 'Welcome back, {username}!',
            'login_failed'           => 'Login failed. Please try again.',
            'all_fields_required'    => 'All fields are required.',
            'password_min_8'         => 'Password must be at least 8 characters.',
            'creating_account'       => 'Creating account…',
            'account_created_welcome' => 'Account created! Welcome, {username}!',
            'registration_failed'    => 'Registration failed. Please try again.',
            'signed_out'             => 'You have been signed out.',
            'login_required_page'    => 'Please sign in to access that page.',

            // ── Password Strength ──
            'pw_weak'                => 'Weak',
            'pw_fair'                => 'Fair',
            'pw_good'                => 'Good',
            'pw_strong'              => 'Strong',
            'pw_perfect'             => 'Perfect',

            // ── Navigation ──
            'nav_home'               => 'Home',
            'nav_ecu_tuning'         => 'ECU Tuning',
            'nav_software'           => 'Software',
            'nav_support'            => 'Support',
            'new_topic'              => 'New Topic',
            'dashboard'              => 'Dashboard',
            'edit_profile'           => 'Edit Profile',
            'my_licenses'            => 'My Licenses',

            // ── Forum Home ──
            'forum_categories'       => 'Forum Categories',
            'threads'                => 'Threads',
            'posts'                  => 'Posts',
            'forum_stats'            => 'Forum Stats',
            'members'                => 'Members',
            'online'                 => 'Online',
            'top_contributors'       => 'Top Contributors',
            'rep'                    => 'rep',
            'no_data'                => 'No data available.',
            'latest_posts'           => 'Latest Posts',
            'no_recent_posts'        => 'No recent posts.',
            'join_community'         => 'Join the Community',
            'join_community_desc'    => 'Register free to post, share tunes and get support.',

            // ── Thread List ──
            'could_not_load_threads' => 'Could not load threads',
            'unexpected_error'       => 'An unexpected error occurred.',
            'no_topics_yet'          => 'No topics found in this category yet.',
            'be_first_to_post'       => 'Be the first to post!',
            'latest_activity'        => 'Latest Activity',
            'newest_first'           => 'Newest First',
            'most_active'            => 'Most Active',
            'most_viewed'            => 'Most Viewed',
            'thread'                 => 'Thread',
            'last_reply'             => 'Last Reply',
            'replies'                => 'Replies',
            'views'                  => 'Views',
            'pinned'                 => 'Pinned',
            'locked'                 => 'Locked',
            'premium'                => 'Premium',
            'prev'                   => 'Prev',
            'next'                   => 'Next',
            'sign_in_to_post'        => 'Please sign in to post a new topic.',

            // ── Thread View ──
            'could_not_load_thread'  => 'Could not load thread',
            'premium_thread'         => 'Premium Thread',
            'premium_thread_desc'    => 'This thread is exclusive to members with an active license.',
            'premium_unlock_desc'    => 'Unlock access to premium content, tuning files, and expert discussion.',
            'view_my_licenses'       => 'View My Licenses',
            'sign_in_to_reply'       => 'Sign in to join the discussion.',
            'post_a_reply'           => 'Post a Reply',
            'write_reply_placeholder' => 'Write your reply… Be helpful, be respectful.',
            'file_upload_hint'       => 'Max 5 files · 10MB each · jpg, png, pdf, bin, csv, log',
            'post_reply'             => 'Post Reply',
            'sign_in_to_like'        => 'Sign in to like posts.',
            'unlocking'              => 'Unlocking…',
            'premium_unlocked'       => 'Premium content unlocked!',
            'could_not_unlock'       => 'Could not unlock content. Please try again.',
            'unlock_premium'         => 'Unlock Premium Content',
            'sign_in_to_quote'       => 'Sign in to quote posts.',
            'reply_area_unavailable' => 'Reply area not available.',
            'quoting_author'         => 'Quoting {author} — write your reply below.',
            'sign_in_to_report'      => 'Sign in to report posts.',
            'report_submitted'       => 'Report submitted. Our moderators will review this post shortly.',
            'could_not_report'       => 'Could not submit report. Please try again.',
            'delete_post_confirm'    => 'Delete this post? This cannot be undone.',
            'post_deleted'           => 'Post deleted.',
            'could_not_delete_post'  => 'Could not delete post.',
            'post_too_short'         => 'Post is too short (min 10 characters).',
            'post_updated'           => 'Post updated.',
            'could_not_save_edit'    => 'Could not save edit.',
            'subscribed'             => 'Subscribed! You\'ll be notified of new replies.',
            'link_copied'            => 'Thread link copied to clipboard.',
            'quote'                  => 'Quote',

            // ── Text Editor ──
            'bold'                   => 'Bold',
            'italic'                 => 'Italic',
            'underline'              => 'Underline',
            'heading'                => 'Heading',
            'code_block'             => 'Code Block',
            'quote_block'            => 'Quote',
            'ordered_list'           => 'Ordered List',
            'unordered_list'         => 'Unordered List',
            'link'                   => 'Link',
            'image'                  => 'Image',
            'bold_text'              => 'bold text',
            'italic_text'            => 'italic text',
            'underlined_text'        => 'underlined text',
            'heading_text'           => 'Heading',
            'code_here'              => 'code here',
            'quoted_text'            => 'quoted text',
            'list_item'              => 'list item',
            'enter_url'              => 'Enter URL:',
            'enter_image_url'        => 'Enter image URL:',

            // ── Create Topic ──
            'must_sign_in_to_create' => 'You must be signed in to create a topic.',
            'create_new_topic'       => 'Create New Topic',
            'category'               => 'Category',
            'select_category'        => '— Select a category —',
            'thread_prefix'          => 'Thread Prefix',
            'none'                   => 'None',
            'title'                  => 'Title',
            'title_placeholder'      => 'Be descriptive — e.g. BMW N54 Stage 3 lean condition at 7000rpm',
            'content'                => 'Content',
            'content_placeholder'    => 'Describe your topic in detail. Include relevant details like vehicle make/model, mods, software version, and what you\'ve already tried.',
            'tags'                   => 'Tags',
            'tags_placeholder'       => 'e.g. BMW, N54, stage3, E40 — separate with commas',
            'tags_hint'              => 'Up to 5 tags, comma-separated.',
            'attachments'            => 'Attachments',
            'drag_drop_files'        => 'Drag & drop files here, or browse',
            'lock_content_label'     => 'Lock partial content for licensed users',
            'lock_content_desc'      => 'Hide part of the post behind a license gate (EsyTuner Pro required)',
            'save_draft'             => 'Save Draft',
            'post_topic'             => 'Post Topic',
            'posting_tips_title'     => 'Posting Tips',
            'tip_clear_title'        => 'Use a clear, specific title',
            'tip_vehicle_info'       => 'Include vehicle make, model & year',
            'tip_software_version'   => 'Mention software version',
            'tip_share_logs'         => 'Share logs or screenshots if relevant',
            'tip_search_first'       => 'Search before posting to avoid duplicates',
            'tip_be_respectful'      => 'Stay on topic and be respectful',
            'forum_rules_title'      => 'Forum Rules',
            'forum_rules_desc'       => 'No spam, no piracy, no offensive content. Share real-world experience and help others grow.',
            'read_full_rules'        => 'Read Full Rules',
            'draft_saved'            => 'Draft saved locally.',
            'select_category_error'  => 'Please select a category.',
            'enter_title_error'      => 'Please enter a topic title.',
            'title_too_short'        => 'Title is too short (min 10 characters).',
            'enter_content_error'    => 'Please write some content for your topic.',
            'content_too_short'      => 'Content is too short (min 30 characters).',
            'posting'                => 'Posting…',
            'uploading_files'        => 'Uploading files…',
            'files_failed_upload'    => 'Topic posted, but {count} file(s) failed to upload: {files}',
            'topic_posted'           => 'Topic posted successfully!',
            'topic_post_failed'      => 'Failed to post topic. Please try again.',
            'max_attachments'        => 'Max 5 attachments.',
            'file_type_not_allowed'  => 'File type .{ext} not allowed.',
            'file_too_large'         => '{filename} exceeds 10MB limit.',

            // ── Dashboard ──
            'overview'               => 'Overview',
            'total_posts'            => 'Total Posts',
            'reputation'             => 'Reputation',
            'active_licenses'        => 'Active Licenses',
            'account_summary'        => 'Account Summary',
            'member_since'           => 'Member since',
            'posts_contributed'      => '{count} post(s) contributed to the forum',
            'reputation_earned'      => '{count} reputation point(s) earned',
            'active_licenses_count'  => '{count} active license(s)',
            'my_posts'               => 'My Posts',
            'posts_made'             => 'You have made {count} post(s) on the forum.',
            'browse_forum'           => 'Browse Forum',
            'no_posts_yet'           => 'You haven\'t posted anything yet.',
            'start_discussion'       => 'Start a Discussion',
            'profile_settings'       => 'Profile Settings',
            'username_no_change'     => 'Username cannot be changed.',
            'display_name'           => 'Display Name',
            'email_address'          => 'Email Address',
            'location'               => 'Location',
            'location_placeholder'   => 'e.g. Germany',
            'about_me'               => 'About Me',
            'about_me_placeholder'   => 'Tell the community about yourself…',
            'forum_signature'        => 'Forum Signature',
            'signature_placeholder'  => 'Your signature shown below posts…',
            'profile_saved'          => 'Profile settings saved!',
            'could_not_save_settings' => 'Could not save settings. Please try again.',

            // ── Security ──
            'security'               => 'Security',
            'change_password'        => 'Change Password',
            'current_password'       => 'Current Password',
            'new_password'           => 'New Password',
            'min_8_chars'            => 'Min. 8 characters',
            'confirm_password'       => 'Confirm New Password',
            'repeat_password'        => 'Repeat new password',
            'update_password'        => 'Update Password',
            'password_updated'       => 'Password updated successfully.',
            'active_sessions'        => 'Active Sessions',
            'current_session'        => 'Current session',

            // ── Licenses ──
            'easytuner_pro_license'  => 'EasyTuner Pro License',
            'active'                 => 'Active',
            'inactive'               => 'Inactive',
            'user_id'                => 'User ID',
            'device'                 => 'Device',
            'device_activated'       => 'Activated',
            'device_not_activated'   => 'Not Activated',
            'expires'                => 'Expires',
            'refresh_status'         => 'Refresh Status',
            'disconnect'             => 'Disconnect',
            'connect_et_account'     => 'Connect Your Easy Tuner Account',
            'connect_et_desc'        => 'Link your Easy Tuner account to activate your license on this forum.',
            'et_email'               => 'Easy Tuner Email',
            'et_email_placeholder'   => 'your@email.com',
            'et_password'            => 'Easy Tuner Password',
            'et_password_placeholder' => 'Your Easy Tuner password',
            'connect_account'        => 'Connect Account',
            'please_fill_both'       => 'Please fill in both fields.',
            'connecting'             => 'Connecting...',
            'et_connected'           => 'Easy Tuner account connected!',
            'connection_failed'      => 'Connection failed.',
            'et_disconnected'        => 'Easy Tuner account disconnected.',
            'could_not_disconnect'   => 'Could not disconnect.',
            'checking'               => 'Checking...',
            'license_refreshed'      => 'License status refreshed.',
            'could_not_check_license' => 'Could not check license status.',
            'hwid_reset_success'     => 'HWID reset successfully! New HWID will be assigned on next launch.',
            'hwid_reset_cooldown'    => 'Reset applied — cooldown now active.',
            'could_not_reset_hwid'   => 'Could not reset HWID. Please try again.',

            // ── User Profile ──
            'user_not_found'         => 'User not found',
            'user_not_found_desc'    => 'This user does not exist or has been removed.',
            'member_since_label'     => 'Member Since',
            'signature_label'        => 'Signature',
            'licenses_label'         => 'Licenses',
            'key'                    => 'Key',
            'status'                 => 'Status',
            'never'                  => 'Never',

            // ── Account Update Modal ──
            'update_your_account'    => 'Update Your Account',
            'update_account_desc'    => 'Please verify your details. This is a one-time step after migration.',
            'account_updated'        => 'Account updated successfully!',
            'could_not_save'         => 'Could not save. Please try again.',

            // ── Ticker ──
            'resume_ticker'          => 'Resume ticker',
            'pause_ticker'           => 'Pause ticker',

            // ── Error Messages ──
            'error_generic'          => 'Something went wrong. Please try again.',
            'login_required'         => 'Please log in to continue.',
            'topic_created'          => 'Topic created!',
            'reply_posted'           => 'Reply posted!',
            'thanks_unlocked'        => 'Thanks added — content unlocked!',
        ];
    }
}
