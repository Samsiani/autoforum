// Header component
const Header = {
    render() {
        const el = document.getElementById('main-header');
        if (!el) return;
        const user = State.currentUser;

        el.innerHTML = `
<div class="header-inner">
  <a class="logo" href="#" data-view="home">
    <i class="fa-solid fa-gauge-high"></i>
    <span>${_t('site_name').replace(' ', ' <em>') + '</em>'}</span>
  </a>

  <nav class="main-nav">
    <a class="nav-link${State.currentView === 'home' ? ' active' : ''}" href="#" data-view="home">
      <i class="fa-solid fa-house-chimney"></i> ${_t('nav_home')}
    </a>
    <a class="nav-link" href="#" data-view="thread-list" data-category="1">
      <i class="fa-solid fa-screwdriver-wrench"></i> ${_t('nav_ecu_tuning')}
    </a>
    <a class="nav-link" href="#" data-view="thread-list" data-category="2">
      <i class="fa-solid fa-microchip"></i> ${_t('nav_software')}
    </a>
    <a class="nav-link" href="#" data-view="thread-list" data-category="3">
      <i class="fa-solid fa-circle-question"></i> ${_t('nav_support')}
    </a>
  </nav>

  <div class="header-actions">
    ${Header._langSwitcher()}
    ${user ? `
    <a class="btn btn-primary btn-sm" href="#" data-view="create-topic">
      <i class="fa-solid fa-plus"></i> ${_t('new_topic')}
    </a>
    <div class="user-menu" id="user-menu">
      <button class="user-btn" id="user-btn" type="button" aria-expanded="false">
        <img src="${user.avatar}" alt="${user.username}" class="user-avatar-sm">
        <span>${user.username}</span>
        <i class="fa-solid fa-chevron-down fa-xs"></i>
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <div class="udrop-head">
          <img src="${user.avatar}" alt="${user.username}" class="udrop-avatar">
          <div>
            <div class="udrop-name">${user.username}</div>
            <div class="udrop-role">${user.role}</div>
          </div>
        </div>
        <div class="udrop-body">
          <a class="udrop-item" href="#" data-view="dashboard">
            <i class="fa-solid fa-gauge"></i> ${_t('dashboard')}
          </a>
          <a class="udrop-item" href="#" data-view="dashboard">
            <i class="fa-solid fa-user-pen"></i> ${_t('edit_profile')}
          </a>
          <a class="udrop-item" href="#" data-view="dashboard">
            <i class="fa-solid fa-key"></i> ${_t('my_licenses')}
          </a>
          <div class="udrop-sep"></div>
          <button class="udrop-item udrop-item--danger" type="button" id="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> ${_t('log_out')}
          </button>
        </div>
      </div>
    </div>
    ` : `
    <button class="btn btn-ghost btn-sm" type="button" id="login-btn">
      <i class="fa-solid fa-right-to-bracket"></i> ${_t('sign_in')}
    </button>
    <button class="btn btn-primary btn-sm" type="button" id="register-btn">
      <i class="fa-solid fa-user-plus"></i> ${_t('register')}
    </button>
    `}
  </div>
</div>`;

        this._bindEvents();
    },

    _langSwitcher() {
        const cfg = typeof AF_DATA !== 'undefined' ? AF_DATA : null;
        const current = cfg?.lang || 'ka';
        const langs = cfg?.languages || { ka: 'ქართული', en: 'English' };
        const flags = { ka: '🇬🇪', en: '🇬🇧' };
        const other = current === 'ka' ? 'en' : 'ka';
        return `
        <button class="btn btn-ghost btn-sm af-lang-btn" type="button" id="lang-switch-btn"
                title="${langs[other]}" data-lang="${other}"
                style="font-size:.85rem;padding:4px 10px;min-width:auto;gap:4px">
            <span style="font-size:1.1rem">${flags[other]}</span> ${other.toUpperCase()}
        </button>`;
    },

    _bindEvents() {
        // nav links
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const view = a.dataset.view;
                const params = a.dataset.category ? { categoryId: parseInt(a.dataset.category) } : {};
                Router.navigateTo(view, params);
            });
        });

        // auth buttons
        document.getElementById('login-btn')?.addEventListener('click', () => {
            Modal.show('auth-modal');
            document.querySelector('[data-tab="login"]')?.click();
        });
        document.getElementById('register-btn')?.addEventListener('click', () => {
            Modal.show('auth-modal');
            document.querySelector('[data-tab="register"]')?.click();
        });

        // user dropdown toggle
        document.getElementById('user-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = document.getElementById('user-dropdown');
            const btn = document.getElementById('user-btn');
            const isOpen = dd?.classList.toggle('show');
            btn?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // close dropdown on outside click
        document.addEventListener('click', () => {
            document.getElementById('user-dropdown')?.classList.remove('show');
            document.getElementById('user-btn')?.setAttribute('aria-expanded', 'false');
        });

        // language switcher
        document.getElementById('lang-switch-btn')?.addEventListener('click', (e) => {
            const lang = e.currentTarget.dataset.lang;
            document.cookie = `af_lang=${lang};path=/;max-age=${365*24*60*60}`;
            window.location.reload();
        });

        // logout
        document.getElementById('logout-btn')?.addEventListener('click', async () => {
            try {
                await API.logout();
            } catch (_) {
                // Proceed with client-side cleanup even if server logout fails.
            }
            State.setUser(null);
            Toast.info(_t('signed_out'));
            setTimeout( () => window.location.reload(), 600 );
        });
    }
};
