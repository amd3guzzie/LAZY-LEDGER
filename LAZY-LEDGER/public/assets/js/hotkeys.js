// Shared keyboard shortcuts for customer, staff, and admin dashboards.
// api.js must load first because it provides the global LL helpers.
(function () {
  'use strict';

  document.addEventListener('keydown', async (e) => {
    if (e.key === 'Escape') {
      if (e.defaultPrevented) return;
      if (document.querySelector('.modal.show, .offcanvas.show')) return;
      const ok = await LL.confirm('Do you want to end your session and log out?', {
        title: 'End session?',
        okText: 'Log out',
      });
      if (!ok) return;
      try { await LL.api('/auth/logout', { method: 'POST' }); } catch { /* Ignore logout errors. */ }
      window.location.href = '/';
    }

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      const form = document.querySelector('.modal.show form')
        || document.querySelector('.view:not([hidden]) form:not([role="search"])');
      if (form) form.requestSubmit();
      else LL.toast('There is nothing to save on this screen.', 'info');
    }

    if (e.key === 'Enter' && e.target.matches('input[type="search"]')) {
      e.preventDefault();
      e.target.form?.requestSubmit();
    }
  });
})();