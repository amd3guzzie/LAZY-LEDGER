/* Customer dashboard: Home, Accounts, Transactions, Budgets, Stats, Profile. */
'use strict';

(() => {
  const { api, html, raw, money, fmtDate, fmtDateTime, toast, formData, submitting, showErrors } = LL;
  const $ = (sel) => document.querySelector(sel);

  const COLORS = ['#c14f27', '#fdd87d', '#7fb7c9', '#c77a5f', '#7ed957', '#f59366', '#5b8fa3', '#e8b04a', '#9e3f1d', '#b0d2dc'];
  const TYPE_LABELS = { cash: 'Cash', bank: 'Bank', e_wallet: 'E-wallet', savings: 'Savings', credit_card: 'Credit card' };

  const state = {
    view: 'home',
    month: LL.monthKey(),
    accounts: [],
    categories: [],
    me: null,
    tx: { page: 1, sort: 'date', dir: 'desc', rows: new Map() },
    budgets: [],
    stats: { view: 'expense', months: 6 },
    charts: {},
  };

  const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

  // ---------- Reference data ----------
  async function loadRefs() {
    const [acc, cats] = await Promise.all([api('/accounts'), api('/categories')]);
    state.accounts = acc.data;
    state.categories = cats.data;
    fillSelect($('#fAccount'), state.accounts, 'All accounts');
    fillSelect($('#fCategory'), state.categories, 'All categories', [{ id: 'none', name: 'Uncategorized' }]);
  }

  function fillSelect(select, items, placeholder, extra = []) {
    const current = select.value;
    select.innerHTML = html`<option value="">${placeholder}</option>${[...items, ...extra].map((i) => raw(html`<option value="${i.id}">${i.name}${i.type && select.id === 'fCategory' ? ` (${i.type})` : ''}</option>`))}`;
    select.value = current;
  }

  function fillTxCategories(type, selected) {
    const sel = $('#txCategory');
    const cats = state.categories.filter((c) => c.type === type);
    sel.innerHTML = html`<option value="">Uncategorized</option>${cats.map((c) => raw(html`<option value="${c.id}">${c.name}</option>`))}`;
    sel.value = selected ?? '';
  }

  // ---------- Home ----------
  async function loadHome() {
    $('#monthLabel').textContent = LL.monthLabel(state.month);
    $('#nextMonth').disabled = state.month >= LL.monthKey();
    const s = await api('/stats/summary', { query: { month: state.month } });
    state.summary = s;

    const isNew = s.counts.transactions === 0;
    $('#onboarding').hidden = !isNew;
    $('#homeMain').hidden = isNew;
    if (isNew) {
      ['accounts', 'budgets', 'transactions'].forEach((k) => {
        const step = document.querySelector(`[data-step="${k}"] .step-num`);
        step.classList.toggle('done', s.counts[k] > 0);
        if (s.counts[k] > 0) step.innerHTML = '<i class="bi bi-check-lg"></i>';
      });
      return;
    }

    $('#leftToSpend').textContent = s.budgeted > 0 ? money(s.left_to_spend) : '—';
    $('#leftToSpend').classList.toggle('text-expense', s.left_to_spend < 0);
    $('#pctUsed').textContent = s.pct_used !== null ? `${Math.round(s.pct_used)}% used` : 'Set a budget to track this';
    $('#incomeTotal').textContent = money(s.income);
    $('#expenseTotal').textContent = money(s.expense);
    setChange($('#incomeChange'), s.income_change, true);
    setChange($('#expenseChange'), s.expense_change, false);

    $('#alertBox').innerHTML = s.alerts.length
      ? html`<div class="alert alert-ll d-flex gap-2 align-items-start" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><div>${s.alerts.map((a) => raw(html`<div>${a.category_name}: ${a.pct > 100 ? 'over budget' : 'almost at its limit'} (${money(a.spent)} of ${money(a.amount_limit)})</div>`))}</div></div>`
      : '';

    $('#homeBudgets').innerHTML = s.budgets.length
      ? s.budgets.slice(0, 4).map(budgetRow).join('')
      : html`<p class="empty-hint mb-0">No budgets for this month. <a href="#budgets">Create one</a>.</p>`;

    $('#homeRecent').innerHTML = s.recent.length
      ? s.recent.map((t) => html`<tr><td>${t.description}<div class="small text-muted-ll fw-normal">${fmtDate(t.transaction_date)} · ${t.category_name || 'Uncategorized'}</div></td>
          <td class="text-end amount ${t.type === 'income' ? 'text-income' : 'text-expense'}">${t.type === 'income' ? '+' : '-'}${money(t.amount)}</td></tr>`).join('')
      : html`<tr><td colspan="2" class="empty-hint">No transactions yet.</td></tr>`;

    $('#homeUpcoming').innerHTML = s.upcoming.length
      ? s.upcoming.map((u) => html`<li class="d-flex justify-content-between fw-bold py-1"><span class="text-salmon"><i class="bi bi-credit-card"></i> ${u.name} due ${fmtDate(u.due_date)}</span><span class="text-expense">${money(u.balance)}</span></li>`).join('')
      : html`<li class="empty-hint">No bills due in the next 30 days.</li>`;

    renderDonut(s.breakdown);
  }

  function setChange(el, pct, goodWhenUp) {
    if (pct === null || pct === undefined) { el.textContent = 'No data last month'; el.className = 'stat-sub'; return; }
    const up = pct >= 0;
    el.textContent = `${up ? '+' : ''}${pct}% vs last month`;
    el.className = 'stat-sub ' + ((up === goodWhenUp) ? 'text-income' : 'text-expense');
  }

  function budgetRow(b) {
    return html`<div class="budget-row">
      <div class="d-flex justify-content-between align-items-baseline gap-2"><span class="name">${b.category_name}</span><span class="nums">${money(b.spent)} / ${money(b.amount_limit)}</span></div>
      ${LL.progressBar(b.pct)}</div>`;
  }

  function renderDonut(breakdown) {
    const empty = !breakdown.length;
    $('#donutEmpty').hidden = !empty;
    $('#donutChart').parentElement.hidden = empty;
    if (empty) return;
    const data = {
      labels: breakdown.map((b) => b.name),
      datasets: [{ data: breakdown.map((b) => b.total * LL.currency.rate), backgroundColor: COLORS, borderWidth: 2 }],
    };
    if (state.charts.donut) { state.charts.donut.data = data; state.charts.donut.update(); return; }
    state.charts.donut = new Chart($('#donutChart'), {
      type: 'doughnut',
      data,
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { font: { family: 'League Spartan', weight: 700 } } },
          tooltip: { callbacks: { label: (c) => ` ${c.label}: ${money(c.raw / LL.currency.rate)} (${breakdown[c.dataIndex].pct}%)` } },
        },
      },
    });
  }

  // External API: Frankfurter exchange rates (ECB data), PHP -> USD.
  async function toggleCurrency() {
    const btn = $('#currencyBtn');
    if (LL.currency.code === 'USD') {
      LL.currency.code = 'PHP'; LL.currency.rate = 1;
      btn.textContent = 'View in USD';
      $('#rateNote').hidden = true;
    } else {
      btn.disabled = true;
      try {
        const res = await fetch('https://api.frankfurter.dev/v1/latest?base=PHP&symbols=USD');
        if (!res.ok) throw new Error();
        const data = await res.json();
        LL.currency.code = 'USD'; LL.currency.rate = data.rates.USD;
        btn.textContent = 'View in PHP';
        $('#rateNote').textContent = `Showing USD at ₱1 = $${data.rates.USD} (ECB rate for ${data.date}, via Frankfurter).`;
        $('#rateNote').hidden = false;
      } catch {
        toast('Could not fetch exchange rates right now.', 'error');
      } finally {
        btn.disabled = false;
      }
    }
    loadView(state.view);
  }

  // ---------- Accounts ----------
  async function loadAccounts() {
    const res = await api('/accounts');
    state.accounts = res.data;
    $('#netWorth').textContent = money(res.net_worth);
    $('#netWorth').classList.toggle('text-expense', res.net_worth < 0);
    $('#accountsCount').textContent = `Across your ${res.data.length} account${res.data.length === 1 ? '' : 's'}`;
    $('#accountsGrid').innerHTML = res.data.length
      ? res.data.map((a) => html`<div class="col-sm-6 col-lg-4">
          <div class="ll-card account-card">
            <div class="d-flex justify-content-between align-items-start">
              <div><div class="stat-label">${a.name}</div><span class="role-badge">${TYPE_LABELS[a.type]}</span></div>
              <button class="btn-icon" data-edit-account="${a.id}" aria-label="Edit ${a.name}"><i class="bi bi-pencil-fill"></i></button>
            </div>
            <div class="stat-value mt-2 ${a.balance < 0 ? 'text-expense' : ''}">${money(a.balance)}</div>
            <div class="text-end small fw-bold ${a.due_date ? 'text-expense' : 'text-muted-ll'}">${a.due_date ? `Due ${fmtDate(a.due_date)}` : `${a.transaction_count} transactions`}</div>
          </div></div>`).join('')
      : html`<div class="col-12"><p class="empty-hint">No accounts yet. Add your cash, bank, e-wallet or credit card.</p></div>`;
  }

  function openAccountModal(account) {
    const form = $('#accountForm');
    form.reset();
    LL.clearErrors(form);
    form.elements.id.value = account?.id || '';
    $('#accountModalTitle').textContent = account ? 'Edit account' : 'Add account';
    $('#accDelete').hidden = !account;
    if (account) {
      form.elements.name.value = account.name;
      form.elements.type.value = account.type;
      form.elements.opening_balance.value = Math.abs(account.opening_balance);
      form.elements.due_date.value = account.due_date || '';
    }
    syncAccountType();
    modal('accountModal').show();
  }

  function syncAccountType() {
    const isCard = $('#accType').value === 'credit_card';
    $('#accDueWrap').hidden = !isCard;
    $('#accOpeningLabel').textContent = isCard ? 'Amount currently owed (₱)' : 'Starting balance (₱)';
  }

  // ---------- Transactions ----------
  function txQuery() {
    const f = formData($('#txFilterForm'));
    return { ...f, q: $('#txSearch').value.trim(), sort: state.tx.sort, dir: state.tx.dir };
  }

  async function loadTransactions() {
    const body = $('#txBody');
    const res = await api('/transactions', { query: { ...txQuery(), page: state.tx.page, per_page: 10 } });
    state.tx.rows = new Map(res.data.map((t) => [t.id, t]));
    body.innerHTML = res.data.length
      ? res.data.map((t) => html`<tr>
          <td>${fmtDate(t.transaction_date)}</td>
          <td>${t.description}</td>
          <td>${t.category_name || 'Uncategorized'}</td>
          <td>${t.account_name}</td>
          <td class="text-end amount ${t.type === 'income' ? 'text-income' : 'text-expense'}">${t.type === 'income' ? '+' : '-'} ${money(t.amount)}</td>
          <td class="text-end text-nowrap">
            <button class="btn-icon" data-edit-tx="${t.id}" aria-label="Edit ${t.description}"><i class="bi bi-pencil-fill"></i></button>
            <button class="btn-icon" data-del-tx="${t.id}" aria-label="Delete ${t.description}"><i class="bi bi-trash-fill"></i></button>
          </td></tr>`).join('')
      : html`<tr><td colspan="6" class="empty-hint">No transactions found.</td></tr>`;

    document.querySelectorAll('th[data-sort]').forEach((th) => {
      const active = th.dataset.sort === state.tx.sort;
      th.querySelector('.sort-ind').textContent = active ? (state.tx.dir === 'asc' ? '▲' : '▼') : '';
      th.setAttribute('aria-sort', active ? (state.tx.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
    LL.pagination($('#txPager'), res.meta, (p) => { state.tx.page = p; loadTransactions(); });
  }

  const reloadTx = () => { state.tx.page = 1; loadTransactions().catch(handleLoadError); };

  function openTxModal(tx) {
    if (!state.accounts.length) {
      toast('Add an account first so we know where the money lives.', 'info');
      openAccountModal();
      return;
    }
    const form = $('#txForm');
    form.reset();
    LL.clearErrors(form);
    $('#txAccount').innerHTML = state.accounts.map((a) => html`<option value="${a.id}">${a.name}</option>`).join('');
    form.elements.id.value = tx?.id || '';
    $('#txModalTitle').textContent = tx ? 'Edit transaction' : 'Add transaction';
    const type = tx?.type || 'expense';
    form.querySelector(`input[name="type"][value="${type}"]`).checked = true;
    fillTxCategories(type, tx?.category_id);
    form.elements.description.value = tx?.description || '';
    form.elements.amount.value = tx?.amount ?? '';
    form.elements.transaction_date.value = tx?.transaction_date || LL.today();
    form.elements.account_id.value = tx?.account_id || state.accounts[0].id;
    modal('txModal').show();
  }

  // ---------- Budgets ----------
  async function loadBudgets() {
    const [res, reqs] = await Promise.all([api('/budgets', { query: { month: state.month } }), api('/category-requests')]);
    state.budgets = res.data;
    state.budgetSummary = res.summary;
    const s = res.summary;
    $('#budgetMonth').textContent = LL.monthLabel(res.month);
    $('#bBudgeted').textContent = money(s.budgeted);
    $('#bBudgetedSub').textContent = s.monthly_budget !== null ? 'Your monthly total' : 'Sum of category budgets';
    $('#bSpent').textContent = money(s.spent);
    $('#bPct').textContent = s.pct_used !== null ? `${Math.round(s.pct_used)}% used` : '';
    $('#bUnassigned').textContent = s.unassigned !== null ? money(s.unassigned) : '—';
    $('#bUnassigned').classList.toggle('text-expense', s.unassigned !== null && s.unassigned < 0);

    $('#budgetList').innerHTML = res.data.length
      ? res.data.map((b) => html`<div class="col-md-6"><div class="budget-row">
          <div class="d-flex justify-content-between align-items-baseline gap-2">
            <span class="name">${b.category_name} <button class="btn-icon" data-edit-budget="${b.id}" aria-label="Edit ${b.category_name} budget"><i class="bi bi-pencil-fill small"></i></button></span>
            <span class="nums">${money(b.spent)} / ${money(b.amount_limit)}</span></div>
          ${LL.progressBar(b.pct)}
          <div class="small fw-bold ${b.remaining < 0 ? 'text-expense' : 'text-muted-ll'}">${b.remaining < 0 ? `${money(-b.remaining)} over` : `${money(b.remaining)} left`}</div>
        </div></div>`).join('')
      : html`<div class="col-12"><p class="empty-hint">No category budgets for this month yet. Click “New budget” to give every peso a job.</p></div>`;

    $('#requestList').innerHTML = reqs.data.length
      ? reqs.data.map((r) => html`<tr><td>${r.requested_name}</td><td class="text-capitalize">${r.requested_type}</td><td>${LL.statusPill(r.status)}</td><td>${fmtDate(r.created_at.slice(0, 10))}</td></tr>`).join('')
      : html`<tr><td colspan="4" class="empty-hint">Need a category that isn't listed? Request one.</td></tr>`;
  }

  function openBudgetModal(budget) {
    const form = $('#budgetForm');
    form.reset();
    LL.clearErrors(form);
    const expenseCats = state.categories.filter((c) => c.type === 'expense');
    const used = new Set(state.budgets.map((b) => b.category_id));
    $('#bCategory').innerHTML = expenseCats
      .filter((c) => !used.has(c.id) || c.id === budget?.category_id)
      .map((c) => html`<option value="${c.id}">${c.name}</option>`).join('');
    $('#bCategory').disabled = !!budget;
    form.elements.id.value = budget?.id || '';
    if (budget) {
      $('#bCategory').value = budget.category_id;
      form.elements.amount_limit.value = budget.amount_limit;
    }
    $('#budgetModalTitle').textContent = budget ? 'Edit budget' : 'New budget';
    $('#budgetDelete').hidden = !budget;
    $('#bMonthLabel').textContent = LL.monthLabel(state.month);
    modal('budgetModal').show();
  }

  // ---------- Stats ----------
  async function loadStats() {
    const [monthly, cats] = await Promise.all([
      api('/stats/monthly', { query: { months: state.stats.months } }),
      api('/stats/categories'),
    ]);
    const v = state.stats.view;
    const values = monthly.data.map((m) => m[v] * LL.currency.rate);
    const titles = { expense: 'Spending by month', income: 'Income by month', net: 'Net (income − spending) by month' };
    $('#barTitle').textContent = titles[v];
    const colors = values.map((n) => (v === 'net' ? (n >= 0 ? '#7ed957' : '#f59366') : v === 'income' ? '#7ed957' : '#fdd87d'));
    const data = { labels: monthly.data.map((m) => m.label), datasets: [{ label: titles[v], data: values, backgroundColor: colors, borderRadius: 4 }] };
    if (state.charts.bar) {
      state.charts.bar.data = data;
      state.charts.bar.update();
    } else {
      state.charts.bar = new Chart($('#barChart'), {
        type: 'bar',
        data,
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + money(c.raw / LL.currency.rate) } } },
          scales: { x: { grid: { display: false }, ticks: { font: { weight: 700 } } }, y: { ticks: { callback: (n) => money(n / LL.currency.rate).replace('.00', '') } } },
        },
      });
    }

    $('#topCategories').innerHTML = cats.data.length
      ? cats.data.slice(0, 6).map((c) => html`<div class="budget-row">
          <div class="d-flex justify-content-between"><span class="name">${c.name}</span><span class="nums">${c.pct}% · ${money(c.total)}</span></div>
          <div class="progress-ll"><div style="width:${c.pct}%"></div></div></div>`).join('')
      : html`<p class="empty-hint">No spending this month yet.</p>`;

    // Auto-generated insights
    const cur = monthly.data[monthly.data.length - 1];
    const prev = monthly.data[monthly.data.length - 2];
    const lines = [];
    if (prev && prev.expense > 0) {
      const d = Math.round(((cur.expense - prev.expense) / prev.expense) * 100);
      const prevName = new Date(prev.month + '-01T00:00:00').toLocaleDateString('en-US', { month: 'long' });
      lines.push(d === 0 ? `You've spent about the same as in ${prevName}.` : `You've spent ${Math.abs(d)}% ${d > 0 ? 'more' : 'less'} than in ${prevName}.`);
    }
    if (cats.data[0]) lines.push(`${cats.data[0].name} is your biggest expense this month (${cats.data[0].pct}% of spending).`);
    if (cur.income > 0) {
      const rate = Math.round((cur.net / cur.income) * 100);
      lines.push(rate >= 0 ? `You're keeping ${rate}% of this month's income.` : `You've spent ${money(-cur.net)} more than you earned this month.`);
    }
    const avg = monthly.data.reduce((s, m) => s + m.expense, 0) / monthly.data.length;
    lines.push(`Average monthly spending over the last ${state.stats.months} months: ${money(avg)}.`);
    $('#insights').innerHTML = lines.map((l) => html`<li>${l}</li>`).join('');
  }

  // ---------- Profile ----------
  async function loadProfile() {
    const [me, notes, tickets] = await Promise.all([api('/profile'), api('/notifications'), api('/tickets')]);
    state.me = me.data;
    const f = $('#profileForm');
    f.elements.first_name.value = me.data.first_name;
    f.elements.last_name.value = me.data.last_name;
    f.elements.email.value = me.data.email;
    $('#prefAlerts').checked = me.data.budget_alerts;
    $('#prefBills').checked = me.data.bill_reminders;
    renderNotifications(notes);

    $('#ticketList').innerHTML = tickets.data.length
      ? tickets.data.map((t) => html`<div class="notif-item">
          <div class="d-flex justify-content-between gap-2"><strong class="text-salmon">${t.subject}</strong>${LL.statusPill(t.status)}</div>
          <div class="small text-muted-ll">${fmtDateTime(t.created_at)}</div>
          ${t.staff_reply ? raw(html`<div class="small mt-1 p-2 bg-light rounded"><strong>Support:</strong> ${t.staff_reply}</div>`) : ''}
        </div>`).join('')
      : html`<p class="empty-hint mb-0">No tickets. Need help? Contact support.</p>`;
  }

  function renderNotifications(res) {
    const badge = document.querySelector('[data-badge="profile"]');
    badge.textContent = res.unread;
    badge.classList.toggle('d-none', !res.unread);
    const list = $('#notifList');
    if (!list) return;
    list.innerHTML = res.data.length
      ? res.data.map((n) => html`<div class="notif-item d-flex justify-content-between gap-2 ${n.is_read ? '' : 'unread'}">
          <div><div>${n.message}</div><div class="small text-muted-ll fw-normal">${fmtDateTime(n.created_at)}</div></div>
          ${n.is_read ? '' : raw(html`<button class="btn-icon" data-read="${n.id}" aria-label="Mark as read"><i class="bi bi-check2-circle"></i></button>`)}
        </div>`).join('')
      : html`<p class="empty-hint mb-0">You're all caught up.</p>`;
  }

  async function refreshBadge() {
    try { renderNotifications(await api('/notifications')); } catch { /* non-critical */ }
  }

  // ---------- View loading ----------
  const loaders = { home: loadHome, accounts: loadAccounts, transactions: loadTransactions, budgets: loadBudgets, stats: loadStats, profile: loadProfile };

  function handleLoadError(err) {
    toast(err.message || 'Could not load data.', 'error');
  }

  function loadView(view) {
    state.view = view;
    return loaders[view]().catch(handleLoadError);
  }

  async function afterDataChange() {
    await loadRefs();
    await loadView(state.view);
  }

  // ---------- Event wiring ----------
  function wire() {
    LL.bindLogout();
    $('#headerActions').innerHTML = '<button class="btn btn-ll d-none d-sm-inline-block" data-action="add-transaction"><i class="bi bi-plus-lg"></i> Add transaction</button>'
      + '<button class="btn btn-ll d-sm-none" data-action="add-transaction" aria-label="Add transaction"><i class="bi bi-plus-lg"></i></button>';

    document.addEventListener('click', async (e) => {
      const t = e.target.closest('[data-action],[data-edit-tx],[data-del-tx],[data-edit-account],[data-edit-budget],[data-read]');
      if (!t) return;
      if (t.dataset.action === 'add-transaction') openTxModal();
      else if (t.dataset.action === 'add-account') openAccountModal();
      else if (t.dataset.action === 'add-budget') {
        if (!state.budgets || state.view !== 'budgets') await loadBudgets();
        openBudgetModal();
      } else if (t.dataset.action === 'set-total') {
        $('#totalAmount').value = state.budgetSummary?.monthly_budget ?? '';
        modal('totalModal').show();
      } else if (t.dataset.action === 'request-category') { $('#requestForm').reset(); LL.clearErrors($('#requestForm')); modal('requestModal').show(); }
      else if (t.dataset.action === 'new-ticket') { $('#ticketForm').reset(); LL.clearErrors($('#ticketForm')); modal('ticketModal').show(); }
      else if (t.dataset.editTx) openTxModal(state.tx.rows.get(Number(t.dataset.editTx)));
      else if (t.dataset.editAccount) openAccountModal(state.accounts.find((a) => a.id === Number(t.dataset.editAccount)));
      else if (t.dataset.editBudget) openBudgetModal(state.budgets.find((b) => b.id === Number(t.dataset.editBudget)));
      else if (t.dataset.delTx) {
        const tx = state.tx.rows.get(Number(t.dataset.delTx));
        if (!(await LL.confirm(`Delete "${tx.description}" (${money(tx.amount)})? Balances and budgets will update.`, { okText: 'Delete' }))) return;
        try { await api(`/transactions/${tx.id}`, { method: 'DELETE' }); toast('Transaction deleted.'); loadTransactions(); } catch (err) { handleLoadError(err); }
      } else if (t.dataset.read) {
        try { await api(`/notifications/${t.dataset.read}/read`, { method: 'PUT' }); refreshBadge(); } catch (err) { handleLoadError(err); }
      }
    });

    // Home
    $('#prevMonth').addEventListener('click', () => { state.month = LL.shiftMonth(state.month, -1); loadHome().catch(handleLoadError); });
    $('#nextMonth').addEventListener('click', () => { state.month = LL.shiftMonth(state.month, 1); loadHome().catch(handleLoadError); });
    $('#currencyBtn').addEventListener('click', toggleCurrency);

    // Transaction form
    $('#txForm').querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', () => fillTxCategories(r.value)));
    $('#txForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      const fields = {};
      if (!d.description) fields.description = 'Please input a description.';
      if (!(Number(d.amount) > 0)) fields.amount = 'Valid positive amount required.';
      if (!d.transaction_date) fields.transaction_date = 'Date is required.';
      if (Object.keys(fields).length) return showErrors(form, { fields });
      const id = d.id; delete d.id;
      d.category_id = d.category_id || null;
      submitting(form, async () => {
        await api(id ? `/transactions/${id}` : '/transactions', { method: id ? 'PUT' : 'POST', body: d });
        modal('txModal').hide();
        toast(id ? 'Transaction updated.' : 'Transaction added.');
        await afterDataChange();
        refreshBadge();
      });
    });

    // Account form
    $('#accType').addEventListener('change', syncAccountType);
    $('#accountForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      const fields = {};
      if (!d.name) fields.name = 'Account name required.';
      if (d.opening_balance !== '' && Number(d.opening_balance) < 0) fields.opening_balance = d.type === 'credit_card' ? 'Enter the amount owed as a positive number.' : 'Starting balance cannot be negative.';
      if (Object.keys(fields).length) return showErrors(form, { fields });
      const id = d.id; delete d.id;
      if (d.type !== 'credit_card') d.due_date = null;
      submitting(form, async () => {
        await api(id ? `/accounts/${id}` : '/accounts', { method: id ? 'PUT' : 'POST', body: d });
        modal('accountModal').hide();
        toast(id ? 'Account updated.' : 'Account added.');
        await afterDataChange();
      });
    });
    $('#accDelete').addEventListener('click', async () => {
      const id = $('#accountForm').elements.id.value;
      const acc = state.accounts.find((a) => a.id === Number(id));
      modal('accountModal').hide();
      if (!(await LL.confirm(`Delete "${acc.name}" and its ${acc.transaction_count} transaction(s)? This cannot be undone.`, { okText: 'Delete account' }))) return;
      try { await api(`/accounts/${id}`, { method: 'DELETE' }); toast('Account deleted.'); await afterDataChange(); } catch (err) { handleLoadError(err); }
    });

    // Transactions search / filter / sort / export
    $('#txSearchForm').addEventListener('submit', (e) => { e.preventDefault(); reloadTx(); });
    $('#txSearch').addEventListener('input', LL.debounce(reloadTx, 350));
    $('#txFilterForm').addEventListener('change', reloadTx);
    $('#txFilterForm').addEventListener('reset', () => setTimeout(reloadTx));
    document.querySelectorAll('th[data-sort]').forEach((th) => th.addEventListener('click', () => {
      if (state.tx.sort === th.dataset.sort) state.tx.dir = state.tx.dir === 'asc' ? 'desc' : 'asc';
      else { state.tx.sort = th.dataset.sort; state.tx.dir = th.dataset.sort === 'date' || th.dataset.sort === 'amount' ? 'desc' : 'asc'; }
      reloadTx();
    }));
    $('#exportCsv').addEventListener('click', () => {
      const q = new URLSearchParams(Object.entries(txQuery()).filter(([, v]) => v !== '' && v != null));
      window.location.href = '/api/transactions/export?' + q.toString();
    });
    $('#exportAll').addEventListener('click', () => { window.location.href = '/api/transactions/export'; });

    // Budgets
    $('#budgetForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      if (!(Number(d.amount_limit) > 0)) return showErrors(form, { fields: { amount_limit: 'Valid positive amount required.' } });
      const id = d.id;
      submitting(form, async () => {
        if (id) await api(`/budgets/${id}`, { method: 'PUT', body: { amount_limit: d.amount_limit } });
        else await api('/budgets', { method: 'POST', body: { category_id: $('#bCategory').value, amount_limit: d.amount_limit, month: state.month } });
        modal('budgetModal').hide();
        toast(id ? 'Budget updated.' : 'Budget created.');
        loadView(state.view);
      });
    });
    $('#budgetDelete').addEventListener('click', async () => {
      const id = $('#budgetForm').elements.id.value;
      modal('budgetModal').hide();
      if (!(await LL.confirm('Delete this budget? Your transactions are not affected.', { okText: 'Delete budget' }))) return;
      try { await api(`/budgets/${id}`, { method: 'DELETE' }); toast('Budget deleted.'); loadView(state.view); } catch (err) { handleLoadError(err); }
    });
    $('#totalForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const v = form.elements.monthly_budget.value.trim();
      if (v !== '' && !(Number(v) > 0)) return showErrors(form, { fields: { monthly_budget: 'Valid positive amount required.' } });
      submitting(form, async () => {
        await api('/budgets/total', { method: 'PUT', body: { monthly_budget: v === '' ? null : v } });
        modal('totalModal').hide();
        toast('Monthly budget saved.');
        loadView(state.view);
      });
    });
    $('#requestForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      if (d.requested_name.length < 2) return showErrors(form, { fields: { requested_name: 'Category name is required.' } });
      submitting(form, async () => {
        await api('/category-requests', { method: 'POST', body: d });
        modal('requestModal').hide();
        toast('Request sent to our staff.');
        if (state.view === 'budgets') loadBudgets();
      });
    });

    // Stats toggles
    $('#statsView').addEventListener('click', (e) => {
      const b = e.target.closest('[data-v]'); if (!b) return;
      state.stats.view = b.dataset.v;
      $('#statsView').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
      loadStats().catch(handleLoadError);
    });
    $('#statsRange').addEventListener('click', (e) => {
      const b = e.target.closest('[data-m]'); if (!b) return;
      state.stats.months = Number(b.dataset.m);
      $('#statsRange').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
      loadStats().catch(handleLoadError);
    });

    // Profile
    $('#profileForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      if (!d.first_name) return showErrors(form, { fields: { first_name: 'First name is required.' } });
      submitting(form, async () => {
        const res = await api('/profile', { method: 'PUT', body: d });
        state.me = res.data;
        $('#sidebarName').textContent = `${res.data.first_name} ${res.data.last_name}`.trim();
        $('#welcomeName').textContent = res.data.first_name;
        toast('Profile saved.');
      });
    });
    document.querySelectorAll('[data-pref]').forEach((sw) => sw.addEventListener('change', async () => {
      try {
        const me = state.me;
        await api('/profile', { method: 'PUT', body: { first_name: me.first_name, last_name: me.last_name, email: me.email, [sw.dataset.pref]: sw.checked } });
        me[sw.dataset.pref] = sw.checked;
        toast('Preference saved.');
      } catch (err) { sw.checked = !sw.checked; handleLoadError(err); }
    }));
    $('#passwordForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const body = { current_password: form.elements.current_password.value, new_password: form.elements.new_password.value, confirm_password: form.elements.confirm_password.value };
      if (body.new_password !== body.confirm_password) return showErrors(form, { fields: { confirm_password: 'Passwords do not match.' } });
      submitting(form, async () => {
        await api('/profile/password', { method: 'PUT', body });
        modal('passwordModal').hide();
        form.reset();
        toast('Password updated.');
      });
    });
    $('#deleteForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      submitting(form, async () => {
        await api('/profile', { method: 'DELETE', body: { password: form.elements.password.value } });
        window.location.href = '/';
      });
    });
    $('#ticketForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.target;
      const d = formData(form);
      const fields = {};
      if (d.subject.length < 3) fields.subject = 'Subject must be at least 3 characters.';
      if (d.message.length < 10) fields.message = 'Message must be at least 10 characters.';
      if (Object.keys(fields).length) return showErrors(form, { fields });
      submitting(form, async () => {
        await api('/tickets', { method: 'POST', body: d });
        modal('ticketModal').hide();
        toast('Ticket sent. Our support team will reply soon.');
        if (state.view === 'profile') loadProfile();
      });
    });
    $('#markAllRead').addEventListener('click', async () => {
      try { await api('/notifications/read-all', { method: 'PUT' }); refreshBadge(); } catch (err) { handleLoadError(err); }
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    wire();
    try { await loadRefs(); } catch (err) { handleLoadError(err); }
    refreshBadge();
    LL.router(Object.keys(loaders), loadView);
  });
})();
