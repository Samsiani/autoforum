// Thread-view — single thread / topic
const ThreadView = {

    replyAttachments: [],

    // Stored during render() so _bindEvents() can reference it without
    // touching the hardcoded THREAD demo object.
    _currentTopicId: 0,

    // ── Demo data ────────────────────────────────────────────────────────────
    THREAD: {
        id: 1,
        title: 'BMW N54 Stage 3 map feedback — 450whp target',
        prefix: 'help',
        categoryId: 1,
        categoryName: 'ECU Tuning & Remapping',
        locked: false,
        solved: false,
        views: 3401,
        replies: 3,
    },

    POSTS: [
        {
            id: 1, op: true, liked: false, likes: 24, bookmarked: false,
            author: { name: 'TurboFox', avatar: 'https://i.pravatar.cc/80?img=2', role: 'VIP Member',
                      joined: 'Mar 2021', posts: 3190, rep: 2847, badges: ['badge-vip', 'badge-active'] },
            time: 'March 14, 2025 · 09:41 AM',
            body: `<p>Hey all! I'm currently building out a Stage 3 setup on an N54 and need some community eyes on my base map before I take it to the dyno next weekend.</p>
<p>Current mods:</p>
<ul>
  <li>Precision 6266 GT (0.82 A/R rear)</li>
  <li>ID1050x injectors</li>
  <li>Walbro 450 in-tank + surge setup</li>
  <li>AEM flex fuel sensor on E40 blend</li>
  <li>Full charge pipe kit, Z-perf intercooler</li>
</ul>
<p>The fuel map looks clean at part-throttle but I'm seeing a slight lean condition at 6800–7200rpm under full boost (targeting 28psi). Posted the log below — anyone spot anything obvious?</p>
<div class="code-block">
<pre>[LOG SNIPPET]
RPM     BOOST   AFR     INJ_PW
6800    27.4    12.8    16.2ms
6950    27.8    12.5    16.8ms  ← OK
7100    28.1    13.6    15.1ms  ← LEAN spike!
7200    28.0    13.4    15.3ms
</pre>
</div>
<p>Any N54 experts here? Would really appreciate the feedback before Saturday!</p>`,
            locked_content: null
        },
        {
            id: 2, op: false, liked: false, likes: 11, bookmarked: false,
            author: { name: 'DieselKing', avatar: 'https://i.pravatar.cc/80?img=1', role: 'Senior Tuner',
                      joined: 'Jan 2020', posts: 4821, rep: 3201, badges: ['badge-verified', 'badge-active'] },
            time: 'March 14, 2025 · 11:18 AM',
            body: `<p>That lean spike at 7100 is almost certainly injector dead-time table compensation missing the mark at high pulsewidth. What base dead-time are you using for ID1050x on 14v? Factory value is way off for those.</p>
<p>Try setting dead-time to <strong>0.780ms @ 14.0v</strong> and re-log. You'll probably find that recovers 0.6–0.8 lambda units.</p>
<p>Also — are you on a flex-fuel calibrated base or just ethanol-offset? Because E40 on a pure petrol base file is asking for trouble at the top end.</p>`,
            locked_content: null
        },
        {
            id: 3, op: false, liked: false, likes: 7, bookmarked: false,
            author: { name: 'ECU_Pro', avatar: 'https://i.pravatar.cc/80?img=3', role: 'Moderator',
                      joined: 'Jul 2019', posts: 2944, rep: 2103, badges: ['badge-mod', 'badge-verified'] },
            time: 'March 14, 2025 · 12:55 PM',
            body: `<p>Agree with DieselKing — dead-time correction is the #1 suspect here. Additionally, check your high-rpm fuel pressure target. At 28psi boost + E40, you want at least 72–75psi base fuel pressure, otherwise you're losing effective injector flow at the top end.</p>`,
            locked_content: {
                preview: 'I also have a working N54 Stage 3 E40 base file with corrected dead-time tables, flex calibration and ignition advance map.',
                cta: 'Unlock Premium Content',
                detail: 'Requires active EsyTuner Pro license to access'
            }
        },
        {
            id: 4, op: false, liked: false, likes: 3, bookmarked: false,
            author: { name: 'TurboFox', avatar: 'https://i.pravatar.cc/80?img=2', role: 'VIP Member',
                      joined: 'Mar 2021', posts: 3190, rep: 2847, badges: ['badge-vip', 'badge-active'] },
            time: 'March 14, 2025 · 14:03 PM',
            body: `<p>Thanks guys — DieselKing that dead-time tip was spot on! Re-logged after adjusting to 0.780ms and the lean spike is completely gone. AFR is sitting solid at 12.4–12.6 all the way to redline now. 🙌</p>
<p>ECU_Pro — yes I have a Pro license, will check out your file after work today. Appreciate the detailed response!</p>
<p>Dyno booked for Saturday — will post results and the final map here afterwards.</p>`,
            locked_content: null
        }
    ],

    // ── Render ───────────────────────────────────────────────────────────────
    async render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        // ── Demo mode: use hardcoded data unchanged ──────────────────────────
        if (CONFIG.DEMO_MODE) {
            this._currentTopicId = this.THREAD.id;
            const thread = this.THREAD;
            el.innerHTML = this._buildHtml({
                title:       thread.title,
                prefix:      thread.prefix,
                locked:      thread.locked,
                solved:      thread.solved,
                replyCount:  thread.replies,
                views:       thread.views,
                catId:       thread.categoryId,
                catName:     thread.categoryName,
                postsHtml:   this.POSTS.map((p, i) => this._post(p, i)).join(''),
            });
            this._bindEvents();
            return;
        }

        // ── Production mode ──────────────────────────────────────────────────
        const topicId = parseInt(params.id ?? 0, 10);
        if (!topicId) {
            Router.navigateTo('home');
            return;
        }
        this._currentTopicId = topicId;

        // Skeleton while loading
        el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="skeleton skeleton-line" style="width:40%;height:1.2rem;margin-bottom:1.5rem"></div>
  <div class="skeleton skeleton-block" style="height:120px;margin-bottom:1.5rem;border-radius:12px"></div>
  ${[1,2,3].map(() => `
  <div class="skeleton skeleton-block" style="height:200px;margin-bottom:1rem;border-radius:12px"></div>`).join('')}
</div>`;

        let data;
        try {
            const page = parseInt(params.page ?? 1, 10);
            data = await API.getPosts({ topicId, page });
        } catch (err) {
            // Premium gate: server returns code:'premium_required' for non-licensed users.
            if (err.code === 'premium_required') {
                el.innerHTML = this._premiumGateHtml();
                el.querySelector('#pg-login-btn')?.addEventListener('click', () => {
                    document.getElementById('open-auth-modal')?.click();
                });
                return;
            }
            el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="empty-state">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <h3>${_t('could_not_load_thread')}</h3>
    <p>${err.message ?? _t('unexpected_error')}</p>
    <button class="btn btn-primary" type="button" onclick="Router.navigateTo('home')">
      <i class="fa-solid fa-house"></i> ${_t('go_home')}
    </button>
  </div>
</div>`;
            return;
        }

        const topic = data.topic;
        // Back-navigation: category ID is stored in params.catId if the
        // thread-list passes it; fall back to 0 (back button still works).
        const catId  = parseInt(params.catId ?? 0, 10);
        const catName = params.catName ?? 'Forum';

        // ── Premium gate ──────────────────────────────────────────────────────
        // The server already rejects the request with code:'premium_required' if
        // the user has no license, so reaching here means either the topic is not
        // premium OR the user is licensed. We still show a badge but never need
        // to block client-side. The server is the authoritative gate.

        el.innerHTML = this._buildHtml({
            title:      topic.title,
            prefix:     topic.prefix,
            locked:     topic.locked,
            isPremium:  topic.is_premium ?? false,
            solved:     topic.status === 'solved',
            replyCount: topic.reply_count,
            views:      null,          // not returned by API
            catId,
            catName,
            postsHtml:  data.posts.map((p, i) => this._postLive(p, i)).join(''),
            pagination: this._pagination(data, params),
        });
        this.replyAttachments = [];
        this._bindEvents();
    },

    // Shared HTML scaffold used by both demo and live modes.
    _buildHtml({ title, prefix, locked, isPremium = false, solved, replyCount, views, catId, catName, postsHtml, pagination = '' }) {
        const prefixBadge = prefix
            ? `<span class="badge badge-${prefix}">${prefix.charAt(0).toUpperCase() + prefix.slice(1)}</span>`
            : '';
        return `
<div class="page-wrap fade-up">
  <!-- breadcrumb -->
  <nav class="breadcrumbs">
    <a href="#" data-view="home">${_t('home')}</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <a href="#" data-view="thread-list" data-id="${catId}" data-category="${catId}">${catName}</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>${title}</span>
  </nav>

  <!-- thread hero -->
  <div class="thread-hero">
    <div class="thread-hero-left">
      <div class="thread-hero-badges">
        ${prefixBadge}
        ${locked    ? `<span class="badge badge-locked">${_t('locked')}</span>`  : ''}
        ${solved    ? '<span class="badge badge-solved">Solved</span>'  : ''}
        ${isPremium ? `<span class="badge badge-premium"><i class="fa-solid fa-crown"></i> ${_t('premium')}</span>` : ''}
      </div>
      <h1 class="thread-hero-title">${title}</h1>
      <div class="thread-hero-meta">
        ${views !== null ? `<span><i class="fa-solid fa-eye"></i> ${Number(views).toLocaleString()} ${_t('views').toLowerCase()}</span>` : ''}
        <span><i class="fa-solid fa-comments"></i> ${replyCount} ${_t('replies').toLowerCase()}</span>
      </div>
    </div>
    <div class="thread-hero-actions">
      ${State.isAuthenticated() ? `
      <button class="btn btn-ghost btn-sm" type="button" id="subscribe-btn">
        <i class="fa-regular fa-bell"></i> Subscribe
      </button>
      <button class="btn btn-ghost btn-sm" type="button" id="share-btn">
        <i class="fa-solid fa-share-nodes"></i> Share
      </button>` : ''}
    </div>
  </div>

  <!-- posts -->
  <div class="posts-list" id="posts-list">
    ${postsHtml}
  </div>

  ${pagination}

  <!-- reply area -->
  ${this._replyArea()}
</div>`;
    },

    // ── Pagination ────────────────────────────────────────────────────────────
    _pagination(data, params) {
        if (!data || data.total_pages <= 1) return '';
        const { page, total_pages } = data;
        let html = '<div class="pagination">';
        if (page > 1) {
            html += `<button class="btn btn-ghost btn-sm page-btn" data-page="${page - 1}">
              <i class="fa-solid fa-angle-left"></i> ${_t('prev')}</button>`;
        }
        const start = Math.max(1, page - 2);
        const end   = Math.min(total_pages, page + 2);
        for (let i = start; i <= end; i++) {
            html += `<button class="btn btn-sm page-btn${i === page ? ' btn-primary' : ' btn-ghost'}" data-page="${i}">${i}</button>`;
        }
        if (page < total_pages) {
            html += `<button class="btn btn-ghost btn-sm page-btn" data-page="${page + 1}">
              ${_t('next')} <i class="fa-solid fa-angle-right"></i></button>`;
        }
        html += '</div>';
        return html;
    },

    _post(p, idx) {
        const u = p.author;
        return `
<div class="post-card${p.op ? ' op' : ''}" id="post-${p.id}">
  <div class="post-layout">
    <!-- user column -->
    <div class="post-user-col">
      <img src="${u.avatar}" alt="${u.name}" class="post-avatar">
      <a class="post-username" href="#" data-view="user-profile" data-id="${p.id}">${u.name}</a>
      <div class="post-user-role">${u.role}</div>
      <div class="post-user-badges">
        ${u.badges.map(b => `<span class="badge ${b}">${this._badgeLabel(b)}</span>`).join('')}
      </div>
      <div class="post-user-stats">
        <div class="pus-item"><span class="pus-val">${u.posts.toLocaleString()}</span><span class="pus-lbl">${_t('posts')}</span></div>
        <div class="pus-item"><span class="pus-val">${u.rep.toLocaleString()}</span><span class="pus-lbl">${_t('rep')}</span></div>
        <div class="pus-item"><span class="pus-val">${u.joined}</span><span class="pus-lbl">${_t('member_since_label')}</span></div>
      </div>
    </div>

    <!-- content column -->
    <div class="post-content-col">
      <div class="post-timestamp">
        <span><i class="fa-regular fa-clock"></i> ${p.time}</span>
        <span class="post-num">#${idx + 1}</span>
      </div>

      <div class="post-body">${p.body}</div>

      ${p.locked_content ? this._lockedBlock(p) : ''}

      <div class="post-footer">
        <div class="post-actions">
          <button class="action-btn like${p.liked ? ' active' : ''}" data-post="${p.id}" type="button">
            <i class="fa-${p.liked ? 'solid' : 'regular'} fa-thumbs-up"></i>
            <span class="like-count">${p.likes}</span>
          </button>
          <button class="action-btn" type="button" title="Quote">
            <i class="fa-solid fa-quote-left"></i>
          </button>
          <button class="action-btn" type="button" title="Bookmark">
            <i class="fa-${p.bookmarked ? 'solid' : 'regular'} fa-bookmark"></i>
          </button>
          ${State.isAuthenticated() ? `
          <button class="action-btn" type="button" title="Report">
            <i class="fa-solid fa-flag"></i>
          </button>` : ''}
        </div>
        ${(State.currentUser?.role === 'Moderator' || State.currentUser?.role === 'Admin') ? `
        <div class="post-mod-actions">
          <button class="action-btn mod-edit" type="button" title="${_t('edit')}" data-post-id="${p.id}" data-user-id="${p.author?.id ?? 0}"><i class="fa-solid fa-pen"></i></button>
          <button class="action-btn mod-delete" type="button" title="${_t('delete')}" data-post-id="${p.id}"><i class="fa-solid fa-trash"></i></button>
        </div>` : ''}
      </div>
    </div>
  </div>
</div>`;
    },

    // ── Live post renderer (real API data shape) ─────────────────────────────
    _postLive(p, idx) {
        const isOp     = !!p.is_op;
        const liked    = !!p.viewer_thanked;
        const likes    = parseInt(p.thanks_count, 10) || 0;
        const posts    = parseInt(p.author_post_count, 10) || 0;
        const rep      = parseInt(p.author_reputation, 10) || 0;
        const location = p.author_location ? `<div class="post-user-location"><i class="fa-solid fa-location-dot"></i> ${p.author_location}</div>` : '';
        const time     = p.created_at ? new Date(p.created_at + 'Z').toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        }) : '';
        // Avatar: gravatar mp fallback (no URLs returned by API)
        const avatarEl = `<div class="post-avatar" style="background:var(--bg-raised);display:flex;align-items:center;justify-content:center;font-size:1.8rem;width:64px;height:64px;border-radius:50%;flex-shrink:0"><i class="fa-solid fa-user" style="color:var(--text-dim)"></i></div>`;

        const isMod    = State.currentUser?.role === 'Moderator' || State.currentUser?.role === 'Admin';
        const isAuthor = State.currentUser?.id === p.user_id;
        const canEdit  = isMod || isAuthor;
        const canDel   = isMod;
        return `
<div class="post-card${isOp ? ' op' : ''}" id="post-${p.id}">
  <div class="post-layout">
    <!-- user column -->
    <div class="post-user-col">
      ${avatarEl}
      <a class="post-username" href="#" data-view="user-profile" data-id="${p.user_id}">${p.author_name || 'Unknown'}</a>
      ${location}
      <div class="post-user-stats">
        <div class="pus-item"><span class="pus-val">${posts.toLocaleString()}</span><span class="pus-lbl">${_t('posts')}</span></div>
        <div class="pus-item"><span class="pus-val">${rep.toLocaleString()}</span><span class="pus-lbl">${_t('rep')}</span></div>
      </div>
    </div>

    <!-- content column -->
    <div class="post-content-col">
      <div class="post-timestamp">
        <span><i class="fa-regular fa-clock"></i> ${time}</span>
        <span class="post-num">#${idx + 1}</span>
      </div>

      <div class="post-body">${p.content}</div>
      ${this._attachmentsBlock(p.attachments)}
      <div class="post-footer">
        <div class="post-actions">
          <button class="action-btn like${liked ? ' active' : ''}" data-post="${p.id}" type="button">
            <i class="fa-${liked ? 'solid' : 'regular'} fa-thumbs-up"></i>
            <span class="like-count">${likes}</span>
          </button>
          <button class="action-btn" type="button" title="Quote">
            <i class="fa-solid fa-quote-left"></i>
          </button>
          ${State.isAuthenticated() ? `
          <button class="action-btn" type="button" title="Report">
            <i class="fa-solid fa-flag"></i>
          </button>` : ''}
        </div>
        ${(canEdit || canDel) ? `
        <div class="post-mod-actions">
          ${canEdit ? `<button class="action-btn mod-edit" type="button" title="${_t('edit')}" data-post-id="${p.id}" data-user-id="${p.user_id}"><i class="fa-solid fa-pen"></i></button>` : ''}
          ${canDel  ? `<button class="action-btn mod-delete" type="button" title="${_t('delete')}" data-post-id="${p.id}"><i class="fa-solid fa-trash"></i></button>` : ''}
        </div>` : ''}
      </div>
    </div>
  </div>
</div>`;
    },

    _attachmentsBlock(attachments) {
        if (!attachments || !attachments.length) return '';

        const imgs = attachments.filter(a => a.mime_type && a.mime_type.startsWith('image/'));
        const docs = attachments.filter(a => !a.mime_type || !a.mime_type.startsWith('image/'));

        const imgIcons = { 'image/jpeg':'fa-file-image', 'image/png':'fa-file-image', 'image/gif':'fa-file-image', 'image/webp':'fa-file-image' };
        const docIcons = {
            'application/pdf': 'fa-file-pdf',
            'application/msword': 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fa-file-word',
            'application/vnd.ms-excel': 'fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fa-file-excel',
            'application/zip': 'fa-file-zipper',
            'application/x-rar-compressed': 'fa-file-zipper',
            'text/plain': 'fa-file-lines',
        };
        const getDocIcon = mime => docIcons[mime] || 'fa-file';

        const fmtSize = bytes => {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
            return (bytes/1048576).toFixed(1) + ' MB';
        };

        const thumbsHtml = imgs.length ? `
<div class="att-images">
  ${imgs.map(a => `
  <a class="att-thumb-link" href="${a.url}" data-lightbox data-caption="${a.filename}" target="_blank">
    <img class="att-thumb" src="${a.url}" alt="${a.filename}" loading="lazy">
  </a>`).join('')}
</div>` : '';

        const docsHtml = docs.length ? `
<div class="att-docs">
  ${docs.map(a => `
  <a class="att-doc-row" href="${a.url}" download="${a.filename}" target="_blank">
    <i class="fa-solid ${getDocIcon(a.mime_type)} att-doc-icon"></i>
    <span class="att-doc-name">${a.filename}</span>
    <span class="att-doc-size">${fmtSize(a.file_size)}</span>
    <i class="fa-solid fa-download att-doc-dl"></i>
  </a>`).join('')}
</div>` : '';

        return `<div class="post-attachments">${thumbsHtml}${docsHtml}</div>`;
    },

    // Full-thread premium gate screen (shown when server returns code:'premium_required').
    _premiumGateHtml() {
        const isLoggedIn = State.isAuthenticated();
        return `
<div class="page-wrap fade-up">
  <div class="premium-gate">
    <div class="premium-gate-icon"><i class="fa-solid fa-crown"></i></div>
    <h2 class="premium-gate-title">${_t('premium_thread')}</h2>
    <p class="premium-gate-body">
      ${_t('premium_thread_desc')}<br>
      ${_t('premium_unlock_desc')}
    </p>
    <div class="premium-gate-actions">
      ${isLoggedIn
        ? `<a class="btn btn-primary" href="${(typeof AF_DATA !== 'undefined' ? AF_DATA.siteUrl : '') + '/my-account/licenses'}" target="_blank">
             <i class="fa-solid fa-key"></i> ${_t('view_my_licenses')}
           </a>`
        : `<button class="btn btn-primary" type="button" id="pg-login-btn">
             <i class="fa-solid fa-right-to-bracket"></i> ${_t('sign_in')}
           </button>`}
      <button class="btn btn-ghost" type="button" onclick="Router.navigateTo('home')">
        <i class="fa-solid fa-house"></i> ${_t('go_home')}
      </button>
    </div>
  </div>
</div>`;
    },

    _replyArea() {
        if (!State.isAuthenticated()) {
            return `
<div class="quick-reply card">
  <div class="card-body text-center" style="padding:2rem">
    <i class="fa-solid fa-lock" style="font-size:2rem;color:var(--text-dim);margin-bottom:.75rem"></i>
    <p style="color:var(--text-muted);margin-bottom:1rem">${_t('sign_in_to_reply')}</p>
    <button class="btn btn-primary" type="button" id="reply-login-btn">
      <i class="fa-solid fa-right-to-bracket"></i> ${_t('sign_in')}
    </button>
  </div>
</div>`;
        }

        return `
<div class="quick-reply card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-reply"></i> ${_t('post_a_reply')}</span>
  </div>
  <div class="card-body">
    <div class="editor-toolbar">
      <button class="editor-btn" title="${_t('bold')}" type="button"><i class="fa-solid fa-bold"></i></button>
      <button class="editor-btn" title="${_t('italic')}" type="button"><i class="fa-solid fa-italic"></i></button>
      <button class="editor-btn" title="${_t('underline')}" type="button"><i class="fa-solid fa-underline"></i></button>
      <div class="editor-sep"></div>
      <button class="editor-btn" title="${_t('link')}" type="button"><i class="fa-solid fa-link"></i></button>
      <button class="editor-btn" title="${_t('image')}" type="button"><i class="fa-regular fa-image"></i></button>
      <button class="editor-btn" title="${_t('code_block')}" type="button"><i class="fa-solid fa-code"></i></button>
      <button class="editor-btn" title="${_t('quote_block')}" type="button"><i class="fa-solid fa-quote-left"></i></button>
      <div class="editor-sep"></div>
      <button class="editor-btn" title="${_t('ordered_list')}" type="button"><i class="fa-solid fa-list-ol"></i></button>
      <button class="editor-btn" title="${_t('unordered_list')}" type="button"><i class="fa-solid fa-list-ul"></i></button>
    </div>
    <textarea class="form-textarea" id="reply-body" rows="6" placeholder="${_t('write_reply_placeholder')}"></textarea>
    <div class="form-group" style="margin-top:.75rem">
      <div class="drop-zone" id="reply-drop-zone">
        <i class="fa-solid fa-cloud-arrow-up dz-icon"></i>
        <p class="dz-text">Drag &amp; drop files here, or <span class="dz-link" id="reply-dz-click">browse</span></p>
        <p class="dz-hint">${_t('file_upload_hint')}</p>
        <input type="file" id="reply-file-input" multiple accept=".jpg,.jpeg,.png,.pdf,.bin,.csv,.log" style="display:none">
      </div>
      <div class="file-list" id="reply-file-list"></div>
    </div>
    <div class="reply-footer">
      <span class="char-count" id="char-count">0 / 10000</span>
      <button class="btn btn-primary" type="button" id="submit-reply">
        <i class="fa-solid fa-paper-plane"></i> ${_t('post_reply')}
      </button>
    </div>
  </div>
</div>`;
    },

    _bindEvents() {
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const view = a.dataset.view;
                const params = {};
                // Support both data-id (new convention) and data-category (legacy)
                const rawId = a.dataset.id ?? a.dataset.category;
                if (rawId) params.id = parseInt(rawId, 10);
                Router.navigateTo(view, params);
            });
        });

        // Pagination buttons
        document.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const newPage = parseInt(btn.dataset.page, 10);
                const p = { ...Router.currentParams, page: newPage };
                this.render(p);
            });
        });

        // like buttons
        document.querySelectorAll('.action-btn.like').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!State.isAuthenticated()) {
                    Toast.warning(_t('sign_in_to_like'));
                    return;
                }
                const postId  = parseInt(btn.dataset.post, 10);
                const isActive = btn.classList.toggle('active');
                const icon    = btn.querySelector('i');
                const count   = btn.querySelector('.like-count');
                icon.className = `fa-${isActive ? 'solid' : 'regular'} fa-thumbs-up`;
                count.textContent = parseInt(count.textContent) + (isActive ? 1 : -1);
                try {
                    await API.thankPost(postId);
                } catch (err) {
                    // Revert optimistic UI on failure
                    btn.classList.toggle('active');
                    icon.className = `fa-${!isActive ? 'solid' : 'regular'} fa-thumbs-up`;
                    count.textContent = parseInt(count.textContent) + (isActive ? -1 : 1);
                    Toast.error(err.message ?? 'Could not save your reaction.');
                }
            });
        });

        // unlock buttons (demo mode only — locked_content is not in live API)
        document.querySelectorAll('.btn-unlock').forEach(btn => {
            btn.addEventListener('click', async () => {
                const postId = parseInt(btn.dataset.post, 10);
                if (!State.isAuthenticated()) {
                    Toast.warning(_t('sign_in_to_like'));
                    Modal.show('auth-modal');
                    return;
                }
                const user = State.currentUser;
                if (!user.licenses || user.licenses.length === 0) {
                    Toast.error('An active EsyTuner Pro license is required to unlock this content.');
                    Router.navigateTo('dashboard');
                    return;
                }
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('unlocking')}`;
                btn.disabled = true;
                try {
                    await API.thankPost(postId);
                    Toast.success(_t('premium_unlocked'));
                    this.render(Router.currentParams);
                } catch (err) {
                    Toast.error(err.message ?? _t('could_not_unlock'));
                    btn.innerHTML = `<i class="fa-solid fa-key"></i> ${_t('unlock_premium')}`;
                    btn.disabled = false;
                }
            });
        });

        // quote buttons — insert quoted text into reply textarea
        document.querySelectorAll('.action-btn[title="Quote"]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!State.isAuthenticated()) { Toast.warning(_t('sign_in_to_quote')); return; }
                const postCard = btn.closest('.post-card');
                const author   = postCard?.querySelector('.post-username')?.textContent.trim() || 'User';
                const bodyEl   = postCard?.querySelector('.post-body');
                // Strip HTML to get plain text for the quote
                const tmp = document.createElement('div');
                tmp.innerHTML = bodyEl?.innerHTML || '';
                const bodyText = (tmp.innerText || tmp.textContent || '').trim();
                const textarea = document.getElementById('reply-body');
                if (!textarea) { Toast.warning(_t('reply_area_unavailable')); return; }
                const insert = `[quote=${author}]\n${bodyText}\n[/quote]\n\n`;
                textarea.value = insert + textarea.value;
                document.getElementById('char-count').textContent = `${textarea.value.length} / 10000`;
                textarea.focus();
                textarea.setSelectionRange(insert.length, insert.length);
                textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                Toast.info(_t('quoting_author', { author }));
            });
        });

        // report buttons
        document.querySelectorAll('.action-btn[title="Report"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!State.isAuthenticated()) { Toast.warning(_t('sign_in_to_report')); return; }
                const postId = parseInt(btn.closest('.post-card')?.id?.replace('post-', ''), 10);
                if (!postId) return;
                try {
                    await API.reportPost(postId);
                    Toast.info(_t('report_submitted'));
                } catch (err) {
                    Toast.error(err.message ?? _t('could_not_report'));
                }
            });
        });

        // delete buttons
        document.querySelectorAll('.mod-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                const postId = parseInt(btn.dataset.postId, 10);
                if (!confirm(_t('delete_post_confirm'))) return;
                btn.disabled = true;
                try {
                    await API.deletePost(postId);
                    const card = document.getElementById(`post-${postId}`);
                    if (card) {
                        card.style.transition = 'opacity .25s';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 260);
                    }
                    Toast.success(_t('post_deleted'));
                } catch (err) {
                    Toast.error(err.message ?? _t('could_not_delete_post'));
                    btn.disabled = false;
                }
            });
        });

        // edit buttons — inline textarea swap
        document.querySelectorAll('.mod-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const postId  = parseInt(btn.dataset.postId, 10);
                const card    = document.getElementById(`post-${postId}`);
                if (!card) return;
                const bodyEl  = card.querySelector('.post-body');
                if (!bodyEl) return;
                // Already editing?
                if (card.querySelector('.inline-edit-wrap')) return;
                const original = bodyEl.innerHTML;
                // Strip HTML to editable plain text
                const tmp = document.createElement('div');
                tmp.innerHTML = original;
                const plain = (tmp.innerText || tmp.textContent || '').trim();
                bodyEl.style.display = 'none';
                const wrap = document.createElement('div');
                wrap.className = 'inline-edit-wrap';
                wrap.innerHTML = `
                    <textarea class="inline-edit-ta" rows="6">${plain.replace(/</g,'&lt;')}</textarea>
                    <div class="inline-edit-actions">
                        <button class="btn btn-primary btn-sm inline-save">${_t('save_changes')}</button>
                        <button class="btn btn-sm inline-cancel" style="margin-left:8px">${_t('cancel')}</button>
                    </div>`;
                bodyEl.insertAdjacentElement('afterend', wrap);
                wrap.querySelector('textarea').focus();

                wrap.querySelector('.inline-cancel').addEventListener('click', () => {
                    wrap.remove();
                    bodyEl.style.display = '';
                });

                wrap.querySelector('.inline-save').addEventListener('click', async () => {
                    const newContent = wrap.querySelector('textarea').value.trim();
                    if (!newContent || newContent.length < 10) {
                        Toast.warning(_t('post_too_short'));
                        return;
                    }
                    const saveBtn = wrap.querySelector('.inline-save');
                    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    saveBtn.disabled = true;
                    try {
                        const res = await API.editPost(postId, newContent);
                        bodyEl.innerHTML = res.content || newContent;
                        bodyEl.style.display = '';
                        wrap.remove();
                        Toast.success(_t('post_updated'));
                    } catch (err) {
                        Toast.error(err.message ?? _t('could_not_save_edit'));
                        saveBtn.innerHTML = _t('save_changes');
                        saveBtn.disabled = false;
                    }
                });
            });
        });

        // subscribe/share
        document.getElementById('subscribe-btn')?.addEventListener('click', () => {
            Toast.success(_t('subscribed'));
        });
        document.getElementById('share-btn')?.addEventListener('click', () => {
            if (navigator.clipboard) navigator.clipboard.writeText(window.location.href);
            Toast.info(_t('link_copied'));
        });

        // reply area
        document.getElementById('reply-login-btn')?.addEventListener('click', () => Modal.show('auth-modal'));

        const textarea = document.getElementById('reply-body');
        textarea?.addEventListener('input', () => {
            document.getElementById('char-count').textContent = `${textarea.value.length} / 10000`;
        });

        // ── Editor toolbar ───────────────────────────────────────────────────
        // Wraps selected text (or inserts placeholder) with the given markers.
        const _wrap = (open, close, placeholder = '') => {
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end   = textarea.selectionEnd;
            const sel   = textarea.value.slice(start, end) || placeholder;
            const before = textarea.value.slice(0, start);
            const after  = textarea.value.slice(end);
            textarea.value = before + open + sel + close + after;
            // Re-select the inserted text (not the markers)
            textarea.selectionStart = start + open.length;
            textarea.selectionEnd   = start + open.length + sel.length;
            textarea.focus();
            document.getElementById('char-count').textContent = `${textarea.value.length} / 10000`;
        };

        document.querySelectorAll('.editor-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!textarea) return;
                const title = btn.title;
                if (title === _t('bold')) {
                    _wrap('<strong>', '</strong>', _t('bold_text'));
                } else if (title === _t('italic')) {
                    _wrap('<em>', '</em>', _t('italic_text'));
                } else if (title === _t('underline')) {
                    _wrap('<u>', '</u>', _t('underlined_text'));
                } else if (title === _t('code_block')) {
                    const sel = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
                    if (sel.includes('\n')) {
                        _wrap('<pre><code>', '</code></pre>', _t('code_here'));
                    } else {
                        _wrap('<code>', '</code>', 'code');
                    }
                } else if (title === _t('quote_block')) {
                    _wrap('[quote]\n', '\n[/quote]\n', _t('quoted_text'));
                } else if (title === _t('ordered_list')) {
                    _wrap('<ol>\n  <li>', '</li>\n</ol>', _t('list_item'));
                } else if (title === _t('unordered_list')) {
                    _wrap('<ul>\n  <li>', '</li>\n</ul>', _t('list_item'));
                } else if (title === _t('link')) {
                    const url = prompt(_t('enter_url'));
                    if (!url) return;
                    const sel = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd) || url;
                    _wrap(`<a href="${url}" target="_blank" rel="noopener">`, '</a>', sel);
                } else if (title === _t('image')) {
                    const src = prompt(_t('enter_image_url'));
                    if (!src) return;
                    const start = textarea.selectionStart;
                    const before = textarea.value.slice(0, start);
                    const after  = textarea.value.slice(start);
                    const tag    = `<img src="${src}" alt="image" class="post-img">`;
                    textarea.value = before + tag + after;
                    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
                    textarea.focus();
                    document.getElementById('char-count').textContent = `${textarea.value.length} / 10000`;
                }
            });
        });

        // ── Reply drop-zone ───────────────────────────────────────────────────
        const rdz = document.getElementById('reply-drop-zone');
        const rfi = document.getElementById('reply-file-input');
        if (rdz && rfi) {
            document.getElementById('reply-dz-click')?.addEventListener('click', () => rfi.click());
            rfi.addEventListener('change', () => { this._handleReplyFiles(rfi.files); rfi.value = ''; });
            rdz.addEventListener('dragover',  e => { e.preventDefault(); rdz.classList.add('drag-over'); });
            rdz.addEventListener('dragleave', () => rdz.classList.remove('drag-over'));
            rdz.addEventListener('drop', e => {
                e.preventDefault();
                rdz.classList.remove('drag-over');
                this._handleReplyFiles(e.dataTransfer.files);
            });
        }

        document.getElementById('submit-reply')?.addEventListener('click', async () => {
            const body = textarea?.value.trim();
            if (!body) { Toast.warning('Please write something before posting.'); return; }
            if (body.length < 10) { Toast.warning(_t('post_too_short')); return; }
            const btn = document.getElementById('submit-reply');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('posting')}`;
            btn.disabled = true;
            try {
                const topicId = this._currentTopicId;
                const data    = await API.createPost({ topicId, content: body });
                const postId  = data?.post_id ?? null;

                // Upload attachments sequentially if any were staged.
                if (postId && this.replyAttachments.length > 0) {
                    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('uploading_files')}`;
                    const failed = [];
                    for (const file of this.replyAttachments) {
                        try { await API.uploadAttachment(file, postId); }
                        catch (e) { failed.push(file.name); }
                    }
                    if (failed.length) Toast.warning(_t('files_failed_upload', { count: failed.length, files: failed.join(', ') }));
                    this.replyAttachments = [];
                }

                Toast.success(_t('reply_posted'));
                textarea.value = '';
                document.getElementById('char-count').textContent = '0 / 10000';
                // Refresh the thread to show the new reply.
                this.render(Router.currentParams);
            } catch (err) {
                Toast.error(err.message ?? _t('topic_post_failed'));
            } finally {
                btn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> ${_t('post_reply')}`;
                btn.disabled = false;
            }
        });

        // ── Attachment image lightbox ─────────────────────────────────────────
        document.querySelectorAll('.att-thumb-link').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const src     = link.href;
                const caption = link.dataset.caption || '';
                let lb = document.getElementById('af-lightbox');
                if (!lb) {
                    lb = document.createElement('div');
                    lb.id = 'af-lightbox';
                    lb.innerHTML = `
<div class="af-lb-backdrop"></div>
<div class="af-lb-dialog">
  <button class="af-lb-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
  <img class="af-lb-img" src="" alt="">
  <p class="af-lb-caption"></p>
</div>`;
                    document.body.appendChild(lb);
                    lb.querySelector('.af-lb-backdrop').addEventListener('click', () => lb.classList.remove('af-lb-open'));
                    lb.querySelector('.af-lb-close').addEventListener('click',   () => lb.classList.remove('af-lb-open'));
                    document.addEventListener('keydown', ev => { if (ev.key === 'Escape') lb.classList.remove('af-lb-open'); });
                }
                lb.querySelector('.af-lb-img').src = src;
                lb.querySelector('.af-lb-caption').textContent = caption;
                lb.classList.add('af-lb-open');
            });
        });
    },

    _handleReplyFiles(files) {
        const list    = document.getElementById('reply-file-list');
        if (!list) return;
        const allowed = ['jpg','jpeg','png','pdf','bin','csv','log'];
        for (const file of Array.from(files)) {
            if (this.replyAttachments.length >= 5) { Toast.warning(_t('max_attachments')); break; }
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(ext)) { Toast.error(_t('file_type_not_allowed', { ext })); continue; }
            if (file.size > 10 * 1024 * 1024) { Toast.error(_t('file_too_large', { filename: file.name })); continue; }
            this.replyAttachments.push(file);
            const item = document.createElement('div');
            item.className = 'file-item';
            item.innerHTML = `
<i class="fa-solid fa-file"></i>
<span class="file-name">${file.name}</span>
<span class="file-size">${this._fmtSize(file.size)}</span>
<button class="file-remove" type="button" title="Remove"><i class="fa-solid fa-xmark"></i></button>`;
            item.querySelector('.file-remove').addEventListener('click', () => {
                this.replyAttachments = this.replyAttachments.filter(f => f !== file);
                item.remove();
            });
            list.appendChild(item);
        }
    },

    _fmtSize(bytes) {
        if (bytes < 1024) return `${bytes}B`;
        if (bytes < 1048576) return `${(bytes/1024).toFixed(1)}KB`;
        return `${(bytes/1048576).toFixed(1)}MB`;
    },

    _badgeLabel(cls) {
        const map = {
            'badge-vip': 'VIP', 'badge-verified': 'Verified', 'badge-admin': 'Admin',
            'badge-mod': 'Mod', 'badge-active': 'Active', 'badge-license': 'Licensed'
        };
        return map[cls] || cls.replace('badge-','');
    }
};
