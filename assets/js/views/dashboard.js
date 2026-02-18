// Dashboard view — user profile, licenses, settings
const DashboardView = {
    SECTIONS: [
        { id: 'overview',  icon: 'fa-gauge',             label: 'Overview' },
        { id: 'licenses',  icon: 'fa-key',               label: 'My Licenses' },
        { id: 'posts',     icon: 'fa-comment-dots',      label: 'My Posts' },
        { id: 'settings',  icon: 'fa-gear',              label: 'Settings' },
        { id: 'security',  icon: 'fa-shield-halved',     label: 'Security' }
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
      <h2 style="margin-bottom:.5rem">Sign In Required</h2>
      <p style="color:var(--text-muted);margin-bottom:1.5rem">Please sign in to access your dashboard.</p>
      <button class="btn btn-primary" type="button" id="dash-login-btn">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
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
    <a href="#" data-view="home">Home</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>Dashboard</span>
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
            <span class="pstat-lbl">Posts</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.reputation || 0}</span>
            <span class="pstat-lbl">Reputation</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.joined || '—'}</span>
            <span class="pstat-lbl">Joined</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.licenses?.length || 0}</span>
            <span class="pstat-lbl">Licenses</span>
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
        <i class="fa-solid ${s.icon}"></i> ${s.label}
      </button>`).join('')}
      <div class="dash-nav-sep"></div>
      <button class="dash-nav-item dash-nav-item--danger" type="button" id="dash-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Log Out
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
  <h2 class="dash-title"><i class="fa-solid fa-gauge"></i> Overview</h2>
  <div class="overview-grid">
    <div class="ov-card">
      <i class="fa-solid fa-comment-dots ov-icon blue"></i>
      <div class="ov-val">${posts}</div>
      <div class="ov-lbl">Total Posts</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-star ov-icon yellow"></i>
      <div class="ov-val">${rep}</div>
      <div class="ov-lbl">Reputation</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-key ov-icon green"></i>
      <div class="ov-val">${lics}</div>
      <div class="ov-lbl">Active Licenses</div>
    </div>
  </div>

  <div class="card" style="margin-top:1.5rem">
    <div class="card-header"><span class="card-title">Account Summary</span></div>
    <div class="card-body">
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-user"></i></div>
        <div class="act-body">
          <span class="act-text">Member since <strong>${user.joined || '—'}</strong></span>
        </div>
      </div>
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-comment-dots"></i></div>
        <div class="act-body">
          <span class="act-text"><strong>${posts}</strong> post${posts === 1 ? '' : 's'} contributed to the forum</span>
        </div>
      </div>
      <div class="activity-item">
        <div class="act-icon yellow"><i class="fa-solid fa-star"></i></div>
        <div class="act-body">
          <span class="act-text"><strong>${rep}</strong> reputation point${rep === 1 ? '' : 's'} earned</span>
        </div>
      </div>
      ${lics > 0 ? `
      <div class="activity-item">
        <div class="act-icon green"><i class="fa-solid fa-key"></i></div>
        <div class="act-body">
          <span class="act-text"><strong>${lics}</strong> active license${lics === 1 ? '' : 's'}</span>
        </div>
      </div>` : ''}
    </div>
  </div>
</div>`;
    },

    _sectionLicenses(user) {
        const licenses = user.licenses || [];
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-key"></i> My Licenses</h2>
  ${licenses.length === 0 ? `
  <div class="empty-state">
    <i class="fa-solid fa-key-skeleton"></i>
    <p>No active licenses found on your account.</p>
  </div>` : licenses.map(lic => `
  <div class="license-card${lic.status === 'active' ? ' active-license' : ''}">
    <div class="lic-header">
      <div class="lic-name">
        <i class="fa-solid fa-certificate"></i>
        EsyTuner Pro License
      </div>
      <span class="badge ${lic.status === 'active' ? 'badge-active' : 'badge-pinned'}">
        ${lic.status === 'active' ? 'Active' : lic.status.charAt(0).toUpperCase() + lic.status.slice(1)}
      </span>
    </div>
    <div class="lic-body">
      <div class="lic-row">
        <span class="lic-lbl">License Key</span>
        <span class="lic-val mono">${lic.key}</span>
      </div>
      <div class="lic-row">
        <span class="lic-lbl">Expires</span>
        <span class="lic-val">${lic.expires_at ?? 'Never'}</span>
      </div>
    </div>
    <div class="lic-footer">
      <button class="btn btn-secondary btn-sm hwid-reset-btn" type="button" data-lic="${lic.key}">
        <i class="fa-solid fa-rotate"></i> Reset HWID
      </button>
      <span class="hwid-warning" id="hwid-warning-${lic.key}"></span>
    </div>
  </div>`).join('')}
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
  <h2 class="dash-title"><i class="fa-solid fa-comment-dots"></i> My Posts</h2>
  <div class="card">
    <div class="card-body" style="padding:0">
      ${mockPosts.map(p => `
      <div class="activity-item">
        <div class="act-icon blue"><i class="fa-solid fa-comment"></i></div>
        <div class="act-body">
          <a class="act-text" href="#" data-view="thread-view" data-thread="1">${p.title}</a>
          <span class="act-time">${p.time} · ${p.replies} replies</span>
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
  <h2 class="dash-title"><i class="fa-solid fa-comment-dots"></i> My Posts</h2>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:2.5rem 1.5rem;color:var(--text-muted)">
      <i class="fa-solid fa-comment-dots" style="font-size:2rem;margin-bottom:.75rem;display:block"></i>
      ${postCount > 0
        ? `<p>You have made <strong>${postCount.toLocaleString()}</strong> post${postCount === 1 ? '' : 's'} on the forum.</p>
           <a class="btn btn-ghost btn-sm" href="#" data-view="home" style="margin-top:.75rem">
             <i class="fa-solid fa-comments"></i> Browse Forum
           </a>`
        : `<p>You haven\'t posted anything yet.</p>
           <a class="btn btn-primary btn-sm" href="#" data-view="home" style="margin-top:.75rem">
             <i class="fa-solid fa-plus"></i> Start a Discussion
           </a>`
      }
    </div>
  </div>
</div>`;
    },

    _sectionSettings(user) {
        return `
<div class="dash-section">
  <h2 class="dash-title"><i class="fa-solid fa-gear"></i> Profile Settings</h2>
  <div class="card">
    <div class="card-body">
      <form class="settings-form" id="settings-form">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" type="text" value="${user.username}" readonly>
          <span class="form-hint">Username cannot be changed.</span>
        </div>
        <div class="form-group">
          <label class="form-label">Display Name</label>
          <input class="form-input" type="text" name="display_name" value="${user.username}">
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input class="form-input" type="email" name="email" value="${user.email || ''}">
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input class="form-input" type="text" name="location" placeholder="e.g. Germany">
        </div>
        <div class="form-group">
          <label class="form-label">About Me</label>
          <textarea class="form-textarea" name="bio" rows="4" placeholder="Tell the community about yourself…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Forum Signature</label>
          <textarea class="form-textarea" name="sig" rows="3" placeholder="Your signature shown below posts…"></textarea>
        </div>
        <div style="text-align:right;margin-top:1rem">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
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
  <h2 class="dash-title"><i class="fa-solid fa-shield-halved"></i> Security</h2>
  <div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">Change Password</span></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input class="form-input" type="password" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-input" type="password" placeholder="Min. 8 characters">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input class="form-input" type="password" placeholder="Repeat new password">
      </div>
      <button class="btn btn-primary" type="button" id="change-pw-btn">
        <i class="fa-solid fa-lock"></i> Update Password
      </button>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Active Sessions</span></div>
    <div class="card-body">
      <div class="activity-item">
        <div class="act-icon green"><i class="fa-solid fa-desktop"></i></div>
        <div class="act-body">
          <span class="act-text">This device · Chrome / macOS</span>
          <span class="act-time act-current">Current session</span>
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

        document.getElementById('dash-logout')?.addEventListener('click', () => {
            State.setUser(null);
            Toast.info('You have been signed out.');
            Header.render();
            Router.navigateTo('home');
        });

        document.getElementById('change-pw-btn')?.addEventListener('click', () => {
            Toast.success('Password updated successfully.');
        });

        this._bindSectionEvents(this.activeSection, user);
    },

    _bindSectionEvents(section, user) {
        // settings form
        document.getElementById('settings-form')?.addEventListener('submit', e => {
            e.preventDefault();
            Toast.success('Profile settings saved!');
        });

        // change password
        document.getElementById('change-pw-btn')?.addEventListener('click', () => {
            Toast.success('Password updated successfully.');
        });

        // HWID reset
        document.querySelectorAll('[id^="hwid-reset-btn"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const licId = btn.dataset.lic;
                if (!State.canResetHWID()) {
                    const hrs = Math.ceil(CONFIG.FORUM.HWID_RESET_COOLDOWN / 3600000);
                    Toast.error(`HWID reset is on cooldown. Please wait ${hrs}h.`);
                    document.getElementById(`hwid-warning-${licId}`).textContent = `On cooldown — try again later`;
                    return;
                }
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Resetting…';
                btn.disabled = true;
                setTimeout(() => {
                    State.performHWIDReset();
                    Toast.success('HWID reset successfully! New HWID will be assigned on next launch.');
                    btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Reset HWID';
                    btn.disabled = false;
                    document.getElementById(`hwid-warning-${licId}`).textContent = `Reset used — next reset in 24h`;
                }, 1200);
            });
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
