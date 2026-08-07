(function () {
    'use strict';

    function parseData(root) {
        var node = root.querySelector('.sc-publications__data');
        if (!node) return null;
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function pad(value) { return String(value).padStart(2, '0'); }

    function init(root) {
        var data = parseData(root);
        if (!data || !Array.isArray(data.fields) || !data.fields.length) return;

        var fieldTabs = Array.prototype.slice.call(root.querySelectorAll('[data-field-index]'));
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
        var activeField = 0;
        var activeTopic = Number(data.fields[0].defaultIndex || 0);
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
            var base = window.location.pathname + window.location.search;
            window.history.replaceState(null, '', base + '#publications-' + field.key + '--' + topic.key);
        }

        function rebuildRail(field) {
            rail.innerHTML = '';
            select.innerHTML = '';
            field.topics.forEach(function (topic, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'tab');
                button.dataset.areaIndex = String(index);
                button.setAttribute('aria-selected', index === activeTopic ? 'true' : 'false');
                if (index === activeTopic) button.classList.add('is-active');
                button.textContent = topic.title;
                button.addEventListener('click', function () { setTopic(index, true); });
                rail.appendChild(button);

                var option = document.createElement('option');
                option.value = String(index);
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
                titleLink.textContent = article.title;
                titleLink.href = article.url;
                action.href = article.url;
                action.setAttribute('aria-label', 'Read ' + article.title);
            });
        }

        function renderTopic(animate, updateHash) {
            var field = data.fields[activeField];
            if (!field || !field.topics.length) return;
            activeTopic = Math.max(0, Math.min(activeTopic, field.topics.length - 1));
            var topic = field.topics[activeTopic];

            Array.prototype.forEach.call(rail.querySelectorAll('[data-area-index]'), function (button) {
                var selected = Number(button.dataset.areaIndex) === activeTopic;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
                if (selected) button.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'smooth'});
            });
            select.value = String(activeTopic);

            stageEyebrow.textContent = topic.group ? field.title + ' / ' + topic.group : field.title;
            mapPosition.textContent = pad(activeTopic + 1) + ' / ' + pad(field.topics.length);
            mapHero.href = topic.mapUrl;
            mapLabel.textContent = data.labels.map;
            mapTitle.textContent = topic.mapTitle;
            mapDescription.textContent = topic.description || data.labels.heroDescription;
            mapAction.firstChild.nodeValue = (topic.mapCta || data.labels.mapCta) + ' ';
            stage.dataset.articleSource = topic.articleSource || 'unresolved';
            stageIndexTitle.textContent = topic.title;
            stageIndexPosition.textContent = pad(activeTopic + 1) + ' / ' + pad(field.topics.length);
            renderArticles(topic);

            if (animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                stage.classList.remove('is-refreshing');
                void stage.offsetWidth;
                stage.classList.add('is-refreshing');
            }
            if (updateHash) writeHash(field, topic);
        }

        function renderField(animate, updateHash) {
            var field = data.fields[activeField];
            if (!field) return;
            activeTopic = Math.max(0, Math.min(activeTopic, field.topics.length - 1));
            fieldTabs.forEach(function (button, index) {
                var selected = index === activeField;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            activeFieldNumber.textContent = pad(activeField + 1);
            fieldPosition.textContent = pad(activeField + 1) + ' / ' + pad(data.fields.length);
            fieldTitle.textContent = field.title;
            fieldDescription.textContent = field.description || '';
            fieldDescription.hidden = !field.description;
            areaCount.textContent = String(field.topics.length);
            areaLabel.textContent = String(data.labels.areas || 'Areas').toUpperCase();
            rail.setAttribute('aria-label', 'Areas in ' + field.title);
            rebuildRail(field);
            renderTopic(animate, updateHash);
        }

        function setField(index, focusStage) {
            if (!data.fields[index]) return;
            activeField = index;
            activeTopic = Number(data.fields[index].defaultIndex || 0);
            renderField(true, true);
            if (focusStage && viewport) viewport.focus({preventScroll: true});
        }

        function setTopic(index, updateHash) {
            var field = data.fields[activeField];
            if (!field || !field.topics[index]) return;
            activeTopic = index;
            renderTopic(true, updateHash);
        }

        function step(delta) {
            var field = data.fields[activeField];
            if (!field || !field.topics.length) return;
            activeTopic = (activeTopic + delta + field.topics.length) % field.topics.length;
            renderTopic(true, true);
        }

        findHashState();
        renderField(false, false);

        fieldTabs.forEach(function (button, index) {
            button.addEventListener('click', function () { setField(index, false); });
        });
        root.querySelectorAll('[data-area-previous]').forEach(function (button) { button.addEventListener('click', function () { step(-1); }); });
        root.querySelectorAll('[data-area-next]').forEach(function (button) { button.addEventListener('click', function () { step(1); }); });
        select.addEventListener('change', function () { setTopic(Number(select.value), true); });

        viewport.addEventListener('keydown', function (event) {
            var tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
            if (['input', 'textarea', 'select'].indexOf(tag) !== -1) return;
            if (event.key === 'ArrowLeft') { event.preventDefault(); step(-1); }
            if (event.key === 'ArrowRight') { event.preventDefault(); step(1); }
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
        document.querySelectorAll('[data-sc-publications="v4.3.3"]').forEach(init);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
