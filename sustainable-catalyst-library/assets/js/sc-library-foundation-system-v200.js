(() => {
  'use strict';

  const announce = (message) => {
    let region = document.getElementById('sc-fnd-live-region');
    if (!region) {
      region = document.createElement('div');
      region.id = 'sc-fnd-live-region';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'true');
      region.style.position = 'absolute';
      region.style.width = '1px';
      region.style.height = '1px';
      region.style.overflow = 'hidden';
      region.style.clip = 'rect(0 0 0 0)';
      document.body.appendChild(region);
    }
    region.textContent = '';
    window.setTimeout(() => { region.textContent = message; }, 20);
  };

  document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-sc-fnd-copy-citation]');
    if (copyButton) {
      const citation = copyButton.getAttribute('data-citation') || '';
      try {
        await navigator.clipboard.writeText(citation);
        const original = copyButton.textContent;
        copyButton.textContent = 'Citation copied';
        announce('Citation copied to clipboard.');
        window.setTimeout(() => { copyButton.textContent = original; }, 1800);
      } catch (error) {
        window.prompt('Copy this citation:', citation);
      }
      return;
    }

    if (event.target.closest('[data-sc-fnd-print]')) {
      window.print();
      return;
    }

    const tocToggle = event.target.closest('.sc-fnd-toc-toggle');
    if (tocToggle) {
      const toc = tocToggle.closest('.sc-fnd-toc');
      const expanded = tocToggle.getAttribute('aria-expanded') !== 'false';
      tocToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      toc.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    }
  });
})();
