/* Staff dashboard: process category requests and support tickets, look up customer status. */
'use strict';

(() => {
  const { api, html, fmtDate, fmtDateTime, toast, formData, submitting } = LL;
  const $ = (sel) => document.querySelector(sel);
  const pages = { requests: 1, tickets: 1, users: 1 };
  const tickets = new Map();
  let chart;

  const handleError = (err) => toast(err.message || 'Could not load data.', 'error');

  async function loadOverview() {
    const o = await api('/staff/overview');
    $('#sPendingReq').textContent = o.requests.pending;
    $('#sOpenTix').textContent = o.tickets.pending + o.tickets.in_progress;
    $('#sResolved').textContent = o.tickets.resolved;
    $('#sCustomers').textContent = o.customers;
    setBadges(o);

    const data = {
      labels: ['Pending', 'Approved', 'Rejected'],
      datasets: [{ data: [o.requests.pending, o.requests.approved, o.requests.rejected], backgroundColor: ['#fdd87d', '#7ed957', '#e5484d'] }],
    };
    if (chart) { chart.data = data; chart.update(); } else {
      chart = new Chart($('#reqChart'), { type: 'doughnut', data, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
    }
    $('#recentBody').innerHTML = o.recent.length
      ? o.recent.map((r) => html`<tr><td class="text-capitalize">${r.kind}</td><td>${r.title}</td><td>${r.email}</td><td>${LL.statusPill(r.status)}</td></tr>`).join('')
      : html`<tr><td colspan="4" class="empty-hint">Nothing yet.</td></tr>`;
  }

  function setBadges(o) {
    const set = (view, n) => {
      const b = document.querySelector(`[data-badge="${view}"]`);
      b.textContent = n; b.classList.toggle('d-none', !n);
    };
    set('requests', o.requests.pending);
    set('tickets', o.tickets.pending);
  }

  async function loadRequests() {
    const res = await api('/staff/category-requests', { query: { ...formData($('#reqFilter')), page: pages.requests } });
    $('#reqBody').innerHTML = res.data.length
      ? res.data.map((r) => html`<tr>
          <td>${fmtDate(r.created_at.slice(0, 10))}</td>
          <td class="fw-800">${r.requested_name}</td>
          <td class="text-capitalize">${r.requested_type}</td>
          <td>${r.requester_name}<div class="small text-muted-ll fw-normal">${r.requester_email}</div></td>
          <td class="small fw-normal text-muted-ll">${r.reason || '—'}</td>
          <td>${LL.statusPill(r.status)}${r.handler_name ? LL.raw(html`<div class="small text-muted-ll fw-normal">by ${r.handler_name}</div>`) : ''}</td>
          <td class="text-end text-nowrap">${r.status === 'pending' ? LL.raw(html`
            <button class="btn btn-sm btn-success fw-bold" data-resolve="${r.id}" data-status="approved" data-name="${r.requested_name}">Approve</button>
            <button class="btn btn-sm btn-outline-danger fw-bold" data-resolve="${r.id}" data-status="rejected" data-name="${r.requested_name}">Reject</button>`) : '—'}</td>
        </tr>`).join('')
      : html`<tr><td colspan="7" class="empty-hint">No requests match.</td></tr>`;
    LL.pagination($('#reqPager'), res.meta, (p) => { pages.requests = p; loadRequests().catch(handleError); });
  }

  async function loadTickets() {
    const res = await api('/staff/tickets', { query: { ...formData($('#tixFilter')), page: pages.tickets } });
    tickets.clear();
    res.data.forEach((t) => tickets.set(t.id, t));
    $('#tixBody').innerHTML = res.data.length
      ? res.data.map((t) => html`<tr>
          <td>${fmtDate(t.created_at.slice(0, 10))}</td>
          <td class="fw-800">${t.subject}</td>
          <td>${t.requester_name}<div class="small text-muted-ll fw-normal">${t.requester_email}</div></td>
          <td>${LL.statusPill(t.status)}</td>
          <td>${t.handler_name || '—'}</td>
          <td class="text-end"><button class="btn btn-sm btn-ll" data-ticket="${t.id}">Open</button></td>
        </tr>`).join('')
      : html`<tr><td colspan="6" class="empty-hint">No tickets match.</td></tr>`;
    LL.pagination($('#tixPager'), res.meta, (p) => { pages.tickets = p; loadTickets().catch(handleError); });
  }

  async function loadUsers() {
    const res = await api('/staff/users', { query: { ...formData($('#usersFilter')), page: pages.users } });
    $('#usersBody').innerHTML = res.data.length
      ? res.data.map((u) => html`<tr>
          <td class="fw-800">${u.first_name} ${u.last_name}</td><td>${u.email}</td><td>${LL.statusPill(u.status)}</td>
          <td>${u.open_tickets}</td><td>${fmtDateTime(u.last_login_at)}</td><td>${fmtDate(u.created_at.slice(0, 10))}</td></tr>`).join('')
      : html`<tr><td colspan="6" class="empty-hint">No customers match.</td></tr>`;
    LL.pagination($('#usersPager'), res.meta, (p) => { pages.users = p; loadUsers().catch(handleError); });
  }

  const loaders = { overview: loadOverview, requests: loadRequests, tickets: loadTickets, users: loadUsers };

  function openTicket(t) {
    const form = $('#ticketForm');
    form.reset();
    LL.clearErrors(form);
    form.elements.id.value = t.id;
    $('#ticketModalTitle').textContent = t.subject;
    $('#ticketMeta').textContent = `From ${t.requester_name} (${t.requester_email}) · ${fmtDateTime(t.created_at)}`;
    $('#ticketMessage').textContent = t.message;
    form.elements.staff_reply.value = t.staff_reply || '';
    form.elements.status.value = t.status === 'pending' ? 'in_progress' : t.status;
    bootstrap.Modal.getOrCreateInstance('#ticketModal').show();
  }

  document.addEventListener('DOMContentLoaded', () => {
    LL.bindLogout();

    document.addEventListener('click', async (e) => {
      const r = e.target.closest('[data-resolve]');
      if (r) {
        const approve = r.dataset.status === 'approved';
        const ok = await LL.confirm(
          approve ? `Approve "${r.dataset.name}"? It will be added to this customer's categories.` : `Reject "${r.dataset.name}"? The customer will be notified.`,
          { okText: approve ? 'Approve' : 'Reject', title: approve ? 'Approve request' : 'Reject request' },
        );
        if (!ok) return;
        try {
          await api(`/staff/category-requests/${r.dataset.resolve}`, { method: 'PUT', body: { status: r.dataset.status } });
          toast(approve ? 'Request approved.' : 'Request rejected.');
          loadRequests().catch(handleError);
          api('/staff/overview').then(setBadges).catch(() => {});
        } catch (err) { handleError(err); }
        return;
      }
      const t = e.target.closest('[data-ticket]');
      if (t) openTicket(tickets.get(Number(t.dataset.ticket)));
    });

    $('#ticketForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      submitting(form, async () => {
        await api(`/staff/tickets/${d.id}`, { method: 'PUT', body: { status: d.status, staff_reply: d.staff_reply } });
        bootstrap.Modal.getInstance('#ticketModal').hide();
        toast('Ticket updated and customer notified.');
        loadTickets().catch(handleError);
        api('/staff/overview').then(setBadges).catch(() => {});
      });
    });

    [['#reqFilter', 'requests', loadRequests], ['#tixFilter', 'tickets', loadTickets], ['#usersFilter', 'users', loadUsers]].forEach(([sel, key, fn]) => {
      const form = $(sel);
      const reload = () => { pages[key] = 1; fn().catch(handleError); };
      form.addEventListener('submit', (e) => { e.preventDefault(); reload(); });
      form.addEventListener('change', reload);
      form.querySelector('input[type="search"]').addEventListener('input', LL.debounce(reload, 350));
    });

    api('/staff/overview').then(setBadges).catch(() => {});
    LL.router(Object.keys(loaders), (v) => loaders[v]().catch(handleError));
  });
})();
