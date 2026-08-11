(function () {
  'use strict';

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function updateProviderLinks(root, query) {
    root.querySelectorAll('[data-sc-course-provider-link]').forEach(function (link) {
      var template = String(link.dataset.searchTemplate || link.getAttribute('href') || '');
      if (!template) { return; }
      link.setAttribute('href', template.indexOf('{query}') !== -1 ? template.replace('{query}', encodeURIComponent(query)) : template);
    });
  }

  function filterMatches(card, data) {
    var query = normalize(data.get('query'));
    var subject = normalize(data.get('subject'));
    var access = normalize(data.get('access'));
    var level = normalize(data.get('level'));
    var duration = normalize(data.get('duration'));
    var pathway = normalize(data.get('pathway'));
    var learning = normalize(data.get('learning'));

    var blob = normalize(card.dataset.courseSearch);
    var subjects = normalize(card.dataset.courseSubjects);
    var cardAccess = normalize(card.dataset.courseAccess);
    var cardLevel = normalize(card.dataset.courseLevel);
    var cardDuration = normalize(card.dataset.courseDuration);
    var cardPathways = normalize(card.dataset.coursePathways);
    var cardLearning = normalize(card.dataset.courseLearning);

    var queryMatch = !query || blob.indexOf(query) !== -1;
    var subjectMatch = !subject || subjects.split('|').indexOf(subject) !== -1;
    var accessMatch = !access || cardAccess === access || (access === 'mixed' && cardAccess.indexOf('mixed') !== -1);
    var levelMatch = !level || cardLevel === level;
    var pathwayMatch = !pathway || cardPathways.split('|').indexOf(pathway) !== -1;
    var durationMatch = !duration || cardDuration === duration || (duration === '15h-plus' && cardDuration === '15h-plus');
    var learningMatch = !learning || (learning === 'saved' ? !!cardLearning : cardLearning === learning);
    return queryMatch && subjectMatch && accessMatch && levelMatch && pathwayMatch && durationMatch && learningMatch;
  }

  document.querySelectorAll('[data-sc-course-finder]').forEach(function (root) {
    var form = root.querySelector('[data-sc-course-finder-form]');
    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-sc-course-card]'));
    var status = root.querySelector('[data-sc-course-status]');
    var empty = root.querySelector('[data-sc-course-empty]');
    if (!form) { return; }

    function applyFilters() {
      var data = new FormData(form);
      var count = 0;
      cards.forEach(function (card) {
        var visible = filterMatches(card, data);
        card.hidden = !visible;
        if (visible) { count += 1; }
      });
      updateProviderLinks(root, normalize(data.get('query')));
      if (empty) { empty.hidden = count !== 0; }
      if (status) {
        status.textContent = count + ' launch-catalog course' + (count === 1 ? '' : 's') + ' match' + (count === 1 ? 'es' : '') + '. Pathway and learning-plan filters stay local; provider gateways below search beyond the launch catalog.';
      }
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      applyFilters();
    });
    form.addEventListener('change', applyFilters);
    var queryInput = form.querySelector('input[name="query"]');
    if (queryInput) {
      queryInput.addEventListener('input', function () {
        if (queryInput.value.length === 0 || queryInput.value.length >= 2) { applyFilters(); }
      });
    }

    root.addEventListener('click', function (event) {
      var ask = event.target.closest('[data-sc-course-ask-librarian]');
      if (!ask) { return; }
      event.preventDefault();
      var title = ask.dataset.courseTitle || '';
      var institution = ask.dataset.courseInstitution || '';
      var pathwayTitles = ask.dataset.coursePathwayTitles || '';
      var prompt = 'Help me evaluate the course “' + title + '” from ' + institution + ' and connect it to the Sustainable Catalyst knowledge I should study before, during, or after the course.';
      if (pathwayTitles) { prompt += ' Relevant Knowledge Pathways include ' + pathwayTitles + '.'; }
      document.dispatchEvent(new CustomEvent('sc-library-librarian-request', {
        detail: { prompt: prompt, recordIds: [], target: '#research-front-door', source: 'open-course-finder' }
      }));
      var target = document.querySelector('#research-front-door');
      if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      if (status) { status.textContent = 'Course context added to the Research Librarian.'; }
    });

    document.addEventListener('sc-library-course-plan-updated', applyFilters);
    applyFilters();
  });
})();
