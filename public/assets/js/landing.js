/* Landing page: login and sign-up modals. */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const loginModalEl = document.getElementById('loginModal');
  const signupModalEl = document.getElementById('signupModal');

  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => new bootstrap.Popover(el));

  // Switch between login and sign-up modals.
  document.querySelectorAll('[data-switch]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const from = btn.closest('.modal');
      bootstrap.Modal.getInstance(from)?.hide();
      from.addEventListener('hidden.bs.modal', () => bootstrap.Modal.getOrCreateInstance(document.querySelector(btn.dataset.switch)).show(), { once: true });
    });
  });

  // Open login automatically after being redirected from a protected page.
  if (new URLSearchParams(location.search).has('login') || location.hash === '#login') {
    bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
  }
  loginModalEl.addEventListener('shown.bs.modal', () => loginModalEl.querySelector('input').focus());
  signupModalEl.addEventListener('shown.bs.modal', () => signupModalEl.querySelector('input').focus());

  const emailOk = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

  document.getElementById('loginForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const data = LL.formData(form);
    const fields = {};
    if (!emailOk(data.email)) fields.email = 'Please enter a valid email address.';
    if (!form.elements.password.value) fields.password = 'Password is required.';
    if (Object.keys(fields).length) return LL.showErrors(form, { fields });
    data.password = form.elements.password.value; // never trim passwords

    LL.submitting(form, async () => {
      const res = await LL.api('/auth/login', { method: 'POST', body: data });
      window.location.href = res.redirect;
    });
  });

  document.getElementById('signupForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const data = LL.formData(form);
    data.password = form.elements.password.value;
    data.confirm_password = form.elements.confirm_password.value;
    const fields = {};
    if (!data.first_name) fields.first_name = 'First name is required.';
    if (!emailOk(data.email)) fields.email = 'Please enter a valid email address.';
    if (data.password.length < 8 || !/[A-Za-z]/.test(data.password) || !/\d/.test(data.password)) {
      fields.password = 'At least 8 characters, with a letter and a number.';
    }
    if (data.password !== data.confirm_password) fields.confirm_password = 'Passwords do not match.';
    if (Object.keys(fields).length) return LL.showErrors(form, { fields });

    LL.submitting(form, async () => {
      const res = await LL.api('/auth/register', { method: 'POST', body: data });
      window.location.href = res.redirect;
    });
  });
});
