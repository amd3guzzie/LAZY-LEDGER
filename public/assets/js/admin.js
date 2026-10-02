/* Admin dashboard: system reports, user & role management, global categories, audit log. */
'use strict';

(() => {
  const { api, html, raw, fmtDate, fmtDateTime, toast, formData, submitting, showErrors } = LL;
  const $ = (sel) => document.querySelector(sel);
  const ME = Number(document.body.dataset.userId || 0);
  const state = { usersPage: 1, auditPage: 1, sort: 'created', dir: 'desc', users: new Map(), cats: new Map(), charts: {} };
  const handleError = (err) => toast(err.message || 'Could not load data.', 'error');
  const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

  async function loadOverview() {
    const o = await api('/admin/overview');
    $('#aTotal').textContent = o.users.total;
    $('#aTotalSub').textContent = `${o.users.customers} customers · ${o.users.suspended} suspended`;
    $('#aActive').textContent = o.users.active_this_week;
    $('#aNew').textContent = o.users.new_this_month;
    $('#aTx').textContent = `${o.transactions_this_month} transactions this month`;
    $('#aPending').textContent = o.requests.pending + o.tickets.pending + o.tickets.in_progress;
    $('#aPendingSub').textContent = `${o.requests.pending} requests · ${o.tickets.pending + o.tickets.in_progress} open tickets`;

    const growth = {
      labels: o.months.map((m) => m.label),
      datasets: [
        { type: 'bar', label: 'New customers', data: o.months.map((m) => m.signups), backgroundColor: '#fdd87d', yAxisID: 'y' },
        { type: 'line', label: 'Transactions', data: o.months.map((m) => m.transactions), borderColor: '#c14f27', backgroundColor: '#c14f27', yAxisID: 'y1', tension: 0.3 },
      ],
    };
    const roles = { labels: ['Customers', 'Staff', 'Admins'], datasets: [{ data: [o.users.customers, o.users.staff, o.users.admins], backgroundColor: ['#7fb7c9', '#fdd87d', '#c14f27'] }] };
    if (state.charts.growth) {
      state.charts.growth.data = growth; state.charts.growth.update();
      state.charts.roles.data = roles; state.charts.roles.update();
    } else {
      state.charts.growth = new Chart($('#growthChart'), {
        data: growth,
        options: {
          maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Signups' } }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 }, title: { display: true, text: 'Transactions' } } },
        },
      });
      state.charts.roles = new Chart($('#roleChart'), { type: 'doughnut', data: roles, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
    }

    const max = Math.max(1, ...o.top_categories.map((c) => c.uses));
    $('#topCats').innerHTML = o.top_categories.length
      ? o.top_categories.map((c) => html`<div class="budget-row"><div class="d-flex justify-content-between"><span class="name">${c.name}</span><span class="nums">${c.uses} uses</span></div>
          <div class="progress-ll"><div style="width:${(c.uses / max) * 100}%"></div></div></div>`).join('')
      : html`<p class="empty-hint">No transactions yet.</p>`;
    $('#recentActivity').innerHTML = o.recent_activity.length
      ? o.recent_activity.map((a) => html`<tr><td class="small text-muted-ll fw-normal text-nowrap">${fmtDateTime(a.created_at)}</td><td>${a.actor || actorFallback(a.action)}${a.actor_role ? raw(html` <span class="role-badge">${a.actor_role}</span>`) : ''}</td><td><code>${a.action}</code></td><td class="small fw-normal">${a.details}</td></tr>`).join('')
      : html`<tr><td class="empty-hint">No activity yet.</td></tr>`;
  }

  // Entries with no actor: failed logins for unknown emails, or users deleted since.
  const actorFallback = (action) => (String(action).startsWith('auth.') ? 'Anonymous visitor' : 'Deleted user');

  async function loadUsers() {
    const res = await api('/admin/users', { query: { ...formData($('#usersFilter')), page: state.usersPage, sort: state.sort, dir: state.dir } });
    state.users = new Map(res.data.map((u) => [u.id, u]));
    $('#usersBody').innerHTML = res.data.length
      ? res.data.map((u) => {
        const self = u.id === ME;
        return html`<tr>
          <td class="fw-800">${u.first_name} ${u.last_name}${self ? raw(' <span class="role-badge">You</span>') : ''}</td>
          <td>${u.email}</td>
          <td>${self ? raw(html`<span class="status-pill role-pill">${u.role}</span>`) : raw(html`<select class="form-select form-select-sm" style="min-width:8.5rem" data-role="${u.id}" aria-label="Role for ${u.email}">
              ${['customer', 'staff', 'admin'].map((r) => raw(html`<option value="${r}" ${r === u.role ? 'selected' : ''}>${r[0].toUpperCase() + r.slice(1)}</option>`))}</select>`)}</td>
          <td>${LL.statusPill(u.status)}</td>
          <td class="small">${fmtDateTime(u.last_login_at)}</td>
          <td class="small">${fmtDate(u.created_at.slice(0, 10))}</td>
          <td class="text-end text-nowrap">${self ? '' : raw(html`
            <button class="btn btn-sm ${u.status === 'active' ? 'btn-outline-warning' : 'btn-outline-success'} fw-bold" data-toggle-status="${u.id}">${u.status === 'active' ? 'Suspend' : 'Restore'}</button>
            <button class="btn btn-sm btn-outline-danger fw-bold" data-delete-user="${u.id}" aria-label="Delete ${u.email}"><i class="bi bi-trash-fill"></i></button>`)}</td>
        </tr>`;
      }).join('')
      : html`<tr><td colspan="7" class="empty-hint">No users match.</td></tr>`;
    document.querySelectorAll('th[data-sort]').forEach((th) => {
      th.querySelector('.sort-ind').textContent = th.dataset.sort === state.sort ? (state.dir === 'asc' ? '▲' : '▼') : '';
    });
    LL.pagination($('#usersPager'), res.meta, (p) => { state.usersPage = p; loadUsers().catch(handleError); });
  }

  async function loadCategories() {
    const res = await api('/admin/categories');
    state.cats = new Map(res.data.map((c) => [c.id, c]));
    $('#catBody').innerHTML = res.data.map((c) => html`<tr>
        <td class="fw-800">${c.name}</td><td class="text-capitalize">${c.type}</td><td>${c.transaction_count}</td><td>${c.user_count}</td>
        <td class="text-end text-nowrap">
          <button class="btn-icon" data-edit-cat="${c.id}" aria-label="Edit ${c.name}"><i class="bi bi-pencil-fill"></i></button>
          <button class="btn-icon" data-delete-cat="${c.id}" aria-label="Delete ${c.name}"><i class="bi bi-trash-fill"></i></button>
        </td></tr>`).join('') || html`<tr><td colspan="5" class="empty-hint">No global categories.</td></tr>`;
  }

  async function loadAudit() {
    const res = await api('/admin/audit-log', { query: { ...formData($('#auditFilter')), page: state.auditPage } });
    $('#auditBody').innerHTML = res.data.length
      ? res.data.map((a) => html`<tr>
          <td class="text-nowrap small">${fmtDateTime(a.created_at)}</td>
          <td>${a.actor_name || actorFallback(a.action)}<div class="small text-muted-ll fw-normal">${a.actor_email || ''} ${a.actor_role ? `· ${a.actor_role}` : ''}</div></td>
          <td><code>${a.action}</code></td><td class="small">${a.target_type} #${a.target_id ?? '—'}</td><td class="small fw-normal">${a.details}</td></tr>`).join('')
      : html`<tr><td colspan="5" class="empty-hint">No entries match.</td></tr>`;
    LL.pagination($('#auditPager'), res.meta, (p) => { state.auditPage = p; loadAudit().catch(handleError); });
  }

  const loaders = { overview: loadOverview, users: loadUsers, categories: loadCategories, audit: loadAudit };

  document.addEventListener('DOMContentLoaded', () => {
    LL.bindLogout();

    document.addEventListener('change', async (e) => {
      const sel = e.target.closest('[data-role]');
      if (!sel) return;
      const u = state.users.get(Number(sel.dataset.role));
      if (!(await LL.confirm(`Change ${u.email} from ${u.role} to ${sel.value}?`, { okText: 'Change role' }))) { sel.value = u.role; return; }
      try { await api(`/admin/users/${u.id}`, { method: 'PUT', body: { role: sel.value } }); toast('Role updated.'); loadUsers(); } catch (err) { sel.value = u.role; handleError(err); }
    });

    document.addEventListener('click', async (e) => {
      const t = e.target.closest('[data-toggle-status],[data-delete-user],[data-edit-cat],[data-delete-cat]');
      if (!t) return;
      try {
        if (t.dataset.toggleStatus) {
          const u = state.users.get(Number(t.dataset.toggleStatus));
          const suspend = u.status === 'active';
          if (!(await LL.confirm(suspend ? `Suspend ${u.email}? They will be blocked from logging in; their data is kept.` : `Restore access for ${u.email}?`, { okText: suspend ? 'Suspend' : 'Restore' }))) return;
          await api(`/admin/users/${u.id}`, { method: 'PUT', body: { status: suspend ? 'suspended' : 'active' } });
          toast(suspend ? 'User suspended.' : 'User restored.');
          loadUsers();
        } else if (t.dataset.deleteUser) {
          const u = state.users.get(Number(t.dataset.deleteUser));
          if (!(await LL.confirm(`Permanently delete ${u.email} and all of their data? This cannot be undone.`, { okText: 'Delete user' }))) return;
          await api(`/admin/users/${u.id}`, { method: 'DELETE' });
          toast('User deleted.');
          loadUsers();
        } else if (t.dataset.editCat) {
          const c = state.cats.get(Number(t.dataset.editCat));
          openCat(c);
        } else if (t.dataset.deleteCat) {
          const c = state.cats.get(Number(t.dataset.deleteCat));
          if (!(await LL.confirm(`Delete "${c.name}"? ${c.transaction_count} transaction(s) will become Uncategorized and related budgets are removed.`, { okText: 'Delete category' }))) return;
          await api(`/admin/categories/${c.id}`, { method: 'DELETE' });
          toast('Category deleted.');
          loadCategories();
        }
      } catch (err) { handleError(err); }
    });

    $('#newUserBtn').addEventListener('click', () => { $('#userForm').reset(); LL.clearErrors($('#userForm')); modal('userModal').show(); });
    $('#userForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      d.password = form.elements.password.value;
      if (!d.first_name) return showErrors(form, { fields: { first_name: 'First name is required.' } });
      submitting(form, async () => {
        await api('/admin/users', { method: 'POST', body: d });
        modal('userModal').hide();
        toast('Account created.');
        loadUsers();
      });
    });

    function openCat(c) {
      const form = $('#catForm');
      form.reset();
      LL.clearErrors(form);
      form.elements.id.value = c?.id || '';
      form.elements.name.value = c?.name || '';
      form.elements.type.value = c?.type || 'expense';
      $('#catModalTitle').textContent = c ? 'Edit category' : 'New category';
      modal('catModal').show();
    }
    $('#newCatBtn').addEventListener('click', () => openCat());
    $('#catForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      if (d.name.length < 2) return showErrors(form, { fields: { name: 'Name must be at least 2 characters.' } });
      const id = d.id; delete d.id;
      submitting(form, async () => {
        await api(id ? `/admin/categories/${id}` : '/admin/categories', { method: id ? 'PUT' : 'POST', body: d });
        modal('catModal').hide();
        toast(id ? 'Category updated.' : 'Category created. It is now available to all customers.');
        loadCategories();
      });
    });

    document.querySelectorAll('th[data-sort]').forEach((th) => th.addEventListener('click', () => {
      if (state.sort === th.dataset.sort) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      else { state.sort = th.dataset.sort; state.dir = th.dataset.sort === 'name' || th.dataset.sort === 'email' ? 'asc' : 'desc'; }
      state.usersPage = 1;
      loadUsers().catch(handleError);
    }));

    [['#usersFilter', () => { state.usersPage = 1; loadUsers().catch(handleError); }], ['#auditFilter', () => { state.auditPage = 1; loadAudit().catch(handleError); }]].forEach(([sel, reload]) => {
      const form = $(sel);
      form.addEventListener('submit', (e) => { e.preventDefault(); reload(); });
      form.addEventListener('change', reload);
      form.querySelector('input[type="search"]').addEventListener('input', LL.debounce(reload, 350));
    });

    LL.router(Object.keys(loaders), (v) => loaders[v]().catch(handleError));
  });
})();
