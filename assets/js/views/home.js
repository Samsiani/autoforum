// Home view — forum category index
const HomeView = {
    CATEGORIES: [
        {
            id: 1, icon: 'fa-screwdriver-wrench', color: 'cat-blue',
            name: 'ECU Tuning & Remapping',
            desc: 'Maps, custom tunes, dyno results and ECU strategies.',
            threads: 1842, posts: 29_410
        },
        {
            id: 2, icon: 'fa-microchip', color: 'cat-purple',
            name: 'Software & Tools',
            desc: 'EsyTuner software releases, updates, tips and tricks.',
            threads: 934, posts: 11_280
        },
        {
            id: 3, icon: 'fa-circle-question', color: 'cat-yellow',
            name: 'Technical Support',
            desc: 'Stuck? Get help from the community and staff.',
            threads: 2_105, posts: 18_660
        },
        {
            id: 4, icon: 'fa-car-side', color: 'cat-green',
            name: 'Vehicle Specific',
            desc: 'Make & model threads — BMW, Mercedes, VAG, JDM and more.',
            threads: 3_318, posts: 47_002
        },
        {
            id: 5, icon: 'fa-book-open', color: 'cat-red',
            name: 'Guides & Tutorials',
            desc: 'Community-written write-ups and how-to guides.',
            threads: 476, posts: 5_840
        },
        {
            id: 6, icon: 'fa-comments', color: 'cat-teal',
            name: 'General Discussion',
            desc: 'Off-topic talk, introductions and community chat.',
            threads: 789, posts: 12_950
        }
    ],

    TOP_USERS: [
        { rank: 1, name: 'DieselKing', posts: 4821, rep: 3201, avatar: 'https://i.pravatar.cc/40?img=1' },
        { rank: 2, name: 'TurboFox',   posts: 3190, rep: 2847, avatar: 'https://i.pravatar.cc/40?img=2' },
        { rank: 3, name: 'ECU_Pro',    posts: 2944, rep: 2103, avatar: 'https://i.pravatar.cc/40?img=3' },
        { rank: 4, name: 'Boost_Guru', posts: 1822, rep: 1640, avatar: 'https://i.pravatar.cc/40?img=4' },
        { rank: 5, name: 'TuneMaster', posts: 1560, rep: 1410, avatar: 'https://i.pravatar.cc/40?img=5' }
    ],

    STATS: { members: 48_230, posts: 125_142, threads: 9_464, online: 73 },

    LATEST: [
        { title: 'BMW N54 stage 3 map — feedback wanted',     user: 'TurboFox',   time: '4m ago' },
        { title: 'v3.2.1 released — changelog inside',         user: 'Staff',      time: '17m ago' },
        { title: 'EGR delete safe on Euro 5?',                 user: 'vag_tech',   time: '1h ago' },
        { title: 'HWID reset not working — ticket #8821',      user: 'newuser99',  time: '2h ago' },
        { title: 'Golf R Stage 2 — Dyno results 385whp',       user: 'ECU_Pro',    time: '3h ago' }
    ],

    async render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        // ── Data loading ──────────────────────────────────────────────
        let categories, stats, topUsers, latest;

        if (CONFIG.DEMO_MODE) {
            categories = this.CATEGORIES.map(c => ({
                id:          c.id,
                name:        c.name,
                description: c.desc,
                icon:        c.icon,        // FA class e.g. 'fa-screwdriver-wrench'
                color:       c.color,       // CSS class e.g. 'cat-blue'
                topic_count: c.threads,
                post_count:  c.posts,
                _demo:       true
            }));
            stats    = this.STATS;
            topUsers = this.TOP_USERS;
            latest   = this.LATEST;
        } else {
            // Fetch categories + home stats in parallel
            const [apiCats, homeStats] = await Promise.all([
                API.getCategories(),
                API.getHomeStats()
            ]);

            categories = (apiCats || []).map(c => ({
                id:          c.id,
                name:        c.name,
                description: c.description ?? '',
                icon:        c.icon        ?? '',
                color:       '',           // DB stores hex colour, not a CSS class
                _hex:        c.color       ?? '',
                topic_count: c.topic_count ?? 0,
                post_count:  c.post_count  ?? 0,
                _demo:       false
            }));

            if (homeStats) {
                stats = {
                    members: homeStats.members ?? 0,
                    posts:   homeStats.posts   ?? 0,
                    threads: homeStats.threads ?? 0,
                    online:  homeStats.online  ?? 0,
                };
                topUsers = (homeStats.top_contributors || []).map(u => ({
                    rank:   u.rank,
                    id:     u.id,
                    name:   u.name,
                    posts:  u.posts,
                    rep:    u.rep,
                    avatar: u.avatar,
                }));
                latest = (homeStats.latest_posts || []).map(p => ({
                    topicId: p.topic_id,
                    title:   p.title,
                    user:    p.author_name,
                    userId:  p.author_id,
                    time:    p.time,
                }));
            } else {
                stats    = { members: 0, posts: 0, threads: 0, online: 0 };
                topUsers = [];
                latest   = [];
            }
        }

        // ── Icon helper: FA class OR emoji/text fallback ───────────────
        const iconHtml = cat => {
            if (cat._demo) {
                return `<i class="fa-solid ${cat.icon}"></i>`;
            }
            // Live: icon field stores an FA class name (e.g. 'fa-car-side')
            return cat.icon
                ? `<i class="fa-solid ${cat.icon}"></i>`
                : `<i class="fa-solid fa-folder-open"></i>`;
        };

        // ── Color helper: CSS class OR inline hex ──────────────────────
        const colorStyle = cat => {
            if (cat._demo) return '';   // colour comes from CSS class on the parent
            return cat._hex ? `style="background:${cat._hex}22;color:${cat._hex}"` : '';
        };

        const colorCls = cat => cat._demo ? cat.color : 'cat-live';

        // ── Stats sidebar ──────────────────────────────────────────────
        const onlineTxt = stats.online !== null
            ? `<strong class="text-success">${this._fmt(stats.online)}</strong>`
            : `<strong class="text-success">—</strong>`;

        const membersTxt = (stats.members !== null && stats.members !== undefined)
            ? `<strong>${this._fmt(stats.members)}</strong>`
            : `<strong>—</strong>`;

        // ── Top Contributors sidebar (demo only for now) ───────────────
        const topUsersHtml = topUsers.length
            ? topUsers.map(u => `
          <div class="top-user">
            <span class="top-rank rank-${u.rank <= 3 ? u.rank : 'n'}">${u.rank}</span>
            <img src="${u.avatar}" alt="${u.name}" class="top-avatar">
            <div class="top-info">
              <a class="top-name" href="#" data-view="user-profile" data-id="${u.id ?? 0}">${u.name}</a>
              <span class="top-meta">${this._fmt(u.posts)} posts · ${this._fmt(u.rep)} rep</span>
            </div>
          </div>`).join('')
            : '<p class="sidebar-empty">No data available.</p>';

        // ── Latest Posts sidebar (demo only for now) ───────────────────
        const latestHtml = latest.length
            ? latest.map(p => `
          <div class="latest-item">
            <a class="latest-title" href="#" data-view="thread-view" data-thread="${p.topicId}">${p.title}</a>
            <span class="latest-meta">by <a href="#" data-view="user-profile" data-id="${p.userId ?? 0}"><strong>${p.user}</strong></a> · ${p.time}</span>
          </div>`).join('')
            : '<p class="sidebar-empty">No recent posts.</p>';

        // ── Render ─────────────────────────────────────────────────────
        el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="forum-layout">

    <!-- CATEGORIES -->
    <main>
      <div class="section-head">
        <h2 class="section-title"><i class="fa-solid fa-layer-group"></i> Forum Categories</h2>
        ${State.isAuthenticated() ? `
        <a class="btn btn-primary btn-sm" href="#" data-view="create-topic">
          <i class="fa-solid fa-plus"></i> New Topic
        </a>` : ''}
      </div>

      <div class="categories-list">
        ${categories.map(cat => `
        <div class="category-card" data-category="${cat.id}" role="button" tabindex="0">
          <div class="category-inner">
            <div class="category-icon ${colorCls(cat)}" ${colorStyle(cat)}>
              ${iconHtml(cat)}
            </div>
            <div class="category-info">
              <a class="category-name" href="#" data-view="thread-list" data-category="${cat.id}">${cat.name}</a>
              <p class="category-desc">${cat.description}</p>
            </div>
            <div class="category-counts">
              <div class="count-item">
                <span class="count-val">${this._fmt(cat.topic_count)}</span>
                <span class="count-lbl">Threads</span>
              </div>
              <div class="count-item">
                <span class="count-val">${this._fmt(cat.post_count)}</span>
                <span class="count-lbl">Posts</span>
              </div>
            </div>
          </div>
        </div>`).join('')}
      </div>
    </main>

    <!-- SIDEBAR -->
    <aside class="sidebar">

      <div class="sidebar-card">
        <div class="sidebar-head"><i class="fa-solid fa-chart-simple"></i> Forum Stats</div>
        <div class="sidebar-body">
          <div class="stats-grid">
            <div class="stat-row"><span class="stat-lbl"><i class="fa-solid fa-users"></i> Members</span>${membersTxt}</div>
            <div class="stat-row"><span class="stat-lbl"><i class="fa-solid fa-comment-dots"></i> Posts</span><strong>${this._fmt(stats.posts)}</strong></div>
            <div class="stat-row"><span class="stat-lbl"><i class="fa-solid fa-list-ul"></i> Threads</span><strong>${this._fmt(stats.threads)}</strong></div>
            <div class="stat-row online"><span class="stat-lbl"><i class="fa-solid fa-circle fa-xs"></i> Online</span>${onlineTxt}</div>
          </div>
        </div>
      </div>

      <div class="sidebar-card">
        <div class="sidebar-head"><i class="fa-solid fa-trophy"></i> Top Contributors</div>
        <div class="sidebar-body">
          ${topUsersHtml}
        </div>
      </div>

      <div class="sidebar-card">
        <div class="sidebar-head"><i class="fa-solid fa-clock-rotate-left"></i> Latest Posts</div>
        <div class="sidebar-body">
          ${latestHtml}
        </div>
      </div>

      ${!State.isAuthenticated() ? `
      <div class="sidebar-card cta-card">
        <div class="sidebar-head"><i class="fa-solid fa-bolt"></i> Join the Community</div>
        <div class="sidebar-body cta-body">
          <p>Register free to post, share tunes and get support.</p>
          <button class="btn btn-primary" type="button" id="join-btn">
            <i class="fa-solid fa-user-plus"></i> Create Account
          </button>
        </div>
      </div>` : ''}

    </aside>
  </div>
</div>`;

        this._bindEvents();
    },

    _bindEvents() {
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const view = a.dataset.view;
                const params = {};
                if (a.dataset.category) params.id = parseInt(a.dataset.category, 10);
                if (a.dataset.thread)   params.id = parseInt(a.dataset.thread, 10);
                if (a.dataset.id)       params.id = parseInt(a.dataset.id, 10);
                Router.navigateTo(view, params);
            });
        });

        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', () => {
                Router.navigateTo('thread-list', { id: parseInt(card.dataset.category, 10) });
            });
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter') Router.navigateTo('thread-list', { id: parseInt(card.dataset.category, 10) });
            });
        });

        document.getElementById('join-btn')?.addEventListener('click', () => {
            Modal.showAuthTab('register');
        });
    },

    _fmt(n) {
        return n >= 1000 ? (n / 1000).toFixed(1).replace('.0','') + 'k' : n;
    }
};
