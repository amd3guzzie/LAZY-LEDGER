// Visitor action tracking (revision #8).
// When someone clicks a button or link, tell the server what they clicked so the admin
// "Visitors" page can show it next to their IP address and the time.
(function () {
  'use strict';
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  document.addEventListener('click', (e) => {
    const el = e.target.closest('a, button');
    if (!el) return;
    // Use the button's visible text (or its aria-label for icon-only buttons), max 80 characters.
    const label = (el.getAttribute('aria-label') || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80);
    if (!label) return;
    // keepalive lets the request finish even if the click opens another page.
    fetch('/api/track', {
      method: 'POST',
      keepalive: true,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify({ action: `Clicked "${label}"`, page: (location.pathname + location.hash).slice(0, 190) }),
    }).catch(() => { /* Tracking must never break the page. */ });
  }, true);
})();
