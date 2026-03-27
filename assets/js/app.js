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

        // 6. Show "Update Account" modal for migrated users on first login.
        if ( State.isAuthenticated() && State.currentUser?.needsAccountUpdate ) {
            App.showAccountUpdateModal();
        }

        // 7. Mark user as online (heartbeat) — fire immediately then every 5 min.
        //    Guards live here so api.js stays decoupled from CONFIG and State.
        if ( ! CONFIG.DEMO_MODE && State.isAuthenticated() ) {
            API.pingActive();
            setInterval( () => { if ( State.isAuthenticated() ) API.pingActive(); }, 5 * 60 * 1000 );
        }

        // 8. First-visit welcome toast (demo / standalone only).
        if ( CONFIG.DEMO_MODE && !localStorage.getItem('esyf_visited') ) {
            localStorage.setItem('esyf_visited', '1');
            setTimeout(() => {
                Toast.info('Welcome to EsyTuner Forum! Browse or sign in to get started.');
            }, 800);
        }
    },

    showAccountUpdateModal() {
        const user = State.currentUser;
        const overlay = document.createElement('div');
        overlay.id = 'af-account-update-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = `
<div style="background:var(--card-bg,#fff);border-radius:12px;padding:2rem;width:440px;max-width:94vw;box-shadow:0 8px 40px rgba(0,0,0,.3);">
  <h2 style="margin:0 0 .5rem;font-size:1.25rem;"><i class="fa-solid fa-user-pen"></i> Update Your Account</h2>
  <p style="color:var(--text-muted);margin:0 0 1.5rem;font-size:.9rem;">Please verify your details. This is a one-time step after migration.</p>
  <div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">Display Name</label>
    <input class="form-input" type="text" id="au-display-name" value="${user.displayName || user.username}">
  </div>
  <div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">Email</label>
    <input class="form-input" type="email" id="au-email" value="${user.email || ''}">
  </div>
  <div class="form-group" style="margin-bottom:1.5rem">
    <label class="form-label">Location</label>
    <input class="form-input" type="text" id="au-location" placeholder="e.g. Tbilisi, Georgia" value="${user.location || ''}">
  </div>
  <button class="btn btn-primary" type="button" id="au-save-btn" style="width:100%">
    <i class="fa-solid fa-check"></i> Save & Continue
  </button>
  <span id="au-msg" style="display:block;margin-top:.5rem;text-align:center;font-size:.85rem;color:var(--text-muted)"></span>
</div>`;
        document.body.appendChild(overlay);

        document.getElementById('au-save-btn').addEventListener('click', async () => {
            const btn = document.getElementById('au-save-btn');
            const msg = document.getElementById('au-msg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            try {
                await API.accountUpdated({
                    display_name: document.getElementById('au-display-name').value.trim(),
                    email: document.getElementById('au-email').value.trim(),
                    location: document.getElementById('au-location').value.trim(),
                });
                overlay.remove();
                Toast.success('Account updated successfully!');
                const userData = await API.getUserData();
                if (userData?.user) {
                    State.setUser(userData.user);
                    Header.render();
                }
            } catch (err) {
                msg.textContent = err.message || 'Could not save. Please try again.';
                msg.style.color = 'var(--danger,#e74c3c)';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Save & Continue';
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
