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
            const activeTab = modal.querySelector(`.af-modal-tab[data-tab="${tab}"]`);
            const indicator = modal.querySelector('.af-modal-tab-indicator');
            if (activeTab && indicator) {
                indicator.style.left  = activeTab.offsetLeft  + 'px';
                indicator.style.width = activeTab.offsetWidth + 'px';
            }
        };

        tabs.forEach(t => {
            t.addEventListener('click', () => switchTab(t.dataset.tab));
            t.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); switchTab(t.dataset.tab); }
            });
        });

        // Password visibility toggles
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
                { label: '',              color: '#333'    },
                { label: _t('pw_weak'),   color: '#ef4444' },
                { label: _t('pw_fair'),   color: '#f97316' },
                { label: _t('pw_good'),   color: '#eab308' },
                { label: _t('pw_strong'), color: '#22c55e' },
                { label: _t('pw_perfect'),color: '#06b6d4' },
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
            if (errorEl) errorEl.textContent = _t('username_password_required');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + _t('signing_in');
        if (errorEl) errorEl.textContent = '';

        try {
            const data = await API.login(username, password, remember);
            State.setUser(data.user);
            this.hide('auth-modal');
            Toast.success(_t('welcome_back', { username: data.user.username }));
            setTimeout( () => window.location.reload(), 800 );
        } catch (err) {
            if (errorEl) errorEl.textContent = err.message ?? _t('login_failed');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> ' + _t('sign_in');
        }
    },

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
            if (errorEl) errorEl.textContent = _t('all_fields_required');
            return;
        }
        if (password.length < 8) {
            if (errorEl) errorEl.textContent = _t('password_min_8');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + _t('creating_account');
        if (errorEl) errorEl.textContent = '';

        try {
            const data = await API.register(username, email, password);
            State.setUser(data.user);
            this.hide('auth-modal');
            Toast.success(_t('account_created_welcome', { username: data.user.username }));
            setTimeout( () => window.location.reload(), 800 );
        } catch (err) {
            if (errorEl) errorEl.textContent = err.message ?? _t('registration_failed');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-user-plus"></i> ' + _t('create_account');
        }
    }
};
