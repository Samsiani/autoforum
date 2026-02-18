// Application State Management
const State = {
    // Current user
    currentUser: null,
    
    // Current view
    currentView: 'home',
    
    // View data/context
    viewData: {},
    
    // HWID Reset attempts tracking
    hwidResets: {
        lastReset: null,
        remainingResets: 3
    },
    
    // Unlocked content tracking
    unlockedContent: new Set(),
    
    // Notification queue
    notifications: [],
    
    // Methods
    setUser(user) {
        this.currentUser = user;
        this.saveToStorage();
    },
    
    getUser() {
        return this.currentUser;
    },
    
    isAuthenticated() {
        return this.currentUser !== null;
    },
    
    setView(viewName, data = {}) {
        this.currentView = viewName;
        this.viewData = data;
        this.saveToStorage();
    },
    
    getView() {
        return this.currentView;
    },
    
    getViewData() {
        return this.viewData;
    },
    
    canResetHWID() {
        if (!this.hwidResets.lastReset) return true;
        
        const cooldown = CONFIG.FORUM.HWID_RESET_COOLDOWN;
        const timeSinceReset = Date.now() - this.hwidResets.lastReset;
        
        return timeSinceReset >= cooldown && this.hwidResets.remainingResets > 0;
    },
    
    performHWIDReset() {
        if (!this.canResetHWID()) {
            return false;
        }
        
        this.hwidResets.lastReset = Date.now();
        this.hwidResets.remainingResets--;
        this.saveToStorage();
        return true;
    },
    
    getRemainingResets() {
        return this.hwidResets.remainingResets;
    },
    
    getNextResetDate() {
        if (!this.hwidResets.lastReset) return null;
        
        const nextReset = new Date(this.hwidResets.lastReset + CONFIG.FORUM.HWID_RESET_COOLDOWN);
        return nextReset;
    },
    
    unlockContent(contentId) {
        this.unlockedContent.add(contentId);
        this.saveToStorage();
    },
    
    isContentUnlocked(contentId) {
        return this.unlockedContent.has(contentId);
    },
    
    // Storage persistence
    saveToStorage() {
        try {
            const stateData = {
                currentUser: this.currentUser,
                currentView: this.currentView,
                viewData: this.viewData,
                hwidResets: this.hwidResets,
                unlockedContent: Array.from(this.unlockedContent)
            };
            localStorage.setItem('esytuner_forum_state', JSON.stringify(stateData));
        } catch (e) {
            console.error('Failed to save state:', e);
        }
    },
    
    loadFromStorage() {
        try {
            const stored = localStorage.getItem('esytuner_forum_state');
            if (stored) {
                const data = JSON.parse(stored);
                this.currentUser = data.currentUser || null;
                this.currentView = data.currentView || 'home';
                this.viewData = data.viewData || {};
                this.hwidResets = data.hwidResets || { lastReset: null, remainingResets: 3 };
                this.unlockedContent = new Set(data.unlockedContent || []);
            }
        } catch (e) {
            console.error('Failed to load state:', e);
        }
    },
    
    clearState() {
        this.currentUser = null;
        this.currentView = 'home';
        this.viewData = {};
        this.hwidResets = { lastReset: null, remainingResets: 3 };
        this.unlockedContent = new Set();
        this.notifications = [];
        localStorage.removeItem('esytuner_forum_state');
    },
    
    // Initialize state from storage on load
    init() {
        this.loadFromStorage();
    }
};

// Initialize state
State.init();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = State;
}
