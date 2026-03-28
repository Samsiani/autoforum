// Dashboard view — user profile, licenses, settings
const DashboardView = {
    SECTIONS: [
        { id: 'overview',  icon: 'fa-gauge',             label: () => _t('overview') },
        { id: 'licenses',  icon: 'fa-key',               label: () => _t('my_licenses') },
        { id: 'posts',     icon: 'fa-comment-dots',      label: () => _t('my_posts') },
        { id: 'settings',  icon: 'fa-gear',              label: () => _t('profile_settings') },
        { id: 'security',  icon: 'fa-shield-halved',     label: () => _t('security') }
    ],

    activeSection: 'overview',

    render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        if (!State.isAuthenticated()) {
            el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="card" style="max-width:500px;margin:4rem auto;text-align:center">
    <div class="card-body" style="padding:3rem">
      <i class="fa-solid fa-lock" style="font-size:3rem;color:var(--text-dim);margin-bottom:1rem;display:block"></i>
      <h2 style="margin-bottom:.5rem">${_t('sign_in_required')}</h2>
      <p style="color:var(--text-muted);margin-bottom:1.5rem">${_t('please_sign_in')}</p>
      <button class="btn btn-primary" type="button" id="dash-login-btn">
        <i class="fa-solid fa-right-to-bracket"></i> ${_t('sign_in')}
      </button>
    </div>
  </div>
</div>`;
            document.getElementById('dash-login-btn')?.addEventListener('click', () => Modal.show('auth-modal'));
            return;
        }

        const user = State.currentUser;
        const section = params.section || this.activeSection;

        el.innerHTML = `
<div class="page-wrap fade-up">
  <nav class="breadcrumbs">
    <a href="#" data-view="home">${_t('home')}</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>${_t('dashboard')}</span>
  </nav>

  <!-- Profile Hero -->
  <div class="profile-hero">
    <div class="profile-banner"></div>
    <div class="profile-hero-body">
      <div class="profile-avatar-wrap">
        <img src="${user.avatar}" alt="${user.username}" class="profile-avatar">
        <span class="profile-status-dot"></span>
      </div>
      <div class="profile-info">
        <h1 class="profile-name">${user.username}</h1>
        <div class="profile-role">${user.role}</div>
        <div class="profile-stats">
          <div class="pstat-item">
            <span class="pstat-val">${user.postCount || 0}</span>
            <span class="pstat-lbl">${_t('posts')}</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.reputation || 0}</span>
            <span class="pstat-lbl">${_t('reputation')}</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.joined || '—'}</span>
            <span class="pstat-lbl">${_t('member_since_label')}</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.licenses?.length || 0}</span>
            <span class="pstat-lbl">${_t('licenses_label')}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Dashboard layout -->
  <div class="dashboard-layout">
    <!-- Sidebar nav -->
    <nav class="dash-nav">
      ${this.SECTIONS.map(s => `
      <button class="dash-nav-item${s.id === section ? ' active' : ''}" data-section="${s.id}" type="button">
        <i class="fa-solid ${s.icon}"></i> ${s.label()}
      </button>`).join('')}
      <div class="dash-nav-sep"></div>
      <button class="dash-nav-item dash-nav-item--danger" type="button" id="dash-logout">
        <i class="fa-solid fa-right-from-bracket"></i> ${_t('log_out')}
      </button>
    </nav>

    <!-- Content -->
    <div class="dash-content" id="dash-content">
      ${this._renderSection(section, user)}
    </div>
  </div>
</div>`;

        this._bindEvents(user);
    },

    _renderSection(section, user) {
        switch (section) {
            case 'overview': return this._sectionOverview(user);
            case 'licenses': return this._sectionLicenses(user);
            case 'posts':    return this._sectionPosts(user);
            case 'settings': return this._sectionSettings(user);
            case 'security': return this._sectionSecurity(user);
            default: return this._sectionOverview(user);
        }
    },

    _sectionOverview(user) {
        const posts = user.postCount || 0;
        const rep   = user.reputation || 0;
        const lics  = user.licenses?.length || 0;
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-gauge"></i> ${_t('overview')}</h2>
  <div class="overview-grid">
    <div class="ov-card">
      <i class="fa-solid fa-comment-dots ov-icon blue"></i>
      <div class="ov-val">${posts}</div>
      <div class="ov-lbl">${_t('total_posts')}</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-star ov-icon yellow"></i>
      <div class="ov-val">${rep}</div>
      <div class="ov-lbl">${_t('reputation')}</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-key ov-icon green"></i>
      <div class="ov-val">${lics}</div>
      <div class="ov-lbl">${_t('active_licenses')}</div>
    </div>
  </div>

  <div class="card" style="margin-top:1.5rem">
    <div class="card-header"><span class="card-title">${_t('account_summary')}</span></div>
    <div class="card-body">
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-user"></i></div>
        <div class="act-body">
          <span class="act-text">${_t('member_since')} <strong>${user.joined || '—'}</strong></span>
        </div>
      </div>
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-comment-dots"></i></div>
        <div class="act-body">
          <span class="act-text">${_t('posts_contributed', { count: posts })}</span>
        </div>
      </div>
      <div class="activity-item">
        <div class="act-icon yellow"><i class="fa-solid fa-star"></i></div>
        <div class="act-body">
          <span class="act-text">${_t('reputation_earned', { count: rep })}</span>
        </div>
      </div>
      ${lics > 0 ? `
      <div class="activity-item">
        <div class="act-icon green"><i class="fa-solid fa-key"></i></div>
        <div class="act-body">
          <span class="act-text">${_t('active_licenses_count', { count: lics })}</span>
        </div>
      </div>` : ''}
    </div>
  </div>
</div>`;
    },

    _sectionLicenses(user) {
        const et = user.easyTuner || {};
        if (et.connected) {
            return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-key"></i> ${_t('my_licenses')}</h2>
  <div class="license-card${et.active ? ' active-license' : ''}">
    <div class="lic-header">
      <div class="lic-name">
        <i class="fa-solid fa-certificate"></i>
        ${_t('easytuner_pro_license')}
      </div>
      <span class="badge ${et.active ? 'badge-active' : 'badge-pinned'}" id="et-status-badge">${et.active ? _t('active') : _t('inactive')}</span>
    </div>
    <div class="lic-body">
      <div class="lic-row">
        <span class="lic-lbl">${_t('email')}</span>
        <span class="lic-val">${et.email}</span>
      </div>
      <div class="lic-row">
        <span class="lic-lbl">${_t('user_id')}</span>
        <span class="lic-val mono">${et.userId || 'N/A'}</span>
      </div>
      <div class="lic-row">
        <span class="lic-lbl">${_t('device')}</span>
        <span class="lic-val">${et.deviceActivated ? '<span class="badge badge-active">' + _t('device_activated') + '</span>' : '<span class="badge badge-pinned">' + _t('device_not_activated') + '</span>'}</span>
      </div>
      ${et.expiresAt ? `<div class="lic-row">
        <span class="lic-lbl">${_t('expires')}</span>
        <span class="lic-val">${new Date(et.expiresAt).toLocaleDateString()}</span>
      </div>` : ''}
    </div>
    <div class="lic-footer">
      <button class="btn btn-secondary btn-sm" type="button" id="et-refresh-btn">
        <i class="fa-solid fa-arrows-rotate"></i> ${_t('refresh_status')}
      </button>
      <button class="btn btn-secondary btn-sm dash-nav-item--danger" type="button" id="et-disconnect-btn">
        <i class="fa-solid fa-plug-circle-xmark"></i> ${_t('disconnect')}
      </button>
    </div>
  </div>
</div>`;
        }
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-key"></i> ${_t('my_licenses')}</h2>
  <div class="card">
    <div class="card-header"><span class="card-title">${_t('connect_et_account')}</span></div>
    <div class="card-body">
      <p style="color:var(--text-muted);margin-bottom:1rem">${_t('connect_et_desc')}</p>
      <div class="form-group">
        <label class="form-label">${_t('et_email')}</label>
        <input class="form-input" type="email" id="et-email" placeholder="${_t('et_email_placeholder')}">
      </div>
      <div class="form-group">
        <label class="form-label">${_t('et_password')}</label>
        <input class="form-input" type="password" id="et-password" placeholder="${_t('et_password_placeholder')}">
      </div>
      <button class="btn btn-primary" type="button" id="et-connect-btn">
        <i class="fa-solid fa-plug"></i> ${_t('connect_account')}
      </button>
      <span id="et-connect-msg" style="margin-left:1rem;color:var(--text-muted)"></span>
    </div>
  </div>
</div>`;
    },

    _sectionPosts(user) {
        if (CONFIG.DEMO_MODE) {
            const mockPosts = [
                { title: 'BMW N54 Stage 3 map feedback — 450whp target', time: '4m ago', replies: 4 },
                { title: 'EGR delete causing limp mode — SOLVED',         time: '2 days ago', replies: 23 },
                { title: 'Injector dead-time tables for ID1050x',          time: '1 week ago', replies: 9 }
            ];
            return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-comment-dots"></i> ${_t('my_posts')}</h2>
  <div class="card">
    <div class="card-body" style="padding:0">
      ${mockPosts.map(p => `
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-comment"></i></div>
        <div class="act-body">
          <a class="act-text" href="#" data-view="thread-view" data-thread="1">${p.title}</a>
          <span class="act-time">${p.time} · ${p.replies} ${_t('replies').toLowerCase()}</span>
        </div>
      </div>`).join('')}
    </div>
  </div>
</div>`;
        }

        // Production: no per-user post history endpoint yet — show empty state
        const postCount = parseInt(user.postCount, 10) || 0;
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-comment-dots"></i> ${_t('my_posts')}</h2>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:2.5rem 1.5rem;color:var(--text-muted)">
      <i class="fa-solid fa-comment-dots" style="font-size:2rem;margin-bottom:.75rem;display:block"></i>
      ${postCount > 0
        ? `<p>${_t('posts_made', { count: postCount.toLocaleString() })}</p>
           <a class="btn btn-ghost btn-sm" href="#" data-view="home" style="margin-top:.75rem">
             <i class="fa-solid fa-comments"></i> ${_t('browse_forum')}
           </a>`
        : `<p>${_t('no_posts_yet')}</p>
           <a class="btn btn-primary btn-sm" href="#" data-view="home" style="margin-top:.75rem">
             <i class="fa-solid fa-plus"></i> ${_t('start_discussion')}
           </a>`
      }
    </div>
  </div>
</div>`;
    },

    _sectionSettings(user) {
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-gear"></i> ${_t('profile_settings')}</h2>
  <div class="card">
    <div class="card-body">
      <form class="settings-form" id="settings-form">
        <div class="form-group">
          <label class="form-label">${_t('username')}</label>
          <input class="form-input" type="text" value="${user.username}" readonly>
          <span class="form-hint">${_t('username_no_change')}</span>
        </div>
        <div class="form-group">
          <label class="form-label">${_t('display_name')}</label>
          <input class="form-input" type="text" name="display_name" value="${user.username}">
        </div>
        <div class="form-group">
          <label class="form-label">${_t('email_address')}</label>
          <input class="form-input" type="email" name="email" value="${user.email || ''}">
        </div>
        <div class="form-group">
          <label class="form-label">${_t('location')}</label>
          <input class="form-input" type="text" name="location" placeholder="${_t('location_placeholder')}">
        </div>
        <div class="form-group">
          <label class="form-label">${_t('about_me')}</label>
          <textarea class="form-textarea" name="bio" rows="4" placeholder="${_t('about_me_placeholder')}"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">${_t('forum_signature')}</label>
          <textarea class="form-textarea" name="sig" rows="3" placeholder="${_t('signature_placeholder')}"></textarea>
        </div>
        <div style="text-align:right;margin-top:1rem">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> ${_t('save_changes')}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>`;
    },

    _sectionSecurity(user) {
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-shield-halved"></i> ${_t('security')}</h2>
  <div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">${_t('change_password')}</span></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">${_t('current_password')}</label>
        <input class="form-input" type="password" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">${_t('new_password')}</label>
        <input class="form-input" type="password" placeholder="${_t('min_8_chars')}">
      </div>
      <div class="form-group">
        <label class="form-label">${_t('confirm_password')}</label>
        <input class="form-input" type="password" placeholder="${_t('repeat_password')}">
      </div>
      <button class="btn btn-primary" type="button" id="change-pw-btn">
        <i class="fa-solid fa-lock"></i> ${_t('update_password')}
      </button>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">${_t('active_sessions')}</span></div>
    <div class="card-body">
      <div class="activity-item">
        <div class="act-icon green"><i class="fa-solid fa-desktop"></i></div>
        <div class="act-body">
          <span class="act-text">This device · Chrome / macOS</span>
          <span class="act-time act-current">${_t('current_session')}</span>
        </div>
      </div>
    </div>
  </div>
</div>`;
    },

    _bindEvents(user) {
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                Router.navigateTo(a.dataset.view, {});
            });
        });

        // section switching
        document.querySelectorAll('.dash-nav-item[data-section]').forEach(btn => {
            btn.addEventListener('click', () => {
                const section = btn.dataset.section;
                this.activeSection = section;
                document.querySelectorAll('.dash-nav-item').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('dash-content').innerHTML = this._renderSection(section, user);
                this._bindSectionEvents(section, user);
            });
        });

        document.getElementById('dash-logout')?.addEventListener('click', async () => {
            try { await API.logout(); } catch (e) { /* clear local state regardless */ }
            State.setUser(null);
            Toast.info(_t('signed_out'));
            Header.render();
            Router.navigateTo('home');
        });

        document.getElementById('change-pw-btn')?.addEventListener('click', () => {
            Toast.success(_t('password_updated'));
        });

        this._bindSectionEvents(this.activeSection, user);
    },

    _bindSectionEvents(section, user) {
        // settings form
        document.getElementById('settings-form')?.addEventListener('submit', async e => {
            e.preventDefault();
            const form = e.target;
            const btn  = form.querySelector('[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
            try {
                await API.updateProfile({
                    display_name: form.querySelector('[name="display_name"]')?.value ?? '',
                    email:        form.querySelector('[name="email"]')?.value ?? '',
                    location:     form.querySelector('[name="location"]')?.value ?? '',
                    bio:          form.querySelector('[name="bio"]')?.value ?? '',
                    signature:    form.querySelector('[name="sig"]')?.value ?? '',
                });
                Toast.success(_t('profile_saved'));
            } catch (err) {
                Toast.error(err.message ?? _t('could_not_save_settings'));
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> ${_t('save_changes')}`;
            }
        });

        // change password
        document.getElementById('change-pw-btn')?.addEventListener('click', () => {
            Toast.success(_t('password_updated'));
        });

        // HWID reset
        document.querySelectorAll('.hwid-reset-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const licId  = btn.dataset.licId;
                const nonce  = user.nonces?.hwid_reset ?? '';
                const warnEl = document.getElementById(`hwid-warning-${licId}`);
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Resetting…';
                btn.disabled = true;
                try {
                    await API.resetHwid(licId, nonce);
                    Toast.success(_t('hwid_reset_success'));
                    if (warnEl) warnEl.textContent = _t('hwid_reset_cooldown');
                } catch (err) {
                    Toast.error(err.message ?? _t('could_not_reset_hwid'));
                    if (warnEl) warnEl.textContent = err.message ?? 'Reset failed.';
                } finally {
                    btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Reset HWID';
                    btn.disabled = false;
                }
            });
        });

        // Easy Tuner connect
        document.getElementById('et-connect-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('et-connect-btn');
            const msg = document.getElementById('et-connect-msg');
            const email = document.getElementById('et-email')?.value?.trim();
            const pw    = document.getElementById('et-password')?.value;
            if (!email || !pw) {
                if (msg) msg.textContent = _t('please_fill_both');
                return;
            }
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('connecting')}`;
            if (msg) msg.textContent = '';
            try {
                const res = await API.etConnect(email, pw);
                Toast.success(res.message || _t('et_connected'));
                // Refresh user data and re-render section.
                const userData = await API.getUserData();
                if (userData?.user) {
                    State.setUser(userData.user);
                    document.getElementById('dash-content').innerHTML = this._renderSection('licenses', userData.user);
                    this._bindSectionEvents('licenses', userData.user);
                }
            } catch (err) {
                Toast.error(err.message || _t('connection_failed'));
                if (msg) msg.textContent = err.message || _t('connection_failed');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-plug"></i> ${_t('connect_account')}`;
            }
        });

        // Easy Tuner disconnect
        document.getElementById('et-disconnect-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('et-disconnect-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disconnecting...';
            try {
                await API.etDisconnect();
                Toast.info(_t('et_disconnected'));
                const userData = await API.getUserData();
                if (userData?.user) {
                    State.setUser(userData.user);
                    document.getElementById('dash-content').innerHTML = this._renderSection('licenses', userData.user);
                    this._bindSectionEvents('licenses', userData.user);
                }
            } catch (err) {
                Toast.error(err.message || _t('could_not_disconnect'));
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-plug-circle-xmark"></i> ${_t('disconnect')}`;
            }
        });

        // Easy Tuner refresh
        document.getElementById('et-refresh-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('et-refresh-btn');
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('checking')}`;
            try {
                const res = await API.etCheck();
                const et = res.easyTuner || {};
                const badge = document.getElementById('et-status-badge');
                if (badge) {
                    badge.textContent = et.active ? _t('active') : _t('inactive');
                    badge.className = `badge ${et.active ? 'badge-active' : 'badge-pinned'}`;
                }
                Toast.success(_t('license_refreshed'));
            } catch (err) {
                Toast.error(err.message || _t('could_not_check_license'));
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-arrows-rotate"></i> ${_t('refresh_status')}`;
            }
        });

        // posts section links
        document.querySelectorAll('[data-view="thread-view"]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                Router.navigateTo('thread-view', { threadId: 1 });
            });
        });
    }
};
