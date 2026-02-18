// Application State Management
const State = {
    // Current user
    currentUser: null,

    // Current view
    currentView: 'home',

    // View data/context
    viewData: {},

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

    // Storage persistence
    saveToStorage() {
        try {
            const stateData = {
                currentUser: this.currentUser,
                currentView: this.currentView,
                viewData:    this.viewData,
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
                this.viewData    = data.viewData    || {};
            }
        } catch (e) {
            console.error('Failed to load state:', e);
        }
    },

    clearState() {
        this.currentUser = null;
        this.currentView = 'home';
        this.viewData    = {};
        localStorage.removeItem('esytuner_forum_state');
    },

    // Initialize state from storage on load
    init() {
        this.loadFromStorage();
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = State;
}
