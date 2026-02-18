// App entry point
const App = {
    async init() {
        // 1. Restore any persisted state (last view, etc.)
        State.init();

        // 2. Hydrate the current user.
        //    In WordPress: AF_DATA.currentUser is injected server-side (always fresh).
        //    In demo mode:  fall back to whatever State.init() loaded from localStorage.
        if ( typeof AF_DATA !== 'undefined' ) {
            if ( AF_DATA.currentUser ) {
                // Server already told us who is logged in — use it directly,
                // no extra AJAX round-trip needed.
                State.setUser( AF_DATA.currentUser );
            } else {
                // Not logged in on the server side — clear any stale local state.
                State.setUser( null );
            }
        }

        // 3. Render header with the correct auth state.
        Header.render();

        // 4. Init auth modal (tab switching + form submit bindings).
        Modal.initAuth();

        // 5. Init router — will render the correct initial view.
        Router.init();

        // 6. Mark user as online (heartbeat) — fire immediately then every 5 min.
        API.pingActive();
        setInterval( () => API.pingActive(), 5 * 60 * 1000 );

        // 7. First-visit welcome toast (demo / standalone only).
        if ( CONFIG.DEMO_MODE && !localStorage.getItem('esyf_visited') ) {
            localStorage.setItem('esyf_visited', '1');
            setTimeout(() => {
                Toast.info('👋 Welcome to EsyTuner Forum! Browse or sign in to get started.');
            }, 800);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
