// Router
const Router = {
    VIEWS: {
        'home':          HomeView,
        'thread-list':   ThreadListView,
        'thread-view':   ThreadView,
        'dashboard':     DashboardView,
        'create-topic':  CreateTopicView,
        'user-profile':  UserProfileView,
    },

    AUTH_REQUIRED: ['dashboard', 'create-topic'],

    currentView: 'home',
    currentParams: {},

    // ── URL helpers ───────────────────────────────────────────────────────────

    /**
     * Build a hash string that includes query params.
     * navigateTo('thread-view', { id: 12 }) → '#thread-view?id=12'
     * navigateTo('home', {})               → '#home'
     */
    _buildHash(view, params) {
        const qs = Object.entries(params)
            .filter(([, v]) => v !== undefined && v !== null && v !== '')
            .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
            .join('&');
        return '#' + view + (qs ? '?' + qs : '');
    },

    /**
     * Parse the current window.location.hash into { view, params }.
     * '#thread-view?id=12&page=2' → { view: 'thread-view', params: { id: 12, page: 2 } }
     * Numeric values are automatically cast to integers.
     */
    _parseHash(raw = '') {
        const fragment = raw.replace(/^#/, '').trim();
        if (!fragment) return { view: 'home', params: {} };

        const qIdx    = fragment.indexOf('?');
        const view    = qIdx === -1 ? fragment : fragment.slice(0, qIdx);
        const qs      = qIdx === -1 ? '' : fragment.slice(qIdx + 1);
        const params  = {};

        if (qs) {
            for (const pair of qs.split('&')) {
                const eqIdx = pair.indexOf('=');
                if (eqIdx === -1) continue;
                const key = decodeURIComponent(pair.slice(0, eqIdx));
                const raw = decodeURIComponent(pair.slice(eqIdx + 1));
                // Cast numeric strings to integers for convenience
                params[key] = /^\d+$/.test(raw) ? parseInt(raw, 10) : raw;
            }
        }

        return { view, params };
    },

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    init() {
        // Back / Forward button — e.state always contains {view, params} because
        // every navigateTo() call stores it via pushState/replaceState.
        window.addEventListener('popstate', e => {
            if (e.state && e.state.view) {
                this._render(e.state.view, e.state.params ?? {});
            } else {
                // Fallback: re-parse the hash (covers edge cases like manual URL edits)
                const { view, params } = this._parseHash(window.location.hash);
                this._render(this.VIEWS[view] ? view : 'home', params);
            }
        });

        // On page load / F5 — parse whatever is in the URL hash right now.
        // Priority: URL is the single source of truth on a fresh load.
        const { view, params } = this._parseHash(window.location.hash);

        if (this.VIEWS[view]) {
            this.navigateTo(view, params, true);   // replaceState keeps the URL clean
        } else {
            this.navigateTo('home', {}, true);
        }
    },

    /**
     * Navigate to a view, updating the URL hash + history state.
     * All params are embedded in the hash as query-string key=value pairs,
     * making every view fully shareable and refreshable via a simple URL.
     */
    navigateTo(view, params = {}, replace = false) {
        if (!this.VIEWS[view]) {
            console.warn(`Router: unknown view "${view}"`);
            view = 'home';
        }

        if (this.AUTH_REQUIRED.includes(view) && !State.isAuthenticated()) {
            Toast.warning('Please sign in to access that page.');
            Modal.show('auth-modal');
            return;
        }

        this.currentView   = view;
        this.currentParams = params;

        const hash  = this._buildHash(view, params);
        const state = { view, params };

        if (replace) {
            history.replaceState(state, '', hash);
        } else {
            history.pushState(state, '', hash);
        }

        this._render(view, params);
    },

    /**
     * Show a lightweight skeleton loader inside #main-container.
     * Called before an async view starts fetching data so the user
     * never sees a blank white screen.
     */
    _showLoader() {
        const el = document.getElementById('main-container');
        if (!el) return;
        el.innerHTML = `
<div class="page-wrap" aria-busy="true" aria-label="Loading">
  <div class="af-skeleton-wrap">
    <div class="af-skeleton af-skeleton--title"></div>
    <div class="af-skeleton af-skeleton--row"></div>
    <div class="af-skeleton af-skeleton--row"></div>
    <div class="af-skeleton af-skeleton--row af-skeleton--short"></div>
  </div>
</div>`;
    },

    /**
     * Render a view. Supports both synchronous and async render() methods.
     * If the view's render() returns a Promise we show a loader first,
     * await the promise, then fade in the result.
     */
    async _render(view, params, initial) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        const container = document.getElementById('main-container');
        if (container) container.style.opacity = '0';

        // Re-render header to update active nav state.
        State.setView(view);
        Header.render();

        const ViewClass = this.VIEWS[view];
        if (!ViewClass) return;

        try {
            // Show skeleton immediately so the user gets visual feedback
            // while the async view fetches data from the API.
            this._showLoader();

            // Await render regardless of whether it is sync or async —
            // a sync render() returns undefined which resolves instantly.
            await ViewClass.render(params);
        } catch (err) {
            // Surface API / network errors gracefully instead of a blank screen.
            console.error('Router render error:', err);
            if (container) {
                container.innerHTML = `
<div class="page-wrap fade-up">
  <div class="card" style="max-width:500px;margin:4rem auto;text-align:center">
    <div class="card-body" style="padding:2.5rem">
      <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem;color:var(--danger);margin-bottom:1rem;display:block"></i>
      <h2 style="margin-bottom:.5rem">Something went wrong</h2>
      <p style="color:var(--text-muted)">${err.message || 'Could not load this page. Please try again.'}</p>
      <button class="btn btn-primary" style="margin-top:1.5rem" onclick="Router.navigateTo('home')">Go Home</button>
    </div>
  </div>
</div>`;
            }
        }

        // Fade the container back in after render (sync or async).
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (container) container.style.opacity = '';
            });
        });
    }
};
