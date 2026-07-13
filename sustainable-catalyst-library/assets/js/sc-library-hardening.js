(function () {
  'use strict';

  const config = window.SCLibraryHardening || {};
  const labels = config.labels || {};
  const touchTarget = Number(config.touchTarget || 44);
  document.documentElement.style.setProperty('--sc-library-touch', `${Math.max(40, Math.min(56, touchTarget))}px`);

  const roots = Array.from(document.querySelectorAll('.sc-library, .sc-library-readiness-public'));
  if (!roots.length) return;

  let live = document.querySelector('.sc-library-a11y-live');
  if (!live) {
    live = document.createElement('div');
    live.className = 'sc-library-a11y-live';
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('aria-atomic', 'true');
    document.body.appendChild(live);
  }

  roots.forEach((root, index) => {
    if (!root.id) root.id = `sc-library-content-${index + 1}`;
    if (!root.hasAttribute('tabindex')) root.setAttribute('tabindex', '-1');

    if (!document.querySelector(`a.sc-library-skip-link[href="#${root.id}"]`)) {
      const skip = document.createElement('a');
      skip.className = 'sc-library-skip-link';
      skip.href = `#${root.id}`;
      skip.textContent = labels.skip || 'Skip to Library content';
      document.body.insertBefore(skip, document.body.firstChild);
      skip.addEventListener('click', () => window.setTimeout(() => root.focus({ preventScroll: true }), 0));
    }

    root.querySelectorAll('button:not([type])').forEach((button) => button.setAttribute('type', 'button'));
    root.querySelectorAll('table').forEach((table) => {
      if (table.closest('.sc-library-table-scroll')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'sc-library-table-scroll';
      wrapper.tabIndex = 0;
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || labels.table || 'Scrollable data table');
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });

    root.querySelectorAll('canvas:not([role])').forEach((canvas) => {
      canvas.setAttribute('role', 'img');
      if (!canvas.getAttribute('aria-label')) canvas.setAttribute('aria-label', 'Document or research visualization');
    });

    root.querySelectorAll('[data-sc-disclosure]').forEach((control) => {
      if (!control.hasAttribute('aria-expanded')) control.setAttribute('aria-expanded', 'false');
      control.addEventListener('click', () => {
        const expanded = control.getAttribute('aria-expanded') === 'true';
        control.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      });
    });
  });

  const announce = (message) => {
    if (!message) return;
    live.textContent = '';
    window.setTimeout(() => { live.textContent = message; }, 30);
  };

  document.addEventListener('sc-library:updated', (event) => announce(event.detail && event.detail.message ? event.detail.message : labels.updated || 'Library content updated.'));

  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type !== 'attributes') continue;
      const target = mutation.target;
      if (target.matches && target.matches('[data-sc-status][aria-live]')) announce(target.textContent.trim());
    }
  });
  roots.forEach((root) => observer.observe(root, { subtree: true, attributes: true, attributeFilter: ['data-state', 'aria-busy'] }));
})();
