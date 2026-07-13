(() => {
  'use strict';
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-button]');
    if (!button) return;
    const container = button.closest('.sc-developer-secret');
    const value = container?.querySelector('[data-copy-value]')?.textContent?.trim() || '';
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      const previous = button.textContent;
      button.textContent = window.SCLibraryDeveloperAdmin?.copied || 'Copied';
      setTimeout(() => { button.textContent = previous; }, 1800);
    } catch (error) {
      window.prompt('Copy this value:', value);
    }
  });
})();
