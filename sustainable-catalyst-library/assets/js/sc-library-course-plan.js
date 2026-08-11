(function () {
  'use strict';
  const cfg = window.scLibraryCourseFinder || {};

  function postPlan(courseId, state) {
    var body = new URLSearchParams();
    body.set('action', 'sc_library_course_plan');
    body.set('nonce', cfg.nonce || '');
    body.set('course_id', courseId);
    body.set('state', state);
    return fetch(cfg.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) { return response.json(); });
  }

  document.querySelectorAll('[data-sc-course-finder]').forEach(function (root) {
    var status = root.querySelector('[data-sc-course-status]');
    var savedCount = root.querySelector('[data-sc-course-saved-count]');
    root.addEventListener('change', function (event) {
      var select = event.target.closest('[data-sc-course-plan-state]');
      if (!select) { return; }
      if (!cfg.signedIn) {
        if (cfg.loginUrl) { window.location.href = cfg.loginUrl; }
        return;
      }
      var card = select.closest('[data-sc-course-card]');
      if (!card) { return; }
      select.disabled = true;
      postPlan(card.dataset.courseId || '', select.value).then(function (payload) {
        if (!payload || !payload.success) { throw new Error(payload && payload.data && payload.data.message ? payload.data.message : (cfg.strings && cfg.strings.saveError) || 'Could not update learning plan.'); }
        var state = payload.data && payload.data.state === 'remove' ? '' : payload.data.state;
        card.dataset.courseLearning = state;
        if (savedCount && payload.data && typeof payload.data.saved_count !== 'undefined') {
          savedCount.textContent = String(payload.data.saved_count);
        }
        if (status) { status.textContent = (cfg.strings && cfg.strings.saved) || 'Learning plan updated.'; }
        document.dispatchEvent(new CustomEvent('sc-library-course-plan-updated', { detail: { courseId: card.dataset.courseId || '', state: state } }));
      }).catch(function (error) {
        if (status) { status.textContent = error.message || ((cfg.strings && cfg.strings.saveError) || 'Could not update learning plan.'); }
      }).finally(function () {
        select.disabled = false;
      });
    });
  });
})();
