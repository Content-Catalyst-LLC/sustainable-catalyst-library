(() => {
    'use strict';

    const config = window.SCLibrarySpotlightAdmin || {};
    const sourceSearch = document.getElementById('sc-library-spotlight-source-search');
    const sourceId = document.getElementById('sc-library-spotlight-source-id');
    const results = document.getElementById('sc-library-spotlight-source-results');
    const sourceRadios = Array.from(document.querySelectorAll('input[name="source_type"]'));
    let timer = null;
    let controller = null;

    const selectedSourceType = () => sourceRadios.find((radio) => radio.checked)?.value || 'library';

    const syncSourceSections = () => {
        const isAnnouncement = selectedSourceType() === 'announcement';
        document.querySelectorAll('[data-source-section="library"]').forEach((element) => {
            element.hidden = isAnnouncement;
        });
        document.querySelectorAll('[data-announcement-only]').forEach((element) => {
            element.hidden = !isAnnouncement;
        });
    };

    sourceRadios.forEach((radio) => radio.addEventListener('change', syncSourceSections));
    syncSourceSections();

    const showMessage = (message) => {
        if (results) {
            results.textContent = message;
        }
    };

    const renderResults = (items) => {
        if (!results) {
            return;
        }
        results.replaceChildren();
        if (!items.length) {
            showMessage(config.noResults || 'No results.');
            return;
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sc-library-spotlight-source-result';
            const title = document.createElement('strong');
            title.textContent = item.title;
            const meta = document.createElement('small');
            meta.textContent = item.url ? `${item.type} · ${item.url}` : item.type;
            button.append(title, meta);
            button.addEventListener('click', () => {
                sourceId.value = String(item.id);
                sourceSearch.value = item.title;
                results.replaceChildren();
                const headline = document.getElementById('sc-library-spotlight-headline');
                const summary = document.getElementById('sc-library-spotlight-summary');
                if (headline && !headline.value.trim()) {
                    headline.value = item.title;
                }
                if (summary && !summary.value.trim()) {
                    summary.value = item.excerpt || '';
                }
                const showThumbnail = document.getElementById('sc-library-spotlight-show-thumbnail');
                if (showThumbnail) {
                    showThumbnail.checked = true;
                }
            });
            results.append(button);
        });
    };

    sourceSearch?.addEventListener('input', () => {
        window.clearTimeout(timer);
        const query = sourceSearch.value.trim();
        if (sourceId) {
            sourceId.value = '';
        }
        if (query.length < 2) {
            results?.replaceChildren();
            return;
        }
        timer = window.setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            showMessage(config.searching || 'Searching…');
            const url = new URL(config.ajaxUrl, window.location.origin);
            url.searchParams.set('action', 'sc_library_spotlight_search_sources');
            url.searchParams.set('nonce', config.nonce || '');
            url.searchParams.set('q', query);
            try {
                const response = await window.fetch(url.toString(), {
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const payload = await response.json();
                renderResults(payload?.data?.items || []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    showMessage(config.noResults || 'No results.');
                }
            }
        }, 250);
    });

    document.querySelectorAll('[data-spotlight-sortable]').forEach((tbody) => {
        let dragging = null;
        const step = Math.max(1, Number.parseInt(tbody.dataset.orderStep || '1', 10) || 1);

        tbody.addEventListener('dragstart', (event) => {
            dragging = event.target.closest('[data-spotlight-row]');
            if (!dragging) {
                return;
            }
            dragging.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });

        tbody.querySelectorAll('[data-spotlight-row]').forEach((row) => {
            row.draggable = true;
        });

        tbody.addEventListener('dragend', () => {
            dragging?.classList.remove('is-dragging');
            dragging = null;
            Array.from(tbody.querySelectorAll('[data-spotlight-row]')).forEach((row, index) => {
                const input = row.querySelector('input[type="number"]');
                if (input) {
                    input.value = String(step === 10 ? (index + 1) * 10 : index + 1);
                }
            });
        });

        tbody.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!dragging) {
                return;
            }
            const row = event.target.closest('[data-spotlight-row]');
            if (!row || row === dragging) {
                return;
            }
            const bounds = row.getBoundingClientRect();
            const after = event.clientY > bounds.top + (bounds.height / 2);
            tbody.insertBefore(dragging, after ? row.nextSibling : row);
        });
    });

    document.addEventListener('click', (event) => {
        const destructive = event.target.closest('[data-confirm]');
        if (destructive && !window.confirm(destructive.dataset.confirm)) {
            event.preventDefault();
        }
    });
})();
