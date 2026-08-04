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
        const position = root.querySelector('[data-sc-spotlight-position]');
        const toggle = root.querySelector('[data-sc-spotlight-toggle]');
        const toggleIcon = root.querySelector('[data-sc-spotlight-toggle-icon]');
        const toggleText = root.querySelector('[data-sc-spotlight-toggle-text]');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const interval = Math.max(8000, Number.parseInt(root.dataset.interval || '16000', 10) || 16000);
        const loop = root.dataset.loop !== 'false';
        const pauseOnHover = root.dataset.pauseOnHover !== 'false';
        let current = Math.min(Number.parseInt(root.dataset.current || '0', 10) || 0, Math.max(0, pages.length - 1));
        let userPaused = root.dataset.autoplay !== 'true';
        let interactionPaused = false;
        let timer = null;
        let touchStartX = null;

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
                if (!cards.length) {
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
        };

        const updateToggle = () => {
            if (!toggle) {
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
        };

        const clearTimer = () => {
            window.clearTimeout(timer);
            timer = null;
        };

        const schedule = () => {
            clearTimer();
            if (pages.length < 2 || userPaused || interactionPaused || prefersReducedMotion.matches || document.hidden) {
                return;
            }
            timer = window.setTimeout(() => show(current + 1, false), interval);
        };

        const normalizedIndex = (requested) => {
            if (!pages.length) {
                return 0;
            }
            if (loop) {
                return (requested + pages.length) % pages.length;
            }
            return Math.max(0, Math.min(pages.length - 1, requested));
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
            });
            tabs.forEach((tab, index) => {
                const active = index === current;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
            });
            root.dataset.current = String(current);
            if (position) {
                position.textContent = `${current + 1} / ${pages.length}`;
            }
            if (focusHeading) {
                pages[current].querySelector('[data-sc-spotlight-page-heading]')?.focus({ preventScroll: true });
            }
            schedule();
        };

        removeDismissedCards();
        refreshPages();
        if (!pages.length) {
            return;
        }

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

        root.querySelector('[data-sc-spotlight-prev]')?.addEventListener('click', () => show(current - 1, false));
        root.querySelector('[data-sc-spotlight-next]')?.addEventListener('click', () => show(current + 1, false));
        toggle?.addEventListener('click', () => {
            if (prefersReducedMotion.matches) {
                return;
            }
            userPaused = !userPaused;
            updateToggle();
            schedule();
        });

        if (pauseOnHover) {
            root.addEventListener('mouseenter', () => {
                interactionPaused = true;
                clearTimer();
            });
            root.addEventListener('mouseleave', () => {
                interactionPaused = false;
                schedule();
            });
        }
        root.addEventListener('focusin', () => {
            interactionPaused = true;
            clearTimer();
        });
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) {
                interactionPaused = false;
                schedule();
            }
        });
        document.addEventListener('visibilitychange', schedule);

        root.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0]?.clientX ?? null;
        }, { passive: true });
        root.addEventListener('touchend', (event) => {
            if (touchStartX === null) {
                return;
            }
            const endX = event.changedTouches[0]?.clientX ?? touchStartX;
            const delta = endX - touchStartX;
            touchStartX = null;
            if (Math.abs(delta) < 50) {
                return;
            }
            show(delta > 0 ? current - 1 : current + 1, false);
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
