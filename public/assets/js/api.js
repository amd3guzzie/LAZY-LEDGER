/* Shared helpers for every LazyLedger page: fetch wrapper, escaping, formatting, UI utilities. */
'use strict';

const LL = (() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  class ApiError extends Error {
    constructor(message, status, fields) {
      super(message);
      this.status = status;
      this.fields = fields || {};
    }
  }

  /** Call the REST API. Throws ApiError with server message + field errors on failure. */
  async function api(path, { method = 'GET', body, query } = {}) {
    let url = '/api' + path;
    if (query) {
      const params = new URLSearchParams();
      Object.entries(query).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') params.append(k, v);
      });
      const qs = params.toString();
      if (qs) url += '?' + qs;
    }
    const opts = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (method !== 'GET') opts.headers['X-CSRF-Token'] = csrf();
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }

    let res;
    try {
      res = await fetch(url, opts);
    } catch {
      throw new ApiError('Network error — check your connection and try again.', 0);
    }
    let data = null;
    try { data = await res.json(); } catch { /* empty or non-JSON body */ }

    if (res.status === 401 && !path.startsWith('/auth/')) {
      window.location.href = '/?login=1';
      throw new ApiError('Session expired.', 401);
    }
    if (!res.ok) {
      throw new ApiError(data?.error || `Request failed (${res.status}).`, res.status, data?.fields);
    }
    return data;
  }

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  /** Tagged template that escapes every interpolated value: html`<td>${name}</td>` */
  function html(strings, ...values) {
    return strings.reduce((out, s, i) => {
      if (i === 0) return s;
      const v = values[i - 1];
      const safe = v instanceof Raw ? v.value : Array.isArray(v) ? v.map((x) => (x instanceof Raw ? x.value : escapeHtml(x))).join('') : escapeHtml(v);
      return out + safe + s;
    }, '');
  }
  class Raw { constructor(value) { this.value = value; } }
  const raw = (value) => new Raw(value);

  // Currency: amounts are stored in PHP; the dashboard can display them in USD via the Frankfurter API.
  const currency = { code: 'PHP', rate: 1 };
  function money(amount, { sign = false } = {}) {
    const n = Number(amount || 0) * currency.rate;
    const abs = Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const symbol = currency.code === 'USD' ? '$' : '₱';
    const prefix = n < 0 ? '-' : sign ? '+' : '';
    return `${prefix}${symbol}${abs}`;
  }

  const fmtDate = (d) => {
    if (!d) return '—';
    const date = new Date(String(d).replace(' ', 'T') + (String(d).length === 10 ? 'T00:00:00' : ''));
    return isNaN(date) ? d : date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
  };
  const fmtDateTime = (d) => {
    if (!d) return 'Never';
    const date = new Date(String(d).replace(' ', 'T'));
    return isNaN(date) ? d : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  };
  const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };
  const monthKey = (d = new Date()) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  const monthLabel = (key) => {
    const [y, m] = key.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  };
  const shiftMonth = (key, delta) => {
    const [y, m] = key.split('-').map(Number);
    return monthKey(new Date(y, m - 1 + delta, 1));
  };

  function toast(message, type = 'success') {
    const wrap = document.getElementById('toasts');
    if (!wrap) return;
    const el = document.createElement('div');
    const bg = type === 'error' ? 'text-bg-danger' : type === 'info' ? 'text-bg-primary' : 'text-bg-success';
    el.className = `toast align-items-center border-0 ${bg}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = html`<div class="d-flex"><div class="toast-body fw-bold">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3500 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }

  /** Show server/client field errors on a form using Bootstrap's is-invalid styling. */
  function showErrors(form, err) {
    clearErrors(form);
    const fields = err?.fields || {};
    let shown = false;
    Object.entries(fields).forEach(([name, msg]) => {
      const input = form.elements[name];
      if (!input || !input.classList) return;
      input.classList.add('is-invalid');
      let fb = input.parentElement.querySelector('.invalid-feedback');
      if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        input.insertAdjacentElement('afterend', fb);
      }
      fb.textContent = msg;
      shown = true;
    });
    const box = form.querySelector('.form-error');
    if (box) {
      box.textContent = shown && Object.keys(fields).length ? '' : (err?.message || 'Something went wrong.');
      box.hidden = !box.textContent;
    } else if (!shown) {
      toast(err?.message || 'Something went wrong.', 'error');
    }
    form.querySelector('.is-invalid')?.focus();
  }

  function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach((el) => { el.textContent = ''; });
    const box = form.querySelector('.form-error');
    if (box) { box.textContent = ''; box.hidden = true; }
  }

  /** Read a form into an object, trimming strings. */
  function formData(form) {
    const out = {};
    Array.from(form.elements).forEach((el) => {
      if (!el.name || el.disabled) return;
      if (el.type === 'checkbox') out[el.name] = el.checked;
      else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; }
      else out[el.name] = typeof el.value === 'string' ? el.value.trim() : el.value;
    });
    return out;
  }

  /** Submit helper: disables the button, runs fn, shows errors. Returns fn's result or undefined. */
  async function submitting(form, fn) {
    const btn = form.querySelector('[type="submit"]');
    clearErrors(form);
    if (btn) btn.disabled = true;
    try {
      return await fn();
    } catch (err) {
      showErrors(form, err);
      return undefined;
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  function confirm(message, { okText = 'Confirm', title = 'Are you sure?' } = {}) {
    return new Promise((resolve) => {
      const el = document.getElementById('confirmModal');
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      el.querySelector('#confirmTitle').textContent = title;
      el.querySelector('#confirmBody').textContent = message;
      const ok = el.querySelector('#confirmOk');
      ok.textContent = okText;
      let result = false;
      const onOk = () => { result = true; modal.hide(); };
      ok.addEventListener('click', onOk, { once: true });
      el.addEventListener('hidden.bs.modal', () => { ok.removeEventListener('click', onOk); resolve(result); }, { once: true });
      modal.show();
    });
  }

  function debounce(fn, ms = 300) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  /** Render Bootstrap pagination into el from API meta; calls onPage(n). */
  function pagination(el, meta, onPage) {
    if (!el) return;
    if (!meta || meta.pages <= 1) {
      el.innerHTML = meta ? html`<small class="text-muted-ll">${meta.total} result${meta.total === 1 ? '' : 's'}</small>` : '';
      return;
    }
    const { page, pages, total } = meta;
    const nums = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) nums.push(i);
    el.innerHTML = html`<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted-ll">Page ${page} of ${pages} · ${total} results</small>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item ${page <= 1 ? 'disabled' : ''}"><button class="page-link" data-page="${page - 1}" aria-label="Previous">&laquo;</button></li>
        ${nums.map((n) => raw(html`<li class="page-item ${n === page ? 'active' : ''}"><button class="page-link" data-page="${n}">${n}</button></li>`))}
        <li class="page-item ${page >= pages ? 'disabled' : ''}"><button class="page-link" data-page="${page + 1}" aria-label="Next">&raquo;</button></li>
      </ul></div>`;
    el.querySelectorAll('[data-page]').forEach((b) => b.addEventListener('click', () => onPage(Number(b.dataset.page))));
  }

  function statusPill(status) {
    return raw(html`<span class="status-pill status-${status}">${String(status).replace('_', ' ')}</span>`);
  }

  function progressBar(pct) {
    const w = Math.min(100, Math.max(0, pct));
    const cls = pct > 100 ? 'over' : pct >= 90 ? 'warn' : '';
    return raw(html`<div class="progress-ll ${cls}" role="progressbar" aria-valuenow="${Math.round(pct)}" aria-valuemin="0" aria-valuemax="100"><div style="width:${w}%"></div></div>`);
  }

  /** Hash-based view switching for dashboard pages. */
  function router(views, onShow) {
    const show = () => {
      const name = location.hash.slice(1).split('?')[0];
      const target = views.includes(name) ? name : views[0];
      document.querySelectorAll('.view').forEach((v) => { v.hidden = v.dataset.view !== target; });
      document.querySelectorAll('[data-view-link]').forEach((a) => {
        a.classList.toggle('active', a.dataset.viewLink === target);
        if (a.dataset.viewLink === target) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
      });
      const oc = bootstrap.Offcanvas.getInstance(document.getElementById('sidebar'));
      if (oc) oc.hide();
      window.scrollTo(0, 0);
      onShow(target);
    };
    window.addEventListener('hashchange', show);
    show();
  }

  function bindLogout() {
    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
      try { await api('/auth/logout', { method: 'POST' }); } catch { /* ignore */ }
      window.location.href = '/';
    });
  }

  return {
    api, ApiError, escapeHtml, html, raw, money, currency, fmtDate, fmtDateTime, today, monthKey, monthLabel, shiftMonth,
    toast, showErrors, clearErrors, formData, submitting, confirm, debounce, pagination, statusPill, progressBar, router, bindLogout,
  };
})();
