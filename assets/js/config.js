// Configuration and Constants
const CONFIG = {
    APP_NAME: 'EsyTuner Forum',
    VERSION: '1.0.0',

    // API Endpoints — overridden at runtime by AF_DATA (wp_localize_script).
    // These values are used only in standalone demo mode (no WordPress).
    API: {
        BASE_URL: '/wp-json/af/v1',
        ENDPOINTS: {
            LOGIN: '/auth/login',
            REGISTER: '/auth/register',
            THREADS: '/threads',
            POSTS: '/posts',
            USERS: '/users',
            LICENSES: '/licenses',
            HWID_RESET: '/licenses/reset-hwid'
        }
    },

    // Forum Settings — may be overridden by AF_DATA.settings.
    FORUM: {
        POSTS_PER_PAGE: 20,
        THREADS_PER_PAGE: 25,
        MAX_ATTACHMENT_SIZE: 10 * 1024 * 1024, // 10MB
        ALLOWED_FILE_TYPES: ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip'],
        HWID_RESET_COOLDOWN: 7 * 24 * 60 * 60 * 1000, // 7 days in ms
        TOAST_DURATION: 3000
    },

    // User Levels
    USER_LEVELS: {
        GUEST: 0,
        MEMBER: 1,
        VIP: 2,
        MODERATOR: 3,
        ADMIN: 4
    },

    // Thread Prefixes
    THREAD_PREFIXES: [
        { id: 'help', label: 'Help', color: '#ef4444' },
        { id: 'solved', label: 'Solved', color: '#10b981' },
        { id: 'discussion', label: 'Discussion', color: '#3b82f6' },
        { id: 'firmware', label: 'Firmware', color: '#8b5cf6' },
        { id: 'guide', label: 'Guide', color: '#f59e0b' }
    ],

    // Sort Options
    SORT_OPTIONS: [
        { value: 'latest', label: 'Latest Activity' },
        { value: 'created', label: 'Recently Created' },
        { value: 'replies', label: 'Most Replies' },
        { value: 'views', label: 'Most Views' }
    ],

    // Animations
    ANIMATIONS: {
        FADE: 300,
        SLIDE: 400,
        MODAL: 200
    },

    // True when running outside WordPress (no AF_DATA), OR when the admin
    // has explicitly enabled "Show demo data" in the plugin settings.
    get DEMO_MODE() {
        if ( typeof AF_DATA === 'undefined' ) return true;
        return AF_DATA.settings && AF_DATA.settings.showDemoData === true;
    }
};

// Merge live WordPress settings into CONFIG at startup.
( function () {
    if ( typeof AF_DATA === 'undefined' ) return;

    const s = AF_DATA.settings ?? {};
    if ( s.threadsPerPage ) CONFIG.FORUM.THREADS_PER_PAGE = s.threadsPerPage;
    if ( s.postsPerPage   ) CONFIG.FORUM.POSTS_PER_PAGE   = s.postsPerPage;
    if ( s.primaryColor   ) {
        // Ensure the CSS variable is updated to match the admin setting.
        document.documentElement.style.setProperty( '--primary', s.primaryColor );
    }
} )();

// CommonJS export (for tooling / tests only).
if ( typeof module !== 'undefined' && module.exports ) {
    module.exports = CONFIG;
}
