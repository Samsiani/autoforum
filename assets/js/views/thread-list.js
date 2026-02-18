// Thread-list view — threads inside a category
const ThreadListView = {
    CATEGORIES: {
        1: { name: 'ECU Tuning & Remapping', icon: 'fa-screwdriver-wrench' },
        2: { name: 'Software & Tools',        icon: 'fa-microchip' },
        3: { name: 'Technical Support',        icon: 'fa-circle-question' },
        4: { name: 'Vehicle Specific',         icon: 'fa-car-side' },
        5: { name: 'Guides & Tutorials',       icon: 'fa-book-open' },
        6: { name: 'General Discussion',       icon: 'fa-comments' }
    },

    THREADS: [
        {
            id: 1, pinned: true, locked: false, hot: false, solved: false,
            prefix: 'guide', title: '📌 [GUIDE] Complete EsyTuner Pro setup from scratch',
            author: 'Staff', avatar: 'https://i.pravatar.cc/32?img=11',
            replies: 45, views: 12840, lastUser: 'Staff', lastTime: '2h ago'
        },
        {
            id: 2, pinned: true, locked: true, hot: false, solved: false,
            prefix: null, title: '📌 Forum Rules & Guidelines — Read Before Posting',
            author: 'Admin', avatar: 'https://i.pravatar.cc/32?img=10',
            replies: 0, views: 8920, lastUser: 'Admin', lastTime: '1d ago'
        },
        {
            id: 3, pinned: false, locked: false, hot: true, solved: false,
            prefix: 'help', title: 'BMW N54 Stage 3 map feedback — 450whp target',
            author: 'TurboFox', avatar: 'https://i.pravatar.cc/32?img=2',
            replies: 87, views: 3401, lastUser: 'DieselKing', lastTime: '4m ago'
        },
        {
            id: 4, pinned: false, locked: false, hot: false, solved: true,
            prefix: 'solved', title: 'EGR delete causing limp mode — SOLVED',
            author: 'vag_tech', avatar: 'https://i.pravatar.cc/32?img=5',
            replies: 23, views: 1890, lastUser: 'ECU_Pro', lastTime: '1h ago'
        },
        {
            id: 5, pinned: false, locked: false, hot: false, solved: false,
            prefix: 'firmware', title: 'EsyTuner v3.2.1 — patch notes & download',
            author: 'Staff', avatar: 'https://i.pravatar.cc/32?img=11',
            replies: 14, views: 2210, lastUser: 'newuser99', lastTime: '17m ago'
        },
        {
            id: 6, pinned: false, locked: false, hot: false, solved: false,
            prefix: 'discussion', title: 'Is MAF scaling better than speed density for high boost?',
            author: 'Boost_Guru', avatar: 'https://i.pravatar.cc/32?img=4',
            replies: 31, views: 980, lastUser: 'TuneMaster', lastTime: '3h ago'
        },
        {
            id: 7, pinned: false, locked: false, hot: true, solved: false,
            prefix: 'help', title: 'HWID reset — getting "cooldown active" error',
            author: 'newuser99', avatar: 'https://i.pravatar.cc/32?img=9',
            replies: 9, views: 440, lastUser: 'Staff', lastTime: '2h ago'
        },
        {
            id: 8, pinned: false, locked: false, hot: false, solved: true,
            prefix: 'solved', title: 'Golf 7 R — ignition timing knock issue resolved',
            author: 'ECU_Pro', avatar: 'https://i.pravatar.cc/32?img=3',
            replies: 18, views: 1102, lastUser: 'vag_tech', lastTime: '5h ago'
        }
    ],

    PREFIX_MAP: {
        help:       { label: 'Help',       cls: 'badge-help' },
        solved:     { label: 'Solved',     cls: 'badge-solved' },
        discussion: { label: 'Discussion', cls: 'badge-discussion' },
        firmware:   { label: 'Firmware',   cls: 'badge-firmware' },
        guide:      { label: 'Guide',      cls: 'badge-guide' }
    },

    // Current navigation state (set during render, used by reply refresh)
    _currentParams: {},

    async render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        this._currentParams = params;

        // Router stores category id as params.id (e.g. #thread-list?id=3).
        // Fall back to params.categoryId for any legacy call-sites.
        const catId = parseInt(params.id ?? params.categoryId ?? 0, 10);
        const sort  = params.sort || 'latest';
        const page  = parseInt(params.page ?? 1, 10);

        // ── Data loading ───────────────────────────────────────────────
        let cat, threads, totalPages;

        if (CONFIG.DEMO_MODE) {
            cat         = this.CATEGORIES[catId] || this.CATEGORIES[1];
            threads     = this.THREADS;
            totalPages  = 1;
        } else {
            try {
                // Resolve real category name/icon from the categories list
                const allCats = await API.getCategories();
                const found   = (allCats || []).find(c => c.id === catId);
                cat = found
                    ? { name: found.name, icon: found.icon || '' }
                    : { name: 'Category', icon: '' };

                // Fetch topics for this category
                const result = await API.getTopics({ categoryId: catId, page, sort });
                // Backend returns { topics: [...], total_pages: n } OR just an array
                if (Array.isArray(result)) {
                    threads    = result;
                    totalPages = 1;
                } else {
                    threads    = result?.topics      ?? [];
                    totalPages = result?.total_pages ?? 1;
                }
            } catch (err) {
                el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="empty-state">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <h3>Could not load threads</h3>
    <p>${err?.message ?? 'An unexpected error occurred.'}</p>
    <button class="btn btn-primary" type="button" onclick="Router.navigateTo('home')">
      <i class="fa-solid fa-house"></i> Go Home
    </button>
  </div>
</div>`;
                return;
            }
        }

        // ── Category icon HTML ─────────────────────────────────────────
        const iconHtml = CONFIG.DEMO_MODE
            ? `<i class="fa-solid ${cat.icon}"></i>`
            : cat.icon
                ? `<i class="fa-solid ${cat.icon}"></i>`
                : `<i class="fa-solid fa-folder-open"></i>`;

        // ── Thread rows ────────────────────────────────────────────────
        let rowsHtml;
        if (threads.length === 0) {
            rowsHtml = `
<div class="thread-row thread-empty">
  <div style="grid-column:1/-1;padding:3rem;text-align:center;color:var(--text-muted)">
    <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:.75rem"></i>
    No topics found in this category yet.
    ${State.isAuthenticated() ? `<br><a href="#" data-view="create-topic" style="margin-top:.5rem;display:inline-block">Be the first to post!</a>` : ''}
  </div>
</div>`;
        } else {
            rowsHtml = threads.map(t => this._row(t)).join('');
        }

        // ── Pagination ─────────────────────────────────────────────────
        const paginationHtml = this._pagination(page, totalPages, catId, sort);

        // ── Render ─────────────────────────────────────────────────────
        el.innerHTML = `
<div class="page-wrap fade-up">
  <!-- breadcrumb -->
  <nav class="breadcrumbs">
    <a href="#" data-view="home">Home</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>${cat.name}</span>
  </nav>

  <!-- list header -->
  <div class="thread-list-header">
    <div class="tlh-left">
      <h1 class="tlh-title">
        ${iconHtml} ${cat.name}
      </h1>
      <span class="tlh-count">${threads.length} thread${threads.length !== 1 ? 's' : ''}</span>
    </div>
    <div class="tlh-actions">
      <select class="form-select form-select-sm" id="sort-select">
        <option value="latest"  ${sort === 'latest'  ? 'selected' : ''}>Latest Activity</option>
        <option value="newest"  ${sort === 'newest'  ? 'selected' : ''}>Newest First</option>
        <option value="hot"     ${sort === 'hot'     ? 'selected' : ''}>Most Active</option>
        <option value="views"   ${sort === 'views'   ? 'selected' : ''}>Most Viewed</option>
      </select>
      ${State.isAuthenticated() ? `
      <a class="btn btn-primary btn-sm" href="#" data-view="create-topic" data-id="${catId}">
        <i class="fa-solid fa-plus"></i> New Topic
      </a>` : `
      <button class="btn btn-primary btn-sm" type="button" id="post-login-btn">
        <i class="fa-solid fa-plus"></i> New Topic
      </button>`}
    </div>
  </div>

  <!-- table -->
  <div class="thread-table">
    <div class="thread-table-head">
      <div></div>
      <div>Thread</div>
      <div class="text-center">Last Reply</div>
      <div class="text-center">Replies</div>
      <div class="text-center">Views</div>
    </div>

    ${rowsHtml}
  </div>

  ${paginationHtml}
</div>`;

        this._bindEvents(catId, sort, cat.name ?? '');
    },

    /**
     * Build a single thread row.
     * Accepts both the demo shape and the real API shape:
     *   Demo:  { id, pinned, locked, hot, solved, prefix, title, author, avatar,
     *            replies, views, lastUser, lastTime }
     *   Live:  { id, is_pinned, is_locked, reply_count, view_count, prefix,
     *            title, author_name, last_reply_user, last_reply_at }
     */
    _row(t) {
        // Normalise field names between demo and live shapes
        const pinned    = !!(t.pinned    ?? t.is_pinned);
        const locked    = !!(t.locked    ?? t.is_locked);
        const premium   = !!(t.premium   ?? t.is_premium);
        const hot       = !!(t.hot       ?? (t.reply_count > 50));
        const solved    = !!(t.solved    ?? false);
        const replies   = t.replies  ?? t.reply_count  ?? 0;
        const views     = t.views    ?? t.view_count   ?? 0;
        const author    = t.author   ?? t.author_name  ?? '—';
        const lastUser  = t.lastUser ?? t.last_reply_user ?? author;
        const prefix    = t.prefix   ?? null;

        // Avatar: demo uses pravatar; live uses Gravatar mp fallback
        const avatar = t.avatar
            ? `<img src="${t.avatar}" alt="${author}" class="meta-avatar">`
            : `<img src="https://www.gravatar.com/avatar/?d=mp&s=32" alt="${author}" class="meta-avatar">`;

        // Last-reply time: demo has a string, live has an ISO timestamp
        let lastTime = t.lastTime ?? '';
        if (!lastTime && t.last_reply_at) {
            lastTime = this._relTime(t.last_reply_at);
        }

        const status     = pinned ? 'ts-pinned' : hot ? 'ts-hot' : locked ? 'ts-locked' : solved ? 'ts-solved' : 'ts-normal';
        const statusIcon = pinned ? 'fa-thumbtack' : hot ? 'fa-fire' : locked ? 'fa-lock' : solved ? 'fa-circle-check' : 'fa-comment';
        const pfx        = prefix && this.PREFIX_MAP[prefix];

        return `
<div class="thread-row${pinned ? ' pinned' : ''}" data-thread="${t.id}" role="button" tabindex="0">
  <div class="thread-status ${status}">
    <i class="fa-solid ${statusIcon}"></i>
  </div>
  <div class="thread-main">
    <div class="thread-title-row">
      ${pfx ? `<span class="badge ${pfx.cls}">${pfx.label}</span>` : ''}
      <a class="thread-title" href="#" data-view="thread-view" data-id="${t.id}" data-thread="${t.id}">${t.title}</a>
    </div>
    <div class="thread-meta">
      ${avatar}
      <span>by <strong>${author}</strong></span>
      ${pinned ? '<span class="badge badge-pinned">Pinned</span>' : ''}
      ${locked ? '<span class="badge badge-pinned" style="background:var(--text-dim)">Locked</span>' : ''}
      ${premium ? '<span class="badge badge-premium"><i class="fa-solid fa-crown"></i> Premium</span>' : ''}
    </div>
  </div>
  <div class="thread-last">
    <span class="tl-user">${lastUser}</span>
    <span class="tl-time">${lastTime}</span>
  </div>
  <div class="thread-count">${replies}</div>
  <div class="thread-count text-muted">${this._fmtViews(views)}</div>
</div>`;
    },

    _pagination(currentPage, totalPages, catId, sort) {
        if (totalPages <= 1) return '';

        const pages = [];
        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 || i === totalPages ||
                (i >= currentPage - 1 && i <= currentPage + 1)
            ) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '…') {
                pages.push('…');
            }
        }

        const btnHtml = pages.map(p =>
            p === '…'
                ? `<span class="page-ellipsis">…</span>`
                : `<button class="page-btn${p === currentPage ? ' active' : ''}" type="button"
                     data-page="${p}" data-cat="${catId}" data-sort="${sort}">${p}</button>`
        ).join('');

        return `
<div class="pagination">
  ${currentPage > 1 ? `<button class="page-btn page-prev" type="button"
    data-page="${currentPage - 1}" data-cat="${catId}" data-sort="${sort}">
    <i class="fa-solid fa-angle-left"></i> Prev</button>` : ''}
  ${btnHtml}
  ${currentPage < totalPages ? `<button class="page-btn page-next" type="button"
    data-page="${currentPage + 1}" data-cat="${catId}" data-sort="${sort}">
    Next <i class="fa-solid fa-angle-right"></i></button>` : ''}
</div>`;
    },

    /** Home view navigates here with params.categoryId — normalise to params.id. */
    _normaliseCatParam(params) {
        if (params.categoryId && !params.id) {
            return { ...params, id: parseInt(params.categoryId, 10) };
        }
        return params;
    },

    _bindEvents(catId, sort, catName = '') {
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const view   = a.dataset.view;
                const params = {};
                // Use data-id as the universal param key matching the router convention.
                // data-thread kept as alias on thread-title anchors (set in _row).
                const rawId = a.dataset.id ?? a.dataset.thread ?? a.dataset.category;
                if (rawId) params.id = parseInt(rawId, 10);
                // Pass category context when going into a thread so breadcrumb works
                if (view === 'thread-view') {
                    params.catId  = catId;
                    params.catName = catName;
                }
                Router.navigateTo(view, params);
            });
        });

        document.querySelectorAll('.thread-row').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('a')) return;
                Router.navigateTo('thread-view', {
                    id:      parseInt(row.dataset.thread, 10),
                    catId,
                    catName,
                });
            });
        });

        // Sort select — re-render page 1 with the new sort
        document.getElementById('sort-select')?.addEventListener('change', e => {
            Router.navigateTo('thread-list', { id: catId, sort: e.target.value, page: 1 });
        });

        // Pagination buttons
        document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                Router.navigateTo('thread-list', {
                    id:   parseInt(btn.dataset.cat, 10),
                    sort: btn.dataset.sort,
                    page: parseInt(btn.dataset.page, 10)
                });
            });
        });

        document.getElementById('post-login-btn')?.addEventListener('click', () => {
            Toast.warning('Please sign in to post a new topic.');
            Modal.show('auth-modal');
        });
    },

    /** Convert an ISO timestamp to a human-readable relative time string. */
    _relTime(iso) {
        if (!iso) return '';
        const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
        if (diff < 60)   return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    },

    _fmtViews(n) {
        return n >= 1000 ? (n / 1000).toFixed(1).replace('.0','') + 'k' : n;
    }
};
