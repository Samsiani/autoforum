<?php
/**
 * Translations — Multilingual string management for AutoForum.
 *
 * Supports Georgian (ka, default) and English (en).
 * English defaults are built-in. Georgian overrides are stored in wp_options.
 * Current language is read from the 'af_lang' cookie.
 *
 * @package AutoForum
 */

namespace AutoForum;

defined( 'ABSPATH' ) || exit;

class Translations {

    private const OPTION_PREFIX = 'af_translations_';
    public const LANGUAGES = [
        'ka' => 'ქართული',
        'en' => 'English',
    ];
    public const DEFAULT_LANG = 'ka';

    /** @var array Cached translations for current language. */
    private static ?array $cache = null;
    private static ?string $current_lang = null;

    /**
     * Get current language from cookie, defaulting to Georgian.
     */
    public static function current_lang(): string {
        if ( null !== self::$current_lang ) {
            return self::$current_lang;
        }
        $lang = $_COOKIE['af_lang'] ?? self::DEFAULT_LANG;
        self::$current_lang = isset( self::LANGUAGES[ $lang ] ) ? $lang : self::DEFAULT_LANG;
        return self::$current_lang;
    }

    /**
     * Get a translated string for the current language.
     */
    public static function get( string $key, string $fallback = '' ): string {
        $all = self::all();
        return $all[ $key ] ?? $fallback ?: $key;
    }

    /**
     * Get all translations for the current language.
     */
    public static function all(): array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }
        self::$cache = self::for_lang( self::current_lang() );
        return self::$cache;
    }

    /**
     * Get all translations for a specific language.
     * Falls back to English defaults for any missing key.
     */
    public static function for_lang( string $lang ): array {
        $en_defaults = self::defaults_en();
        $ka_defaults = self::defaults_ka();
        $defaults    = 'ka' === $lang ? $ka_defaults : $en_defaults;

        // Load saved overrides.
        $overrides = get_option( self::OPTION_PREFIX . $lang, [] );
        if ( ! is_array( $overrides ) ) {
            $overrides = [];
        }

        // Merge: start with English defaults, overlay language defaults, then overrides.
        $merged = $en_defaults;
        foreach ( $defaults as $k => $v ) {
            if ( '' !== $v ) {
                $merged[ $k ] = $v;
            }
        }
        foreach ( $overrides as $k => $v ) {
            if ( '' !== trim( $v ) ) {
                $merged[ $k ] = $v;
            }
        }

        return $merged;
    }

    /**
     * Save translation overrides for a language.
     */
    public static function save( string $lang, array $overrides ): void {
        if ( ! isset( self::LANGUAGES[ $lang ] ) ) {
            return;
        }
        $defaults = 'ka' === $lang ? self::defaults_ka() : self::defaults_en();
        $en_defaults = self::defaults_en();
        $clean = [];
        foreach ( $overrides as $k => $v ) {
            if ( ! isset( $en_defaults[ $k ] ) ) {
                continue;
            }
            $v = sanitize_text_field( wp_unslash( $v ) );
            // Only save if different from the language default.
            $default_val = $defaults[ $k ] ?? $en_defaults[ $k ] ?? '';
            if ( $v !== $default_val ) {
                $clean[ $k ] = $v;
            }
        }
        update_option( self::OPTION_PREFIX . $lang, $clean, false );
        self::$cache = null;
    }

    /**
     * Get translation groups for the admin UI.
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
                'keys'  => [ 'pw_weak', 'pw_fair', 'pw_good', 'pw_strong', 'pw_perfect' ],
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
                'keys'  => [ 'resume_ticker', 'pause_ticker' ],
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
     * English defaults (built-in).
     */
    public static function defaults_en(): array {
        return [
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
            'pw_weak'                => 'Weak',
            'pw_fair'                => 'Fair',
            'pw_good'                => 'Good',
            'pw_strong'              => 'Strong',
            'pw_perfect'             => 'Perfect',
            'nav_home'               => 'Home',
            'nav_ecu_tuning'         => 'ECU Tuning',
            'nav_software'           => 'Software',
            'nav_support'            => 'Support',
            'new_topic'              => 'New Topic',
            'dashboard'              => 'Dashboard',
            'edit_profile'           => 'Edit Profile',
            'my_licenses'            => 'My Licenses',
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
            'user_not_found'         => 'User not found',
            'user_not_found_desc'    => 'This user does not exist or has been removed.',
            'member_since_label'     => 'Member Since',
            'signature_label'        => 'Signature',
            'licenses_label'         => 'Licenses',
            'key'                    => 'Key',
            'status'                 => 'Status',
            'never'                  => 'Never',
            'update_your_account'    => 'Update Your Account',
            'update_account_desc'    => 'Please verify your details. This is a one-time step after migration.',
            'account_updated'        => 'Account updated successfully!',
            'could_not_save'         => 'Could not save. Please try again.',
            'resume_ticker'          => 'Resume ticker',
            'pause_ticker'           => 'Pause ticker',
            'error_generic'          => 'Something went wrong. Please try again.',
            'login_required'         => 'Please log in to continue.',
            'topic_created'          => 'Topic created!',
            'reply_posted'           => 'Reply posted!',
            'thanks_unlocked'        => 'Thanks added — content unlocked!',
        ];
    }

    /**
     * Georgian defaults.
     */
    public static function defaults_ka(): array {
        return [
            'site_name'              => 'EsyTuner ფორუმი',
            'home'                   => 'მთავარი',
            'sign_in'                => 'შესვლა',
            'register'               => 'რეგისტრაცია',
            'log_out'                => 'გასვლა',
            'loading'                => 'იტვირთება',
            'go_home'                => 'მთავარზე დაბრუნება',
            'save_changes'           => 'შენახვა',
            'cancel'                 => 'გაუქმება',
            'delete'                 => 'წაშლა',
            'edit'                   => 'რედაქტირება',
            'search'                 => 'ძიება',
            'sign_in_required'       => 'საჭიროა შესვლა',
            'please_sign_in'         => 'გთხოვთ შეხვიდეთ თქვენს ანგარიშზე.',
            'username'               => 'მომხმარებელი',
            'password'               => 'პაროლი',
            'email'                  => 'ელ-ფოსტა',
            'remember_me'            => 'დამახსოვრება',
            'forgot_password'        => 'დაგავიწყდათ პაროლი?',
            'create_account'         => 'ანგარიშის შექმნა',
            'username_password_required' => 'მომხმარებელი და პაროლი აუცილებელია.',
            'signing_in'             => 'შესვლა…',
            'welcome_back'           => 'გამარჯობა, {username}!',
            'login_failed'           => 'შესვლა ვერ მოხერხდა. სცადეთ ხელახლა.',
            'all_fields_required'    => 'ყველა ველი აუცილებელია.',
            'password_min_8'         => 'პაროლი მინიმუმ 8 სიმბოლო უნდა იყოს.',
            'creating_account'       => 'ანგარიშის შექმნა…',
            'account_created_welcome' => 'ანგარიში შეიქმნა! გამარჯობა, {username}!',
            'registration_failed'    => 'რეგისტრაცია ვერ მოხერხდა. სცადეთ ხელახლა.',
            'signed_out'             => 'თქვენ გამოხვედით ანგარიშიდან.',
            'login_required_page'    => 'ამ გვერდზე შესასვლელად გთხოვთ შეხვიდეთ.',
            'pw_weak'                => 'სუსტი',
            'pw_fair'                => 'საშუალო',
            'pw_good'                => 'კარგი',
            'pw_strong'              => 'ძლიერი',
            'pw_perfect'             => 'შესანიშნავი',
            'nav_home'               => 'მთავარი',
            'nav_ecu_tuning'         => 'ECU თიუნინგი',
            'nav_software'           => 'პროგრამები',
            'nav_support'            => 'დახმარება',
            'new_topic'              => 'ახალი თემა',
            'dashboard'              => 'პანელი',
            'edit_profile'           => 'პროფილის რედაქტირება',
            'my_licenses'            => 'ჩემი ლიცენზიები',
            'forum_categories'       => 'ფორუმის კატეგორიები',
            'threads'                => 'თემები',
            'posts'                  => 'პოსტები',
            'forum_stats'            => 'ფორუმის სტატისტიკა',
            'members'                => 'წევრები',
            'online'                 => 'ონლაინ',
            'top_contributors'       => 'ტოპ კონტრიბუტორები',
            'rep'                    => 'რეპ',
            'no_data'                => 'მონაცემები არ არის.',
            'latest_posts'           => 'უახლესი პოსტები',
            'no_recent_posts'        => 'ბოლო პოსტები არ არის.',
            'join_community'         => 'შემოგვიერთდი',
            'join_community_desc'    => 'დარეგისტრირდი უფასოდ, გაუზიარე თიუნინგი და მიიღე დახმარება.',
            'could_not_load_threads' => 'თემების ჩატვირთვა ვერ მოხერხდა',
            'unexpected_error'       => 'მოულოდნელი შეცდომა.',
            'no_topics_yet'          => 'ამ კატეგორიაში თემები ჯერ არ არის.',
            'be_first_to_post'       => 'იყავი პირველი!',
            'latest_activity'        => 'ბოლო აქტივობა',
            'newest_first'           => 'უახლესი',
            'most_active'            => 'ყველაზე აქტიური',
            'most_viewed'            => 'ყველაზე ნანახი',
            'thread'                 => 'თემა',
            'last_reply'             => 'ბოლო პასუხი',
            'replies'                => 'პასუხები',
            'views'                  => 'ნახვები',
            'pinned'                 => 'მიმაგრებული',
            'locked'                 => 'ჩაკეტილი',
            'premium'                => 'პრემიუმ',
            'prev'                   => 'წინა',
            'next'                   => 'შემდეგი',
            'sign_in_to_post'        => 'ახალი თემის შესაქმნელად გთხოვთ შეხვიდეთ.',
            'could_not_load_thread'  => 'თემის ჩატვირთვა ვერ მოხერხდა',
            'premium_thread'         => 'პრემიუმ თემა',
            'premium_thread_desc'    => 'ეს თემა ხელმისაწვდომია მხოლოდ აქტიური ლიცენზიის მქონე წევრებისთვის.',
            'premium_unlock_desc'    => 'გახსენი წვდომა პრემიუმ კონტენტზე, თიუნინგ ფაილებზე და ექსპერტთა დისკუსიაზე.',
            'view_my_licenses'       => 'ჩემი ლიცენზიების ნახვა',
            'sign_in_to_reply'       => 'დისკუსიაში მონაწილეობისთვის გთხოვთ შეხვიდეთ.',
            'post_a_reply'           => 'პასუხის დაწერა',
            'write_reply_placeholder' => 'დაწერეთ თქვენი პასუხი… იყავით დამხმარე და პატივმოყვარე.',
            'file_upload_hint'       => 'მაქს 5 ფაილი · 10MB თითო · jpg, png, pdf, bin, csv, log',
            'post_reply'             => 'პასუხის გაგზავნა',
            'sign_in_to_like'        => 'პოსტების მოწონებისთვის გთხოვთ შეხვიდეთ.',
            'unlocking'              => 'იხსნება…',
            'premium_unlocked'       => 'პრემიუმ კონტენტი გაიხსნა!',
            'could_not_unlock'       => 'კონტენტის გახსნა ვერ მოხერხდა. სცადეთ ხელახლა.',
            'unlock_premium'         => 'პრემიუმ კონტენტის გახსნა',
            'sign_in_to_quote'       => 'ციტირებისთვის გთხოვთ შეხვიდეთ.',
            'reply_area_unavailable' => 'პასუხის არეა მიუწვდომელია.',
            'quoting_author'         => '{author}-ის ციტირება — დაწერეთ თქვენი პასუხი ქვემოთ.',
            'sign_in_to_report'      => 'რეპორტისთვის გთხოვთ შეხვიდეთ.',
            'report_submitted'       => 'რეპორტი გაგზავნილია. მოდერატორები განიხილავენ.',
            'could_not_report'       => 'რეპორტის გაგზავნა ვერ მოხერხდა. სცადეთ ხელახლა.',
            'delete_post_confirm'    => 'წაშალოთ ეს პოსტი? ეს ვერ გაუქმდება.',
            'post_deleted'           => 'პოსტი წაიშალა.',
            'could_not_delete_post'  => 'პოსტის წაშლა ვერ მოხერხდა.',
            'post_too_short'         => 'პოსტი ძალიან მოკლეა (მინ 10 სიმბოლო).',
            'post_updated'           => 'პოსტი განახლდა.',
            'could_not_save_edit'    => 'რედაქტირების შენახვა ვერ მოხერხდა.',
            'subscribed'             => 'გამოწერილია! მიიღებთ შეტყობინებას ახალი პასუხების შესახებ.',
            'link_copied'            => 'თემის ბმული დაკოპირდა.',
            'quote'                  => 'ციტატა',
            'bold'                   => 'მუქი',
            'italic'                 => 'დახრილი',
            'underline'              => 'ხაზგასმული',
            'heading'                => 'სათაური',
            'code_block'             => 'კოდის ბლოკი',
            'quote_block'            => 'ციტატა',
            'ordered_list'           => 'დანომრილი სია',
            'unordered_list'         => 'სია',
            'link'                   => 'ბმული',
            'image'                  => 'სურათი',
            'bold_text'              => 'მუქი ტექსტი',
            'italic_text'            => 'დახრილი ტექსტი',
            'underlined_text'        => 'ხაზგასმული ტექსტი',
            'heading_text'           => 'სათაური',
            'code_here'              => 'კოდი აქ',
            'quoted_text'            => 'ციტირებული ტექსტი',
            'list_item'              => 'სიის ელემენტი',
            'enter_url'              => 'შეიყვანეთ URL:',
            'enter_image_url'        => 'შეიყვანეთ სურათის URL:',
            'must_sign_in_to_create' => 'თემის შესაქმნელად აუცილებელია შესვლა.',
            'create_new_topic'       => 'ახალი თემის შექმნა',
            'category'               => 'კატეგორია',
            'select_category'        => '— აირჩიეთ კატეგორია —',
            'thread_prefix'          => 'თემის პრეფიქსი',
            'none'                   => 'არცერთი',
            'title'                  => 'სათაური',
            'title_placeholder'      => 'იყავით კონკრეტული — მაგ. BMW N54 Stage 3 lean condition 7000rpm-ზე',
            'content'                => 'შინაარსი',
            'content_placeholder'    => 'აღწერეთ თქვენი თემა დეტალურად. მიუთითეთ მანქანის მარკა/მოდელი, მოდიფიკაციები, პროგრამის ვერსია.',
            'tags'                   => 'ტეგები',
            'tags_placeholder'       => 'მაგ. BMW, N54, stage3, E40 — გამოყავით მძიმით',
            'tags_hint'              => 'მაქსიმუმ 5 ტეგი, მძიმით გამოყოფილი.',
            'attachments'            => 'მიმაგრებული ფაილები',
            'drag_drop_files'        => 'გადმოათრიეთ ფაილები აქ, ან აირჩიეთ',
            'lock_content_label'     => 'კონტენტის ჩაკეტვა ლიცენზირებული მომხმარებლებისთვის',
            'lock_content_desc'      => 'პოსტის ნაწილის დამალვა ლიცენზიის მოთხოვნით (EsyTuner Pro)',
            'save_draft'             => 'დრაფტის შენახვა',
            'post_topic'             => 'თემის გამოქვეყნება',
            'posting_tips_title'     => 'გამოქვეყნების რჩევები',
            'tip_clear_title'        => 'გამოიყენეთ მკაფიო, კონკრეტული სათაური',
            'tip_vehicle_info'       => 'მიუთითეთ მანქანის მარკა, მოდელი და წელი',
            'tip_software_version'   => 'მიუთითეთ პროგრამის ვერსია',
            'tip_share_logs'         => 'გააზიარეთ ლოგები ან სქრინშოტები',
            'tip_search_first'       => 'ჯერ მოძებნეთ, დუბლიკატების თავიდან ასაცილებლად',
            'tip_be_respectful'      => 'იყავით თემაზე და პატივმოყვარე',
            'forum_rules_title'      => 'ფორუმის წესები',
            'forum_rules_desc'       => 'არ არის სპამი, პირატობა ან შეურაცხმყოფელი კონტენტი. გაუზიარეთ გამოცდილება და დაეხმარეთ სხვებს.',
            'read_full_rules'        => 'სრული წესების წაკითხვა',
            'draft_saved'            => 'დრაფტი შენახულია.',
            'select_category_error'  => 'გთხოვთ აირჩიოთ კატეგორია.',
            'enter_title_error'      => 'გთხოვთ შეიყვანოთ სათაური.',
            'title_too_short'        => 'სათაური ძალიან მოკლეა (მინ 10 სიმბოლო).',
            'enter_content_error'    => 'გთხოვთ დაწეროთ შინაარსი.',
            'content_too_short'      => 'შინაარსი ძალიან მოკლეა (მინ 30 სიმბოლო).',
            'posting'                => 'იგზავნება…',
            'uploading_files'        => 'ფაილები იტვირთება…',
            'files_failed_upload'    => 'თემა გამოქვეყნდა, მაგრამ {count} ფაილის ატვირთვა ვერ მოხერხდა: {files}',
            'topic_posted'           => 'თემა წარმატებით გამოქვეყნდა!',
            'topic_post_failed'      => 'თემის გამოქვეყნება ვერ მოხერხდა. სცადეთ ხელახლა.',
            'max_attachments'        => 'მაქსიმუმ 5 მიმაგრება.',
            'file_type_not_allowed'  => '.{ext} ტიპის ფაილი დაუშვებელია.',
            'file_too_large'         => '{filename} აჭარბებს 10MB ლიმიტს.',
            'overview'               => 'მიმოხილვა',
            'total_posts'            => 'სულ პოსტები',
            'reputation'             => 'რეპუტაცია',
            'active_licenses'        => 'აქტიური ლიცენზიები',
            'account_summary'        => 'ანგარიშის მიმოხილვა',
            'member_since'           => 'წევრია',
            'posts_contributed'      => '{count} პოსტი ფორუმზე',
            'reputation_earned'      => '{count} რეპუტაციის ქულა',
            'active_licenses_count'  => '{count} აქტიური ლიცენზია',
            'my_posts'               => 'ჩემი პოსტები',
            'posts_made'             => 'თქვენ დაწერეთ {count} პოსტი ფორუმზე.',
            'browse_forum'           => 'ფორუმის დათვალიერება',
            'no_posts_yet'           => 'ჯერ არაფერი დაგიწერიათ.',
            'start_discussion'       => 'დისკუსიის დაწყება',
            'profile_settings'       => 'პროფილის პარამეტრები',
            'username_no_change'     => 'მომხმარებლის სახელი ვერ შეიცვლება.',
            'display_name'           => 'საჩვენებელი სახელი',
            'email_address'          => 'ელ-ფოსტა',
            'location'               => 'მდებარეობა',
            'location_placeholder'   => 'მაგ. თბილისი',
            'about_me'               => 'ჩემ შესახებ',
            'about_me_placeholder'   => 'მოგვიყევით თქვენ შესახებ…',
            'forum_signature'        => 'ფორუმის ხელმოწერა',
            'signature_placeholder'  => 'თქვენი ხელმოწერა პოსტების ქვემოთ…',
            'profile_saved'          => 'პროფილის პარამეტრები შენახულია!',
            'could_not_save_settings' => 'პარამეტრების შენახვა ვერ მოხერხდა. სცადეთ ხელახლა.',
            'security'               => 'უსაფრთხოება',
            'change_password'        => 'პაროლის შეცვლა',
            'current_password'       => 'მიმდინარე პაროლი',
            'new_password'           => 'ახალი პაროლი',
            'min_8_chars'            => 'მინ. 8 სიმბოლო',
            'confirm_password'       => 'პაროლის დადასტურება',
            'repeat_password'        => 'გაიმეორეთ ახალი პაროლი',
            'update_password'        => 'პაროლის განახლება',
            'password_updated'       => 'პაროლი წარმატებით განახლდა.',
            'active_sessions'        => 'აქტიური სესიები',
            'current_session'        => 'მიმდინარე სესია',
            'easytuner_pro_license'  => 'EasyTuner Pro ლიცენზია',
            'active'                 => 'აქტიური',
            'inactive'               => 'არააქტიური',
            'user_id'                => 'მომხმარებლის ID',
            'device'                 => 'მოწყობილობა',
            'device_activated'       => 'აქტივირებული',
            'device_not_activated'   => 'არ არის აქტივირებული',
            'expires'                => 'ვადა',
            'refresh_status'         => 'სტატუსის განახლება',
            'disconnect'             => 'გაწყვეტა',
            'connect_et_account'     => 'Easy Tuner ანგარიშის დაკავშირება',
            'connect_et_desc'        => 'დააკავშირეთ თქვენი Easy Tuner ანგარიში ლიცენზიის გასააქტიურებლად.',
            'et_email'               => 'Easy Tuner ელ-ფოსტა',
            'et_email_placeholder'   => 'your@email.com',
            'et_password'            => 'Easy Tuner პაროლი',
            'et_password_placeholder' => 'თქვენი Easy Tuner პაროლი',
            'connect_account'        => 'ანგარიშის დაკავშირება',
            'please_fill_both'       => 'გთხოვთ შეავსოთ ორივე ველი.',
            'connecting'             => 'უკავშირდება...',
            'et_connected'           => 'Easy Tuner ანგარიში დაკავშირდა!',
            'connection_failed'      => 'კავშირი ვერ მოხერხდა.',
            'et_disconnected'        => 'Easy Tuner ანგარიში გაწყდა.',
            'could_not_disconnect'   => 'გაწყვეტა ვერ მოხერხდა.',
            'checking'               => 'მოწმდება...',
            'license_refreshed'      => 'ლიცენზიის სტატუსი განახლდა.',
            'could_not_check_license' => 'ლიცენზიის შემოწმება ვერ მოხერხდა.',
            'hwid_reset_success'     => 'HWID გასუფთავდა! ახალი მოწყობილობა გააქტიურდება გაშვებისას.',
            'hwid_reset_cooldown'    => 'რესეტი გამოყენებულია — cooldown აქტიურია.',
            'could_not_reset_hwid'   => 'HWID რესეტი ვერ მოხერხდა. სცადეთ ხელახლა.',
            'user_not_found'         => 'მომხმარებელი ვერ მოიძებნა',
            'user_not_found_desc'    => 'ეს მომხმარებელი არ არსებობს ან წაშლილია.',
            'member_since_label'     => 'წევრია',
            'signature_label'        => 'ხელმოწერა',
            'licenses_label'         => 'ლიცენზიები',
            'key'                    => 'გასაღები',
            'status'                 => 'სტატუსი',
            'never'                  => 'უვადო',
            'update_your_account'    => 'ანგარიშის განახლება',
            'update_account_desc'    => 'გთხოვთ გადაამოწმოთ თქვენი მონაცემები. ეს ერთჯერადი ნაბიჯია.',
            'account_updated'        => 'ანგარიში წარმატებით განახლდა!',
            'could_not_save'         => 'შენახვა ვერ მოხერხდა. სცადეთ ხელახლა.',
            'resume_ticker'          => 'ტიკერის გაშვება',
            'pause_ticker'           => 'ტიკერის შეჩერება',
            'error_generic'          => 'რაღაც შეცდომა მოხდა. სცადეთ ხელახლა.',
            'login_required'         => 'გთხოვთ შეხვიდეთ გასაგრძელებლად.',
            'topic_created'          => 'თემა შეიქმნა!',
            'reply_posted'           => 'პასუხი გამოქვეყნდა!',
            'thanks_unlocked'        => 'მადლობა დამატებულია — კონტენტი გაიხსნა!',
        ];
    }
}
