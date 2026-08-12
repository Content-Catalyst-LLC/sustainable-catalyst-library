(function () {
    'use strict';

    var RUNTIME = 'v4.3.22.3';

    function parseData(root) {
        var node = root.querySelector('.sc-publications__data');
        if (!node) return null;
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function pad(value) { return String(value).padStart(2, '0'); }

    function markRuntime(root, state, detail) {
        root.setAttribute('data-sc-publications-runtime-state', state);
        if (detail) root.setAttribute('data-sc-publications-runtime-detail', detail);
        else root.removeAttribute('data-sc-publications-runtime-detail');
        if (state.indexOf('fallback') === 0 || state === 'error') root.classList.remove('is-enhanced');
    }

    function reportFailure(root, code, error) {
        markRuntime(root, 'fallback', code);
        try {
            root.dispatchEvent(new CustomEvent('sc:publications:runtimefailure', {
                bubbles: true,
                detail: { code: code, message: error && error.message ? String(error.message) : '' }
            }));
        } catch (ignored) {}
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('[Sustainable Catalyst Publications] fail-open navigation:', code, error || '');
        }
    }

    function isPlainPrimaryClick(event) {
        return !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    function buildFallbackUrl(root, fieldKey, mapKey) {
        try {
            var url = new URL(window.location.href);
            if (fieldKey) url.searchParams.set('sc_publications_field', fieldKey);
            else url.searchParams.delete('sc_publications_field');
            if (mapKey) url.searchParams.set('sc_publications_map', mapKey);
            else url.searchParams.delete('sc_publications_map');
            url.hash = root.id ? '#' + root.id : '';
            return url.href;
        } catch (e) { return ''; }
    }

    function init(root) {
        markRuntime(root, 'initializing');
        var data = parseData(root);
        if (!data || !Array.isArray(data.fields) || !data.fields.length) {
            reportFailure(root, 'invalid-payload');
            return;
        }

        var fieldTabs = Array.prototype.slice.call(root.querySelectorAll('[data-field-index][data-field-key]'));
        var viewport = root.querySelector('.sc-publications__viewport');
        var rail = root.querySelector('.sc-publications__area-rail');
        var select = root.querySelector('.sc-publications__area-select');
        var activeFieldNumber = root.querySelector('.sc-publications__active-field-number');
        var fieldPosition = root.querySelector('.sc-publications__field-position');
        var fieldTitle = root.querySelector('.sc-publications__field-heading h3');
        var fieldDescription = root.querySelector('.sc-publications__field-description');
        var areaCount = root.querySelector('.sc-publications__area-count strong');
        var areaLabel = root.querySelector('.sc-publications__area-count span');
        var stage = root.querySelector('.sc-publications__stage');
        var stageEyebrow = root.querySelector('.sc-publications__stage-eyebrow');
        var mapPosition = root.querySelector('.sc-publications__map-position');
        var mapHero = root.querySelector('.sc-publications__map-hero');
        var mapLabel = root.querySelector('.sc-publications__map-label');
        var mapTitle = root.querySelector('.sc-publications__map-copy strong');
        var mapDescription = root.querySelector('.sc-publications__map-description');
        var mapAction = root.querySelector('.sc-publications__map-action');
        var articleRows = Array.prototype.slice.call(root.querySelectorAll('.sc-publications__articles li'));
        var stageIndexTitle = root.querySelector('.sc-publications__stage-index strong');
        var stageIndexPosition = root.querySelector('.sc-publications__stage-index span');
        var previousLabels = Array.prototype.slice.call(root.querySelectorAll('.sc-publications__previous-label'));
        var nextLabels = Array.prototype.slice.call(root.querySelectorAll('.sc-publications__next-label'));

        var required = [viewport, rail, select, activeFieldNumber, fieldPosition, fieldTitle, fieldDescription, areaCount, areaLabel, stage, stageEyebrow, mapPosition, mapHero, mapLabel, mapTitle, mapDescription, mapAction, stageIndexTitle, stageIndexPosition];
        if (required.some(function (node) { return !node; }) || fieldTabs.length !== data.fields.length) {
            reportFailure(root, 'incomplete-dom');
            return;
        }

        var fieldIndexByKey = function (key) {
            return data.fields.findIndex(function (field) { return String(field.key || '') === String(key || ''); });
        };
        var requestedFieldKey = String(root.dataset.initialFieldKey || '');
        var requestedMapKey = String(root.dataset.initialMapKey || '');
        var requestedFieldIndex = fieldIndexByKey(requestedFieldKey);
        var activeField = requestedFieldIndex >= 0 ? requestedFieldIndex : 0;
        var requestedTopicIndex = data.fields[activeField] && Array.isArray(data.fields[activeField].topics)
            ? data.fields[activeField].topics.findIndex(function (topic) { return topic.key === requestedMapKey; })
            : -1;
        var activeTopic = requestedTopicIndex >= 0 ? requestedTopicIndex : Number(data.fields[activeField].defaultIndex || 0);
        var touchStartX = null;

        function findHashState() {
            var match = window.location.hash.match(/^#publications-([a-z0-9-]+)--([a-z0-9-]+)$/);
            if (!match) return;
            data.fields.forEach(function (field, fieldIndex) {
                if (field.key !== match[1]) return;
                field.topics.forEach(function (topic, topicIndex) {
                    if (topic.key === match[2]) { activeField = fieldIndex; activeTopic = topicIndex; }
                });
            });
        }

        function writeHash(field, topic) {
            if (!window.history || !window.history.replaceState) return;
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('sc_publications_field');
                url.searchParams.delete('sc_publications_map');
                url.hash = '#publications-' + field.key + '--' + topic.key;
                window.history.replaceState(null, '', url.pathname + url.search + url.hash);
            } catch (e) {}
        }

        function verifyTopic(field, topic) {
            return !!field && !!topic && stage.dataset.fieldKey === String(field.key || '') && stage.dataset.mapKey === String(topic.key || '') && stageIndexTitle.textContent === String(topic.title || '');
        }

        function verifyField(field) {
            if (!field || root.dataset.activeField !== String(field.key || '') || stage.dataset.fieldKey !== String(field.key || '') || fieldTitle.textContent !== String(field.title || '')) return false;
            var activeTab = fieldTabs.find(function (tab) { return tab.getAttribute('aria-selected') === 'true'; });
            return !!activeTab && String(activeTab.dataset.fieldKey || '') === String(field.key || '');
        }

        function rebuildRail(field) {
            rail.innerHTML = '';
            select.innerHTML = '';
            field.topics.forEach(function (topic, index) {
                var link = document.createElement('a');
                link.href = buildFallbackUrl(root, field.key, topic.key) || '#';
                link.setAttribute('role', 'tab');
                link.dataset.areaIndex = String(index);
                link.dataset.mapKey = String(topic.key || '');
                link.setAttribute('aria-selected', index === activeTopic ? 'true' : 'false');
                if (index === activeTopic) link.classList.add('is-active');
                link.textContent = topic.title;
                // v4.3.22.3: direct Article Map links are intentionally not intercepted.
                // The href is the authority; JavaScript may enhance playback controls only.
                rail.appendChild(link);

                var option = document.createElement('option');
                option.value = String(index);
                option.dataset.mapKey = String(topic.key || '');
                option.textContent = topic.title;
                option.selected = index === activeTopic;
                select.appendChild(option);
            });
        }

        function renderArticles(topic) {
            articleRows.forEach(function (row, index) {
                var article = topic.articles[index];
                if (!article) { row.hidden = true; return; }
                row.hidden = false;
                var titleLink = row.querySelector('h4 a');
                var action = row.querySelector('.sc-publications__row-action');
                if (!titleLink || !action) return;
                titleLink.textContent = article.title;
                titleLink.href = article.url;
                action.href = article.url;
                action.setAttribute('aria-label', 'Read ' + article.title);
            });
        }

        function renderTopic(animate, updateHash) {
            var field = data.fields[activeField];
            if (!field || !Array.isArray(field.topics) || !field.topics.length) return false;
            activeTopic = Math.max(0, Math.min(activeTopic, field.topics.length - 1));
            var topic = field.topics[activeTopic];

            Array.prototype.forEach.call(rail.querySelectorAll('[data-area-index]'), function (link) {
                var selected = Number(link.dataset.areaIndex) === activeTopic;
                link.classList.toggle('is-active', selected);
                link.setAttribute('aria-selected', selected ? 'true' : 'false');
                if (selected && typeof link.scrollIntoView === 'function') link.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'smooth'});
            });
            select.value = String(activeTopic);

            stageEyebrow.textContent = topic.group ? field.title + ' / ' + topic.group : field.title;
            mapPosition.textContent = pad(activeTopic + 1) + ' / ' + pad(field.topics.length);
            mapHero.href = topic.mapUrl;
            mapLabel.textContent = data.labels.map;
            mapTitle.textContent = topic.mapTitle;
            mapDescription.textContent = topic.description || data.labels.heroDescription;
            var actionText = Array.prototype.find.call(mapAction.childNodes, function (node) { return node.nodeType === 3; });
            if (actionText) actionText.nodeValue = (topic.mapCta || data.labels.mapCta) + ' ';
            stage.dataset.articleSource = topic.articleSource || 'unresolved';
            stage.dataset.fieldKey = String(field.key || '');
            stage.dataset.mapKey = String(topic.key || '');
            root.dataset.activeMap = String(topic.key || '');
            stageIndexTitle.textContent = topic.title;
            stageIndexPosition.textContent = pad(activeTopic + 1) + ' / ' + pad(field.topics.length);
            renderArticles(topic);

            if (animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                stage.classList.remove('is-refreshing');
                void stage.offsetWidth;
                stage.classList.add('is-refreshing');
            }
            if (updateHash) writeHash(field, topic);
            return verifyTopic(field, topic);
        }

        function renderField(animate, updateHash) {
            var field = data.fields[activeField];
            if (!field || !Array.isArray(field.topics) || !field.topics.length) return false;
            activeTopic = Math.max(0, Math.min(activeTopic, field.topics.length - 1));
            fieldTabs.forEach(function (link) {
                var selected = String(link.dataset.fieldKey || '') === String(field.key || '');
                link.classList.toggle('is-active', selected);
                link.setAttribute('aria-selected', selected ? 'true' : 'false');
                link.tabIndex = selected ? 0 : -1;
            });
            activeFieldNumber.textContent = pad(activeField + 1);
            fieldPosition.textContent = pad(activeField + 1) + ' / ' + pad(data.fields.length);
            fieldTitle.textContent = field.title;
            fieldDescription.textContent = field.description || '';
            fieldDescription.hidden = !field.description;
            areaCount.textContent = String(field.topics.length);
            areaLabel.textContent = String(data.labels.areas || 'Areas').toUpperCase();
            rail.setAttribute('aria-label', 'Areas in ' + field.title);
            root.dataset.activeField = String(field.key || '');
            rebuildRail(field);
            if (!renderTopic(animate, updateHash)) return false;
            return verifyField(field);
        }

        function setField(index, focusStage) {
            if (!data.fields[index]) return false;
            activeField = index;
            activeTopic = Number(data.fields[index].defaultIndex || 0);
            if (!renderField(true, true)) return false;
            if (focusStage && viewport && typeof viewport.focus === 'function') viewport.focus({preventScroll: true});
            return verifyField(data.fields[index]);
        }

        function setTopic(index, updateHash) {
            var field = data.fields[activeField];
            if (!field || !field.topics[index]) return false;
            activeTopic = index;
            return renderTopic(true, updateHash);
        }

        function step(delta) {
            var field = data.fields[activeField];
            if (!field || !field.topics.length) return false;
            var next = (activeTopic + delta + field.topics.length) % field.topics.length;
            return setTopic(next, true);
        }

        // Server query parameters are the source of truth; legacy hash state no longer changes fields.
        try {
            if (!renderField(false, false)) {
                reportFailure(root, 'initial-render-verification-failed');
                return;
            }
        } catch (error) {
            reportFailure(root, 'initial-render-exception', error);
            return;
        }

        root.classList.add('is-enhanced');
        markRuntime(root, 'ready');

        // v4.3.22.3: major-field tabs are server-authoritative anchors.
        // Do not attach a click handler and do not call preventDefault() here.
        // Native navigation carries sc_publications_field to PHP, which renders the selected field.

        root.querySelectorAll('[data-area-previous]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    if (!step(-1)) {
                        var field = data.fields[activeField];
                        var topic = field && field.topics[(activeTopic - 1 + field.topics.length) % field.topics.length];
                        var href = topic ? buildFallbackUrl(root, field.key, topic.key) : '';
                        if (href) window.location.assign(href);
                    }
                } catch (error) { reportFailure(root, 'previous-switch-exception', error); }
            });
        });
        root.querySelectorAll('[data-area-next]').forEach(function (button) {
            button.addEventListener('click', function () {
                try {
                    if (!step(1)) {
                        var field = data.fields[activeField];
                        var topic = field && field.topics[(activeTopic + 1) % field.topics.length];
                        var href = topic ? buildFallbackUrl(root, field.key, topic.key) : '';
                        if (href) window.location.assign(href);
                    }
                } catch (error) { reportFailure(root, 'next-switch-exception', error); }
            });
        });
        select.addEventListener('change', function () {
            var index = Number(select.value);
            var field = data.fields[activeField];
            var topic = field && field.topics[index];
            var href = topic ? buildFallbackUrl(root, field.key, topic.key) : '';
            if (href) window.location.assign(href);
        });

        viewport.addEventListener('keydown', function (event) {
            var tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
            if (['input', 'textarea', 'select'].indexOf(tag) !== -1) return;
            if (event.key === 'ArrowLeft') { if (step(-1)) event.preventDefault(); }
            if (event.key === 'ArrowRight') { if (step(1)) event.preventDefault(); }
        });

        viewport.addEventListener('touchstart', function (event) {
            if (event.touches && event.touches.length === 1) touchStartX = event.touches[0].clientX;
        }, {passive: true});
        viewport.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches || !event.changedTouches.length) return;
            var delta = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(delta) < 55) return;
            step(delta > 0 ? -1 : 1);
        }, {passive: true});

        previousLabels.forEach(function (node) { node.textContent = data.labels.previous; });
        nextLabels.forEach(function (node) { node.textContent = data.labels.next; });
    }

    function boot() {
        document.querySelectorAll('[data-sc-publications="' + RUNTIME + '"]').forEach(function (root) {
            try { init(root); } catch (error) { reportFailure(root, 'boot-exception', error); }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
