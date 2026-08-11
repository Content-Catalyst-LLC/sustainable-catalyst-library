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

  document.querySelectorAll('[data-sc-course-finder]').forEach(function (root) {
    var form = root.querySelector('[data-sc-course-finder-form]');
    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-sc-course-card]'));
    var status = root.querySelector('[data-sc-course-status]');
    var empty = root.querySelector('[data-sc-course-empty]');
    if (!form) { return; }

    function applyFilters() {
      var data = new FormData(form);
      var query = normalize(data.get('query'));
      var subject = normalize(data.get('subject'));
      var access = normalize(data.get('access'));
      var count = 0;

      cards.forEach(function (card) {
        var blob = normalize(card.dataset.courseSearch);
        var subjects = normalize(card.dataset.courseSubjects);
        var cardAccess = normalize(card.dataset.courseAccess);
        var queryMatch = !query || blob.indexOf(query) !== -1;
        var subjectMatch = !subject || subjects.split('|').indexOf(subject) !== -1;
        var accessMatch = !access || cardAccess === access || (access === 'mixed' && cardAccess.indexOf('mixed') !== -1);
        var visible = queryMatch && subjectMatch && accessMatch;
        card.hidden = !visible;
        if (visible) { count += 1; }
      });

      updateProviderLinks(root, query);
      if (empty) { empty.hidden = count !== 0; }
      if (status) {
        status.textContent = count + ' launch-catalog course' + (count === 1 ? '' : 's') + ' match' + (count === 1 ? 'es' : '') + '. Provider gateways below search beyond the launch catalog.';
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
    applyFilters();
  });
})();
