// Create-topic view
const CreateTopicView = {
    CATEGORIES: [
        { id: 1, name: 'ECU Tuning & Remapping' },
        { id: 2, name: 'Software & Tools' },
        { id: 3, name: 'Technical Support' },
        { id: 4, name: 'Vehicle Specific' },
        { id: 5, name: 'Guides & Tutorials' },
        { id: 6, name: 'General Discussion' }
    ],

    PREFIXES: [
        { id: 'discussion', label: 'Discussion', cls: 'badge-discussion' },
        { id: 'help',       label: 'Help',       cls: 'badge-help' },
        { id: 'guide',      label: 'Guide',      cls: 'badge-guide' },
        { id: 'firmware',   label: 'Firmware',   cls: 'badge-firmware' }
    ],

    attachments: [],

    async render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        if (!State.isAuthenticated()) {
            el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="card" style="max-width:500px;margin:4rem auto;text-align:center">
    <div class="card-body" style="padding:3rem">
      <i class="fa-solid fa-lock" style="font-size:3rem;color:var(--text-dim);margin-bottom:1rem;display:block"></i>
      <h2 style="margin-bottom:.5rem">${_t('sign_in_required')}</h2>
      <p style="color:var(--text-muted);margin-bottom:1.5rem">${_t('must_sign_in_to_create')}</p>
      <button class="btn btn-primary" type="button" id="ct-login-btn">
        <i class="fa-solid fa-right-to-bracket"></i> ${_t('sign_in')}
      </button>
    </div>
  </div>
</div>`;
            document.getElementById('ct-login-btn')?.addEventListener('click', () => Modal.show('auth-modal'));
            return;
        }

        // ── Load categories ────────────────────────────────────────────
        let cats;
        if (CONFIG.DEMO_MODE) {
            cats = this.CATEGORIES;
        } else {
            const apiCats = await API.getCategories();
            cats = (apiCats || []).map(c => ({ id: c.id, name: c.name }));
        }

        // Build <option> elements; pre-select category from router params if provided
        const preselect = parseInt(params.id ?? params.categoryId ?? 0, 10);
        const categoryOptions = cats.map(c =>
            `<option value="${c.id}"${c.id === preselect ? ' selected' : ''}>${c.name}</option>`
        ).join('');

        el.innerHTML = `
<div class="page-wrap fade-up">
  <nav class="breadcrumbs">
    <a href="#" data-view="home">${_t('home')}</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>${_t('create_new_topic')}</span>
  </nav>

  <div class="create-topic-wrap">
    <div class="create-topic-main">
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fa-solid fa-pen-to-square"></i> ${_t('new_topic')}</span>
        </div>
        <div class="card-body">
          <form id="create-topic-form">

            <!-- Category -->
            <div class="form-group">
              <label class="form-label" for="topic-category">
                ${_t('category')} <span class="required">*</span>
              </label>
              <select class="form-select" id="topic-category" name="category" required>
                <option value="">${_t('select_category')}</option>
                ${categoryOptions}
              </select>
            </div>

            <!-- Prefix -->
            <div class="form-group">
              <label class="form-label">${_t('thread_prefix')}</label>
              <div class="prefix-picker">
                ${this.PREFIXES.map(p => `
                <label class="prefix-opt">
                  <input type="radio" name="prefix" value="${p.id}">
                  <span class="badge ${p.cls}">${p.label}</span>
                </label>`).join('')}
                <label class="prefix-opt">
                  <input type="radio" name="prefix" value="" checked>
                  <span class="badge" style="background:var(--bg-raised);color:var(--text-muted);border:1px solid var(--border)">${_t('none')}</span>
                </label>
              </div>
            </div>

            <!-- Title -->
            <div class="form-group">
              <label class="form-label" for="topic-title">
                ${_t('title')} <span class="required">*</span>
              </label>
              <input class="form-input" type="text" id="topic-title" name="title"
                     placeholder="${_t('title_placeholder')}"
                     maxlength="200" required>
              <div class="form-hint">
                <span id="title-count">0</span> / 200 characters
              </div>
            </div>

            <!-- Body -->
            <div class="form-group">
              <label class="form-label" for="topic-body">
                ${_t('content')} <span class="required">*</span>
              </label>
              <div class="editor-toolbar">
                <button class="editor-btn" title="${_t('bold')}" type="button"><i class="fa-solid fa-bold"></i></button>
                <button class="editor-btn" title="${_t('italic')}" type="button"><i class="fa-solid fa-italic"></i></button>
                <button class="editor-btn" title="${_t('underline')}" type="button"><i class="fa-solid fa-underline"></i></button>
                <div class="editor-sep"></div>
                <button class="editor-btn" title="${_t('heading')}" type="button"><i class="fa-solid fa-heading"></i></button>
                <button class="editor-btn" title="${_t('link')}" type="button"><i class="fa-solid fa-link"></i></button>
                <button class="editor-btn" title="${_t('image')}" type="button"><i class="fa-regular fa-image"></i></button>
                <button class="editor-btn" title="${_t('code_block')}" type="button"><i class="fa-solid fa-code"></i></button>
                <div class="editor-sep"></div>
                <button class="editor-btn" title="${_t('ordered_list')}" type="button"><i class="fa-solid fa-list-ol"></i></button>
                <button class="editor-btn" title="${_t('unordered_list')}" type="button"><i class="fa-solid fa-list-ul"></i></button>
                <button class="editor-btn" title="${_t('quote_block')}" type="button"><i class="fa-solid fa-quote-left"></i></button>
              </div>
              <textarea class="form-textarea" id="topic-body" name="body" rows="12"
                        placeholder="${_t('content_placeholder')}"></textarea>
              <div class="form-hint" style="text-align:right">
                <span id="body-count">0</span> / 50000
              </div>
            </div>

            <!-- Tags -->
            <div class="form-group">
              <label class="form-label" for="topic-tags">${_t('tags')}</label>
              <input class="form-input" type="text" id="topic-tags" name="tags"
                     placeholder="${_t('tags_placeholder')}">
              <div class="form-hint">${_t('tags_hint')}</div>
            </div>

            <!-- Attachments -->
            <div class="form-group">
              <label class="form-label">${_t('attachments')}</label>
              <div class="drop-zone" id="drop-zone">
                <i class="fa-solid fa-cloud-arrow-up dz-icon"></i>
                <p class="dz-text">${_t('drag_drop_files')}</p>
                <p class="dz-hint">${_t('file_upload_hint')}</p>
                <input type="file" id="file-input" multiple accept=".jpg,.jpeg,.png,.pdf,.bin,.csv,.log" style="display:none">
              </div>
              <div class="file-list" id="file-list"></div>
            </div>

            <!-- Lock option -->
            <div class="form-group">
              <label class="lock-option">
                <input type="checkbox" name="lock_content" id="lock-content">
                <div class="lock-option-text">
                  <span class="lock-option-label"><i class="fa-solid fa-lock"></i> ${_t('lock_content_label')}</span>
                  <span class="lock-option-hint">${_t('lock_content_desc')}</span>
                </div>
              </label>
            </div>

            <!-- Submit -->
            <div class="create-topic-footer">
              <button class="btn btn-ghost" type="button" id="save-draft">
                <i class="fa-regular fa-floppy-disk"></i> ${_t('save_draft')}
              </button>
              <button class="btn btn-primary btn-lg" type="submit" id="submit-topic">
                <i class="fa-solid fa-paper-plane"></i> ${_t('post_topic')}
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>

    <!-- Sidebar hints -->
    <aside class="create-topic-sidebar">
      <div class="sidebar-card">
        <div class="sidebar-head"><i class="fa-solid fa-lightbulb"></i> ${_t('posting_tips_title')}</div>
        <div class="sidebar-body">
          <ul class="tips-list">
            <li>${_t('tip_clear_title')}</li>
            <li>${_t('tip_vehicle_info')}</li>
            <li>${_t('tip_software_version')}</li>
            <li>${_t('tip_share_logs')}</li>
            <li>${_t('tip_search_first')}</li>
            <li>${_t('tip_be_respectful')}</li>
          </ul>
        </div>
      </div>
      <div class="sidebar-card">
        <div class="sidebar-head"><i class="fa-solid fa-shield-halved"></i> ${_t('forum_rules_title')}</div>
        <div class="sidebar-body">
          <p style="color:var(--text-muted);font-size:.85rem;line-height:1.6">
            ${_t('forum_rules_desc')}
          </p>
          <a class="btn btn-ghost btn-sm" href="#" style="margin-top:.75rem">${_t('read_full_rules')}</a>
        </div>
      </div>
    </aside>
  </div>
</div>`;

        this._bindEvents();
    },

    _bindEvents() {
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                Router.navigateTo(a.dataset.view, {});
            });
        });

        // character counters
        document.getElementById('topic-title')?.addEventListener('input', e => {
            document.getElementById('title-count').textContent = e.target.value.length;
        });
        document.getElementById('topic-body')?.addEventListener('input', e => {
            document.getElementById('body-count').textContent = e.target.value.length;
        });

        // ── Editor toolbar ───────────────────────────────────────────────────
        const textarea = document.getElementById('topic-body');
        const _wrap = (open, close, placeholder = '') => {
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end   = textarea.selectionEnd;
            const sel   = textarea.value.slice(start, end) || placeholder;
            const before = textarea.value.slice(0, start);
            const after  = textarea.value.slice(end);
            textarea.value = before + open + sel + close + after;
            textarea.selectionStart = start + open.length;
            textarea.selectionEnd   = start + open.length + sel.length;
            textarea.focus();
            document.getElementById('body-count').textContent = textarea.value.length;
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
                } else if (title === _t('heading')) {
                    _wrap('<h3>', '</h3>', _t('heading_text'));
                } else if (title === _t('code_block')) {
                    _wrap('<pre><code>', '</code></pre>', _t('code_here'));
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
                    const pos = textarea.selectionStart;
                    const tag = `<img src="${src}" alt="image" class="post-img">`;
                    textarea.value = textarea.value.slice(0, pos) + tag + textarea.value.slice(pos);
                    textarea.selectionStart = textarea.selectionEnd = pos + tag.length;
                    textarea.focus();
                    document.getElementById('body-count').textContent = textarea.value.length;
                }
            });
        });

        const dz = document.getElementById('drop-zone');
        const fi = document.getElementById('file-input');

        document.getElementById('dz-click')?.addEventListener('click', () => fi?.click());
        dz?.addEventListener('click', () => fi?.click());
        fi?.addEventListener('change', () => this._handleFiles(fi.files));

        dz?.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('active'); });
        dz?.addEventListener('dragleave', () => dz.classList.remove('active'));
        dz?.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('active');
            this._handleFiles(e.dataTransfer.files);
        });

        // save draft
        document.getElementById('save-draft')?.addEventListener('click', () => {
            Toast.info(_t('draft_saved'));
        });

        // submit
        document.getElementById('create-topic-form')?.addEventListener('submit', async e => {
            e.preventDefault();
            const cat    = document.getElementById('topic-category').value;
            const title  = document.getElementById('topic-title').value.trim();
            const body   = document.getElementById('topic-body').value.trim();
            const prefix = document.querySelector('[name="prefix"]:checked')?.value ?? '';
            const locked      = false; // topic-lock is a mod-only action handled server-side
            const lockContent = document.querySelector('[name="lock_content"]')?.checked ?? false;

            if (!cat)   { Toast.warning(_t('select_category_error')); return; }
            if (!title) { Toast.warning(_t('enter_title_error')); return; }
            if (title.length < 10) { Toast.warning(_t('title_too_short')); return; }
            if (!body)  { Toast.warning(_t('enter_content_error')); return; }
            if (body.length < 30) { Toast.warning(_t('content_too_short')); return; }

            const btn = document.getElementById('submit-topic');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('posting')}`;
            btn.disabled = true;

            try {
                // Step 1 — Create the topic. The backend creates the topic record AND
                // its first post, then returns the new post_id so we can attach files.
                const data = await API.createTopic({
                    categoryId: parseInt(cat, 10),
                    title,
                    prefix,
                    content: body,
                    locked,
                    lockContent,
                });

                // Step 2 — Upload attachments sequentially now that we have a real post_id.
                // Attachments are optional; skip if none were added.
                const postId = data.post_id ?? data.topic_id ?? null;
                if (postId && this.attachments.length > 0) {
                    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${_t('uploading_files')}`;
                    const failed = [];
                    for (const file of this.attachments) {
                        try {
                            await API.uploadAttachment(file, postId);
                        } catch (uploadErr) {
                            failed.push(file.name);
                            console.error('Attachment upload failed:', file.name, uploadErr);
                        }
                    }
                    if (failed.length > 0) {
                        Toast.warning(_t('files_failed_upload', { count: failed.length, files: failed.join(', ') }));
                    }
                    this.attachments = [];
                }

                Toast.success(_t('topic_posted'));
                Router.navigateTo('thread-view', { id: data.topic_id });
            } catch (err) {
                Toast.error(err.message ?? _t('topic_post_failed'));
            } finally {
                btn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> ${_t('post_topic')}`;
                btn.disabled = false;
            }
        });
    },

    _handleFiles(files) {
        const list = document.getElementById('file-list');
        if (!list) return;
        const allowed = ['jpg','jpeg','png','pdf','bin','csv','log'];
        let added = 0;

        for (const file of files) {
            if (this.attachments.length + added >= 5) { Toast.warning(_t('max_attachments')); break; }
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(ext)) { Toast.error(_t('file_type_not_allowed', { ext })); continue; }
            if (file.size > 10 * 1024 * 1024) { Toast.error(_t('file_too_large', { filename: file.name })); continue; }

            this.attachments.push(file);
            added++;

            const item = document.createElement('div');
            item.className = 'file-item';
            item.innerHTML = `
<i class="fa-solid fa-file"></i>
<span class="file-name">${file.name}</span>
<span class="file-size">${this._fmtSize(file.size)}</span>
<button class="file-remove" type="button"><i class="fa-solid fa-xmark"></i></button>`;
            item.querySelector('.file-remove').addEventListener('click', () => {
                this.attachments = this.attachments.filter(f => f !== file);
                item.remove();
            });
            list.appendChild(item);
        }
    },

    _fmtSize(bytes) {
        if (bytes < 1024) return `${bytes}B`;
        if (bytes < 1024 * 1024) return `${(bytes/1024).toFixed(1)}KB`;
        return `${(bytes/1024/1024).toFixed(1)}MB`;
    }
};
