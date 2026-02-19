// Modal system
const Modal = {
    show(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('show');
        document.body.style.overflow = 'hidden';
        this._bindClose(el);
    },

    hide(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('show');
        document.body.style.overflow = '';
    },

    _bindClose(el) {
        const hideModal = () => this.hide(el.id);
        const closeEl = el.querySelector('.af-modal-close');
        if (closeEl) {
            closeEl.addEventListener('click', hideModal, { once: true });
            // keyboard support for div[role=button]
            closeEl.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); hideModal(); }
            }, { once: true });
        }
        el.addEventListener('click', e => { if (e.target === el) hideModal(); }, { once: true });
        const esc = e => { if (e.key === 'Escape') { hideModal(); document.removeEventListener('keydown', esc); } };
        document.addEventListener('keydown', esc);
    },

    // Auth modal setup
    initAuth() {
        const modal = document.getElementById('auth-modal');
        if (!modal) return;

        const tabs  = modal.querySelectorAll('.af-modal-tab');
        const panes = modal.querySelectorAll('.af-modal-pane');

        const switchTab = (tab) => {
            tabs.forEach(t  => t.classList.toggle('active', t.dataset.tab === tab));
            panes.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
            // Slide indicator
            const activeTab = modal.querySelector(`.af-modal-tab[data-tab="${tab}"]`);
            const indicator = modal.querySelector('.af-modal-tab-indicator');
            if (activeTab && indicator) {
                indicator.style.left  = activeTab.offsetLeft  + 'px';
                indicator.style.width = activeTab.offsetWidth + 'px';
            }
        };

        tabs.forEach(t => {
            t.addEventListener('click', () => switchTab(t.dataset.tab));
            // keyboard support for div[role=tab]
            t.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); switchTab(t.dataset.tab); }
            });
        });

        // Password visibility toggles (works for both button and div)
        modal.querySelectorAll('.af-eye-btn').forEach(btn => {
            const toggle = () => {
                const input = btn.closest('.af-input-wrap').querySelector('.af-input');
                const icon  = btn.querySelector('i');
                if (!input || !icon) return;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                icon.className = isHidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            };
            btn.addEventListener('click', toggle);
            btn.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
        });

        // Password strength meter (register)
        const regPwd    = modal.querySelector('#reg-password');
        const fillEl    = modal.querySelector('#reg-strength-fill');
        const labelEl   = modal.querySelector('#reg-strength-label');
        const strength  = pw => {
            if (!pw) return { score: 0, label: '', color: '' };
            let score = 0;
            if (pw.length >= 8)  score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            const map = [
                { label: '',         color: '#333'    },
                { label: 'Weak',     color: '#ef4444' },
                { label: 'Fair',     color: '#f97316' },
                { label: 'Good',     color: '#eab308' },
                { label: 'Strong',   color: '#22c55e' },
                { label: 'Perfect',  color: '#06b6d4' },
            ];
            return { score, ...map[score] };
        };
        regPwd?.addEventListener('input', () => {
            const r = strength(regPwd.value);
            if (fillEl) { fillEl.style.width = (r.score * 20) + '%'; fillEl.style.background = r.color; }
            if (labelEl) { labelEl.textContent = r.label; labelEl.style.color = r.color; }
        });

        // Position indicator on first load
        switchTab('login');

        modal.querySelector('#login-form')?.addEventListener('submit', e => {
            e.preventDefault(); this._doLogin(e.target);
        });

        modal.querySelector('#register-form')?.addEventListener('submit', e => {
            e.preventDefault(); this._doRegister(e.target);
        });
    },

    async _doLogin(form) {
        const username  = form.querySelector('[name="username"]').value.trim();
        const password  = form.querySelector('[name="password"]').value;
        const remember  = form.querySelector('[name="remember"]')?.checked ?? false;
        const errorEl   = form.querySelector('#login-error');
        const submitBtn = form.querySelector('#login-submit');

        if (!username || !password) {
            if (errorEl) errorEl.textContent = 'Username and password are required.';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing in…';
        if (errorEl) errorEl.textContent = '';

        try {
            const data = await API.login(username, password, remember);
            // Refresh all nonces — the page-load nonces were generated for the
            // guest (user 0) and are now invalid for the logged-in user.
            if ( data.nonces && typeof AF_DATA !== 'undefined' ) {
                Object.assign( AF_DATA.nonces, data.nonces );
            }
            if ( data.restNonce && typeof AF_DATA !== 'undefined' ) {
                AF_DATA.restNonce = data.restNonce;
            }
            // Keep AF_DATA.currentUser in sync so api.js logout() can read
            // user.nonces.logout from it (it reads AF_DATA.currentUser, not State).
            if ( typeof AF_DATA !== 'undefined' ) {
                AF_DATA.currentUser = data.user;
            }
            State.setUser(data.user);
            this.hide('auth-modal');
            Toast.success(`Welcome back, ${data.user.username}!`);
            Header.render();
            Router.navigateTo('home');
        } catch (err) {
            if (errorEl) errorEl.textContent = err.message ?? 'Login failed. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Sign In';
        }
    },

    /**
     * Open the auth modal on a specific tab ('login' or 'register').
     * Scopes the tab query to #auth-modal so it is unambiguous.
     */
    showAuthTab(tab) {
        this.show('auth-modal');
        const tabEl = document.querySelector(`#auth-modal .af-modal-tab[data-tab="${tab}"]`);
        tabEl?.click();
    },

    async _doRegister(form) {
        const username  = form.querySelector('[name="username"]').value.trim();
        const email     = form.querySelector('[name="email"]').value.trim();
        const password  = form.querySelector('[name="password"]').value;
        const errorEl   = form.querySelector('#register-error');
        const submitBtn = form.querySelector('#register-submit');

        if (!username || !email || !password) {
            if (errorEl) errorEl.textContent = 'All fields are required.';
            return;
        }
        if (password.length < 8) {
            if (errorEl) errorEl.textContent = 'Password must be at least 8 characters.';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating account…';
        if (errorEl) errorEl.textContent = '';

        try {
            const data = await API.register(username, email, password);
            // Same nonce refresh as login — registration also switches user context.
            if ( data.nonces && typeof AF_DATA !== 'undefined' ) {
                Object.assign( AF_DATA.nonces, data.nonces );
            }
            if ( data.restNonce && typeof AF_DATA !== 'undefined' ) {
                AF_DATA.restNonce = data.restNonce;
            }
            if ( typeof AF_DATA !== 'undefined' ) {
                AF_DATA.currentUser = data.user;
            }
            State.setUser(data.user);
            this.hide('auth-modal');
            Toast.success(`Account created! Welcome, ${data.user.username}!`);
            Header.render();
            Router.navigateTo('home');
        } catch (err) {
            if (errorEl) errorEl.textContent = err.message ?? 'Registration failed. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Create Account';
        }
    }
};
