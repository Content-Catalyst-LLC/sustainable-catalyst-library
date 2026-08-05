(() => {
    'use strict';

    const safeStorageGet = (key) => {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    };

    const safeStorageSet = (key, value) => {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Storage may be unavailable in restricted or private sessions.
        }
    };

    const initialize = (root) => {
        let pages = Array.from(root.querySelectorAll('[data-sc-spotlight-page]'));
        let tabs = Array.from(root.querySelectorAll('[data-sc-spotlight-tab]'));
        const positions = Array.from(root.querySelectorAll('[data-sc-spotlight-position]'));
        const status = root.querySelector('[data-sc-spotlight-status]');
        const progress = root.querySelector('[data-sc-spotlight-progress]');
        const toggle = root.querySelector('[data-sc-spotlight-toggle]');
        const toggleIcon = root.querySelector('[data-sc-spotlight-toggle-icon]');
        const toggleText = root.querySelector('[data-sc-spotlight-toggle-text]');
        const tierToggle = root.querySelector('[data-sc-spotlight-tier-toggle]');
        const tierToggleLabel = root.querySelector('[data-sc-spotlight-tier-toggle-label]');
        const tierIcon = root.querySelector('[data-sc-spotlight-tier-icon]');
        const secondaryPanel = root.querySelector('[data-sc-spotlight-secondary-panel]');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const interval = Math.max(8000, Number.parseInt(root.dataset.interval || '14000', 10) || 14000);
        const loop = root.dataset.loop !== 'false';
        const pauseOnHover = root.dataset.pauseOnHover !== 'false';
        let current = Math.min(Number.parseInt(root.dataset.current || '0', 10) || 0, Math.max(0, pages.length - 1));
        let userPaused = root.dataset.autoplay !== 'true';
        let interactionPaused = false;
        let secondaryExpanded = Boolean(secondaryPanel) && root.dataset.secondaryOpen === 'true';
        let timer = null;
        let touchStartX = null;

        const label = (name, fallback) => root.dataset[name] || fallback;

        const installThumbnailFallbacks = () => {
            root.querySelectorAll('.sc-homepage-spotlight__thumbnail-image').forEach((image) => {
                const showPlaceholder = () => {
                    const frame = image.closest('.sc-homepage-spotlight__thumbnail');
                    if (!frame) {
                        return;
                    }
                    frame.classList.add('sc-homepage-spotlight__thumbnail--placeholder');
                    frame.dataset.thumbnailSource = 'runtime-fallback';
                    image.remove();
                    if (!frame.querySelector('.sc-homepage-spotlight__thumbnail-placeholder-mark')) {
                        const mark = document.createElement('span');
                        mark.className = 'sc-homepage-spotlight__thumbnail-placeholder-mark';
                        mark.textContent = 'KL';
                        frame.append(mark);
                    }
                };
                image.addEventListener('error', showPlaceholder, { once: true });
                if (image.complete && image.naturalWidth === 0) {
                    showPlaceholder();
                }
            });
        };

        const removeDismissedCards = () => {
            root.querySelectorAll('[data-sc-spotlight-card][data-dismiss-key]').forEach((card) => {
                if (safeStorageGet(card.dataset.dismissKey) === 'dismissed') {
                    card.remove();
                }
            });
        };

        const refreshPages = () => {
            pages = Array.from(root.querySelectorAll('[data-sc-spotlight-page]'));
            pages.forEach((page) => {
                const cards = page.querySelectorAll('[data-sc-spotlight-card]');
                if (cards.length < 4) {
                    const pageIndex = page.dataset.pageIndex;
                    root.querySelector(`[data-sc-spotlight-tab="${pageIndex}"]`)?.remove();
                    page.remove();
                }
            });
            pages = Array.from(root.querySelectorAll('[data-sc-spotlight-page]'));
            tabs = Array.from(root.querySelectorAll('[data-sc-spotlight-tab]'));
            pages.forEach((page, index) => {
                page.dataset.pageIndex = String(index);
            });
            tabs.forEach((tab, index) => {
                tab.dataset.scSpotlightTab = String(index);
            });
            if (!pages.length) {
                root.hidden = true;
            }
            current = Math.min(current, Math.max(0, pages.length - 1));
            if (secondaryPanel && !secondaryPanel.querySelector('[data-sc-spotlight-tab]')) {
                const tierContainer = tierToggle?.closest('[data-sc-spotlight-secondary-tier]');
                if (tierContainer) {
                    tierContainer.remove();
                } else {
                    tierToggle?.remove();
                    secondaryPanel.remove();
                }
                secondaryExpanded = false;
            }
        };

        const pageTier = (page) => page?.dataset.scSpotlightTier || 'primary';

        const navigableIndexes = () => {
            const allIndexes = pages.map((page, index) => ({ page, index }));
            if (!secondaryPanel || secondaryExpanded) {
                return allIndexes.map(({ index }) => index);
            }
            const primary = allIndexes
                .filter(({ page }) => pageTier(page) !== 'secondary')
                .map(({ index }) => index);
            return primary.length ? primary : allIndexes.map(({ index }) => index);
        };

        const adjacentIndex = (direction) => {
            const indexes = navigableIndexes();
            if (!indexes.length) {
                return 0;
            }
            let position = indexes.indexOf(current);
            if (position < 0) {
                position = 0;
            }
            const requested = position + direction;
            if (loop) {
                return indexes[(requested + indexes.length) % indexes.length];
            }
            return indexes[Math.max(0, Math.min(indexes.length - 1, requested))];
        };

        const playbackState = () => {
            if (pages.length < 2) {
                return 'static';
            }
            if (prefersReducedMotion.matches) {
                return 'reduced';
            }
            if (userPaused) {
                return 'paused';
            }
            if (interactionPaused || document.hidden) {
                return 'hold';
            }
            return 'auto';
        };

        const updatePlaybackState = () => {
            const state = playbackState();
            root.dataset.playbackState = state;
            if (status) {
                const labels = {
                    auto: label('statusAuto', 'Auto'),
                    paused: label('statusPaused', 'Paused'),
                    hold: label('statusHold', 'Hold'),
                    static: label('statusStatic', 'Static'),
                    reduced: label('statusReduced', 'Reduced motion'),
                };
                status.textContent = labels[state] || labels.paused;
            }
            return state;
        };

        const updateToggle = () => {
            if (!toggle) {
                updatePlaybackState();
                return;
            }
            const paused = userPaused || prefersReducedMotion.matches;
            toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
            toggle.setAttribute('aria-label', paused ? root.dataset.labelPlay : root.dataset.labelPause);
            if (toggleIcon) {
                toggleIcon.textContent = paused ? '▶' : 'Ⅱ';
            }
            if (toggleText) {
                toggleText.textContent = paused ? 'Play' : 'Pause';
            }
            toggle.disabled = prefersReducedMotion.matches;
            if (prefersReducedMotion.matches) {
                toggle.title = 'Automatic rotation is disabled by your reduced-motion preference.';
            } else {
                toggle.removeAttribute('title');
            }
            updatePlaybackState();
        };

        const updateSecondaryTier = () => {
            if (!secondaryPanel || !tierToggle) {
                return;
            }
            secondaryPanel.hidden = !secondaryExpanded;
            tierToggle.setAttribute('aria-expanded', secondaryExpanded ? 'true' : 'false');
            if (tierToggleLabel) {
                tierToggleLabel.textContent = secondaryExpanded
                    ? label('labelFewerTopics', 'Hide additional topics')
                    : label('labelMoreTopics', 'Explore additional topics');
            }
            if (tierIcon) {
                tierIcon.textContent = secondaryExpanded ? '−' : '+';
            }
            root.dataset.secondaryExpanded = secondaryExpanded ? 'true' : 'false';
        };

        const clearTimer = () => {
            window.clearTimeout(timer);
            timer = null;
        };

        const restartProgress = () => {
            if (!progress) {
                return;
            }
            progress.classList.remove('is-running');
            void progress.offsetWidth;
            if (playbackState() === 'auto') {
                progress.classList.add('is-running');
            }
        };

        const schedule = () => {
            clearTimer();
            const state = updatePlaybackState();
            restartProgress();
            if (state !== 'auto') {
                return;
            }
            timer = window.setTimeout(() => show(adjacentIndex(1), false), interval);
        };

        const normalizedIndex = (requested) => {
            if (!pages.length) {
                return 0;
            }
            return Math.max(0, Math.min(pages.length - 1, requested));
        };

        const updatePositions = () => {
            const value = `${String(current + 1).padStart(2, '0')} / ${String(pages.length).padStart(2, '0')}`;
            positions.forEach((position) => {
                position.textContent = value;
            });
        };

        const show = (requested, focusHeading = false) => {
            if (!pages.length) {
                root.hidden = true;
                return;
            }
            current = normalizedIndex(requested);
            pages.forEach((page, index) => {
                const active = index === current;
                page.hidden = !active;
                page.classList.toggle('is-active', active);
                page.classList.remove('is-refreshing');
                if (active && !prefersReducedMotion.matches) {
                    void page.offsetWidth;
                    page.classList.add('is-refreshing');
                }
            });
            tabs.forEach((tab, index) => {
                const active = index === current;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
            });
            root.dataset.current = String(current);
            updatePositions();
            if (focusHeading) {
                pages[current].querySelector('[data-sc-spotlight-page-heading]')?.focus({ preventScroll: true });
            }
            schedule();
        };

        installThumbnailFallbacks();
        removeDismissedCards();
        refreshPages();
        if (!pages.length) {
            return;
        }
        updateSecondaryTier();

        root.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-sc-spotlight-tab]');
            if (tab) {
                show(Number.parseInt(tab.dataset.scSpotlightTab || '0', 10) || 0, false);
                return;
            }
            const dismiss = event.target.closest('[data-sc-spotlight-dismiss]');
            if (dismiss) {
                const card = dismiss.closest('[data-sc-spotlight-card]');
                if (!card) {
                    return;
                }
                if (card.dataset.dismissKey) {
                    safeStorageSet(card.dataset.dismissKey, 'dismissed');
                }
                card.remove();
                refreshPages();
                show(current, true);
            }
        });

        root.addEventListener('keydown', (event) => {
            const tab = event.target.closest('[data-sc-spotlight-tab]');
            if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            const tablist = tab.closest('[role="tablist"]');
            const navigableTabs = tablist
                ? Array.from(tablist.querySelectorAll('[data-sc-spotlight-tab]'))
                : tabs;
            const tabPosition = Math.max(0, navigableTabs.indexOf(tab));
            let requestedPosition = tabPosition;
            if (event.key === 'ArrowLeft') requestedPosition = tabPosition - 1;
            if (event.key === 'ArrowRight') requestedPosition = tabPosition + 1;
            if (event.key === 'Home') requestedPosition = 0;
            if (event.key === 'End') requestedPosition = navigableTabs.length - 1;
            requestedPosition = (requestedPosition + navigableTabs.length) % navigableTabs.length;
            const requestedTab = navigableTabs[requestedPosition];
            show(Number.parseInt(requestedTab?.dataset.scSpotlightTab || '0', 10) || 0, false);
            requestedTab?.focus({ preventScroll: true });
        });

        root.querySelector('[data-sc-spotlight-prev]')?.addEventListener('click', () => show(adjacentIndex(-1), false));
        root.querySelector('[data-sc-spotlight-next]')?.addEventListener('click', () => show(adjacentIndex(1), false));
        toggle?.addEventListener('click', () => {
            if (prefersReducedMotion.matches) {
                return;
            }
            userPaused = !userPaused;
            updateToggle();
            schedule();
        });
        tierToggle?.addEventListener('click', () => {
            secondaryExpanded = !secondaryExpanded;
            updateSecondaryTier();
            if (!secondaryExpanded && pageTier(pages[current]) === 'secondary') {
                const firstPrimary = pages.findIndex((page) => pageTier(page) !== 'secondary');
                show(Math.max(0, firstPrimary), false);
            } else {
                schedule();
            }
        });

        if (pauseOnHover) {
            root.addEventListener('mouseenter', () => {
                interactionPaused = true;
                clearTimer();
                updatePlaybackState();
            });
            root.addEventListener('mouseleave', () => {
                interactionPaused = false;
                schedule();
            });
        }
        root.addEventListener('focusin', () => {
            interactionPaused = true;
            clearTimer();
            updatePlaybackState();
        });
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) {
                interactionPaused = false;
                schedule();
            }
        });
        document.addEventListener('visibilitychange', schedule);

        root.addEventListener('touchstart', (event) => {
            interactionPaused = true;
            clearTimer();
            updatePlaybackState();
            touchStartX = event.changedTouches[0]?.clientX ?? null;
        }, { passive: true });
        root.addEventListener('touchend', (event) => {
            if (touchStartX === null) {
                interactionPaused = false;
                schedule();
                return;
            }
            const endX = event.changedTouches[0]?.clientX ?? touchStartX;
            const delta = endX - touchStartX;
            touchStartX = null;
            interactionPaused = false;
            if (Math.abs(delta) >= 50) {
                show(delta > 0 ? adjacentIndex(-1) : adjacentIndex(1), false);
                return;
            }
            schedule();
        }, { passive: true });

        prefersReducedMotion.addEventListener?.('change', () => {
            updateToggle();
            schedule();
        });

        updateToggle();
        show(current, false);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-sc-homepage-spotlight]').forEach(initialize);
    });
})();
