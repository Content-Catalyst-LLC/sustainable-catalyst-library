(() => {
    'use strict';

    const announce = (message) => {
        let live = document.getElementById('sc-library-system-live');
        if (!live) {
            live = document.createElement('div');
            live.id = 'sc-library-system-live';
            live.className = 'screen-reader-text';
            live.setAttribute('aria-live', 'polite');
            document.body.appendChild(live);
        }
        live.textContent = '';
        window.setTimeout(() => { live.textContent = message; }, 30);
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-system-manifest]');
        if (!button) return;
        const pre = document.querySelector('[data-system-manifest]');
        if (!pre) return;
        try {
            await navigator.clipboard.writeText(pre.textContent || '');
            announce(window.SCLibraryUnifiedSystem?.strings?.copied || 'System manifest copied.');
            button.textContent = window.SCLibraryUnifiedSystem?.strings?.copied || 'Copied';
        } catch (error) {
            announce(window.SCLibraryUnifiedSystem?.strings?.copyFailed || 'The system manifest could not be copied.');
        }
    });

    document.querySelectorAll('[data-sc-library-living-system] a[href^="#"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
        });
    });
})();
