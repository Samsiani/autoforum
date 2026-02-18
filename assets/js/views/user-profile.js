// User Profile — public view of any forum member
const UserProfileView = {

    async render(params = {}) {
        const el = document.getElementById('main-container');
        if (!el) return;

        const userId = parseInt(params.id ?? 0, 10);
        if (!userId) {
            Router.navigateTo('home');
            return;
        }

        // Skeleton
        el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="skeleton skeleton-block" style="height:180px;border-radius:16px;margin-bottom:1.5rem"></div>
  <div class="skeleton skeleton-line" style="width:60%;height:1rem;margin-bottom:1rem"></div>
  <div class="skeleton skeleton-line" style="width:40%;height:1rem"></div>
</div>`;

        let user;
        try {
            user = await API.getUserProfile(userId);
        } catch (err) {
            el.innerHTML = `
<div class="page-wrap fade-up">
  <div class="empty-state">
    <i class="fa-solid fa-user-slash"></i>
    <h3>User not found</h3>
    <p>${err.message ?? 'This user does not exist or has been removed.'}</p>
    <button class="btn btn-primary" type="button" onclick="Router.navigateTo('home')">
      <i class="fa-solid fa-house"></i> Go Home
    </button>
  </div>
</div>`;
            return;
        }

        const isSelf = State.currentUser && State.currentUser.id === userId;

        el.innerHTML = `
<div class="page-wrap fade-up">
  <nav class="breadcrumbs">
    <a href="#" data-view="home">Home</a>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>Members</span>
    <span class="sep"><i class="fa-solid fa-angle-right"></i></span>
    <span>${user.name}</span>
  </nav>

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="profile-banner"></div>
    <div class="profile-hero-body">
      <div class="profile-avatar-wrap">
        <img src="${user.avatar}" alt="${user.name}" class="profile-avatar">
        <span class="profile-status-dot"></span>
      </div>
      <div class="profile-info">
        <h1 class="profile-name">${user.name}</h1>
        <div class="profile-role">${user.role}</div>
        ${user.location ? `<div class="profile-location"><i class="fa-solid fa-location-dot"></i> ${user.location}</div>` : ''}
        <div class="profile-stats">
          <div class="pstat-item">
            <span class="pstat-val">${(user.postCount || 0).toLocaleString()}</span>
            <span class="pstat-lbl">Posts</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${(user.reputation || 0).toLocaleString()}</span>
            <span class="pstat-lbl">Reputation</span>
          </div>
          <div class="pstat-item">
            <span class="pstat-val">${user.joined || '—'}</span>
            <span class="pstat-lbl">Joined</span>
          </div>
        </div>
      </div>
      ${isSelf ? `
      <div style="margin-left:auto;align-self:flex-start">
        <button class="btn btn-ghost btn-sm" type="button" onclick="Router.navigateTo('dashboard')">
          <i class="fa-solid fa-pen"></i> Edit Profile
        </button>
      </div>` : ''}
    </div>
  </div>

  <!-- Stats cards -->
  <div class="overview-grid" style="margin-top:1.5rem">
    <div class="ov-card">
      <i class="fa-solid fa-comment-dots ov-icon blue"></i>
      <div class="ov-val">${(user.postCount || 0).toLocaleString()}</div>
      <div class="ov-lbl">Total Posts</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-star ov-icon yellow"></i>
      <div class="ov-val">${(user.reputation || 0).toLocaleString()}</div>
      <div class="ov-lbl">Reputation</div>
    </div>
    <div class="ov-card">
      <i class="fa-solid fa-calendar ov-icon purple"></i>
      <div class="ov-val" style="font-size:1rem">${user.joined || '—'}</div>
      <div class="ov-lbl">Member Since</div>
    </div>
  </div>

  ${user.signature ? `
  <div class="card" style="margin-top:1.5rem">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-pen-nib"></i> Signature</span></div>
    <div class="card-body" style="color:var(--text-muted);font-size:.9rem;line-height:1.7">
      ${user.signature}
    </div>
  </div>` : ''}

  ${user.licenses && user.licenses.length ? `
  <div class="card" style="margin-top:1.5rem">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-key"></i> License${user.licenses.length > 1 ? 's' : ''}</span>
    </div>
    <div class="card-body" style="padding:0">
      <table style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
          <tr style="border-bottom:1px solid var(--border-soft)">
            <th style="padding:.6rem 1rem;text-align:left;color:var(--text-dim);font-weight:600">Key</th>
            <th style="padding:.6rem 1rem;text-align:left;color:var(--text-dim);font-weight:600">Status</th>
            <th style="padding:.6rem 1rem;text-align:left;color:var(--text-dim);font-weight:600">Expires</th>
          </tr>
        </thead>
        <tbody>
          ${user.licenses.map(l => `
          <tr style="border-bottom:1px solid var(--border-soft)">
            <td style="padding:.6rem 1rem"><code style="font-size:.8rem;color:var(--text-light)">${l.key}</code></td>
            <td style="padding:.6rem 1rem">
              <span style="display:inline-block;padding:.2rem .55rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;
                background:${l.status==='active'?'rgba(34,197,94,.15)':'rgba(234,179,8,.15)'};
                color:${l.status==='active'?'#22c55e':'#eab308'}">
                ${l.status}
              </span>
            </td>
            <td style="padding:.6rem 1rem;color:var(--text-muted)">${l.expires_at ?? 'Never'}</td>
          </tr>`).join('')}
        </tbody>
      </table>
    </div>
  </div>` : ''}

</div>`;

        // Wire breadcrumb nav
        document.querySelectorAll('[data-view]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                Router.navigateTo(a.dataset.view, {});
            });
        });
    }
};
