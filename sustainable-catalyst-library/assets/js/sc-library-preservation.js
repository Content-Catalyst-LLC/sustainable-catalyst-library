(() => {
  'use strict';
  document.querySelectorAll('[data-sc-library-preservation-confirm]').forEach((button) => {
    button.addEventListener('click', (event) => {
      const message = button.getAttribute('data-sc-library-preservation-confirm') || 'Continue?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
  document.querySelectorAll('.sc-library-preservation-table-wrap code').forEach((code) => {
    code.title = 'Click to copy checksum';
    code.tabIndex = 0;
    const copy = async () => {
      try { await navigator.clipboard.writeText(code.textContent.replace('…', '')); code.dataset.copied = '1'; }
      catch (error) { /* Clipboard may be unavailable in older admin browsers. */ }
    };
    code.addEventListener('click', copy);
    code.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') copy(); });
  });
})();
