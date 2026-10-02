/* Guided tour: spotlights one element at a time with a step card (Back / Next / Skip). No dependencies. */
'use strict';

const LLTour = (() => {
  const { html, raw } = LL;
  let active = null;

  const visible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

  /**
   * steps: [{ target?: selector, title, text }]. A step whose target is missing or hidden
   * is shown as a centered card. onEnd(completed: boolean) runs once when the tour closes.
   */
  function start(steps, { onEnd } = {}) {
    if (active) active.close(false);

    const backdrop = document.createElement('div');
    backdrop.className = 'tour-backdrop';
    const spot = document.createElement('div');
    spot.className = 'tour-spotlight';
    const card = document.createElement('div');
    card.className = 'tour-card';
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-labelledby', 'tourTitle');
    card.setAttribute('aria-describedby', 'tourText');
    document.body.append(backdrop, spot, card);
    document.body.classList.add('tour-open'); // extra scroll room so targets near the page end can be centered

    const previousFocus = document.activeElement;
    let index = 0;
    let target = null;

    function place() {
      const pad = 8;
      const margin = 12;
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const cw = card.offsetWidth;
      const ch = card.offsetHeight;

      if (!target) {
        spot.hidden = true;
        backdrop.classList.add('dim');
        card.style.left = `${Math.max(margin, (vw - cw) / 2)}px`;
        card.style.top = `${Math.max(margin, (vh - ch) / 2)}px`;
        return;
      }
      backdrop.classList.remove('dim');
      spot.hidden = false;
      const r = target.getBoundingClientRect();
      Object.assign(spot.style, {
        left: `${r.left - pad}px`, top: `${r.top - pad}px`, width: `${r.width + pad * 2}px`, height: `${r.height + pad * 2}px`,
      });

      // Prefer below the target, then above; otherwise overlap the bottom of the viewport.
      let top = r.bottom + pad + margin;
      if (top + ch > vh - margin) top = r.top - pad - margin - ch;
      if (top < margin) top = vh - ch - margin;
      const left = Math.min(Math.max(margin, r.left + r.width / 2 - cw / 2), vw - cw - margin);
      card.style.left = `${left}px`;
      card.style.top = `${Math.max(margin, top)}px`;
    }

    function render() {
      const step = steps[index];
      const el = step.target ? document.querySelector(step.target) : null;
      target = visible(el) ? el : null;
      const last = index === steps.length - 1;
      card.innerHTML = html`
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
          <h2 class="tour-title" id="tourTitle">${step.title}</h2>
          <button type="button" class="btn-close" data-tour="skip" aria-label="Close tour"></button>
        </div>
        <p class="tour-text" id="tourText">${step.text}</p>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <span class="tour-count">${index + 1} of ${steps.length}</span>
          <div class="d-flex gap-2">
            ${index > 0 ? raw('<button type="button" class="btn btn-ll-outline btn-sm" data-tour="back">Back</button>') : raw('<button type="button" class="btn btn-link btn-sm text-muted-ll fw-bold" data-tour="skip">Skip tour</button>')}
            <button type="button" class="btn btn-ll btn-sm" data-tour="next">${last ? 'Finish' : 'Next'}</button>
          </div>
        </div>`;
      if (target) target.scrollIntoView({ block: 'center', inline: 'nearest' });
      // Wait a frame so scrolling and the new card size settle before positioning.
      requestAnimationFrame(() => { place(); card.querySelector('[data-tour="next"]').focus(); });
    }

    function go(delta) {
      const next = index + delta;
      if (next >= steps.length) return close(true);
      if (next < 0) return;
      index = next;
      render();
    }

    function onClick(e) {
      const action = e.target.closest('[data-tour]')?.dataset.tour;
      if (action === 'next') go(1);
      else if (action === 'back') go(-1);
      else if (action === 'skip') close(false);
    }

    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); close(false); }
      else if (e.key === 'ArrowRight') go(1);
      else if (e.key === 'ArrowLeft') go(-1);
      else if (e.key === 'Tab') {
        // Keep keyboard focus inside the step card while the tour is open.
        const items = [...card.querySelectorAll('button')];
        const i = items.indexOf(document.activeElement);
        e.preventDefault();
        items[(i + (e.shiftKey ? -1 : 1) + items.length) % items.length].focus();
      }
    }

    const onMove = () => requestAnimationFrame(place);

    function close(completed) {
      card.removeEventListener('click', onClick);
      document.removeEventListener('keydown', onKey, true);
      window.removeEventListener('resize', onMove);
      window.removeEventListener('scroll', onMove, true);
      backdrop.remove(); spot.remove(); card.remove();
      document.body.classList.remove('tour-open');
      active = null;
      previousFocus?.focus?.();
      if (onEnd) onEnd(completed);
    }

    card.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey, true);
    window.addEventListener('resize', onMove);
    window.addEventListener('scroll', onMove, true);
    active = { close };
    render();
  }

  return { start, isActive: () => !!active };
})();
