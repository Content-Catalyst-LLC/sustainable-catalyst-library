(() => {
  'use strict';

  const cfg = window.SCLibraryCollaboration || {};
  const roots = Array.from(document.querySelectorAll('[data-sc-library-editorial]'));
  if (!roots.length || !cfg.enabled) return;

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  })[char]);

  const api = async (path, options = {}) => {
    const response = await fetch(`${cfg.restRoot}${path.replace(/^\//, '')}`, {
      ...options,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || '',
        ...(options.headers || {})
      }
    });
    let payload = {};
    try { payload = await response.json(); } catch (e) { payload = {}; }
    if (!response.ok) {
      const error = new Error(payload.message || cfg.strings?.error || 'Request failed.');
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  };

  const date = (value) => {
    if (!value) return '—';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? esc(value) : parsed.toLocaleString();
  };

  const button = (label, action, extra = '') => `<button type="button" class="sc-library-editorial-button ${extra}" data-action="${esc(action)}">${esc(label)}</button>`;

  const statusOptions = (selected) => Object.entries(cfg.statuses || {}).map(([value, label]) =>
    `<option value="${esc(value)}" ${value === selected ? 'selected' : ''}>${esc(label)}</option>`
  ).join('');

  const roleOptions = () => Object.entries(cfg.roles || {}).map(([value, label]) =>
    `<option value="${esc(value)}">${esc(label)}</option>`
  ).join('');

  const subjectOptions = () => Object.entries(cfg.subjectTypes || {}).map(([value, label]) =>
    `<option value="${esc(value)}">${esc(label)}</option>`
  ).join('');

  class EditorialApp {
    constructor(root) {
      this.root = root;
      this.reviewId = root.dataset.review || '';
      this.items = [];
      this.active = null;
      this.message = '';
      this.boot();
    }

    async boot() {
      this.root.innerHTML = `<p class="sc-library-editorial-loading">${esc(cfg.strings?.loading || 'Loading…')}</p>`;
      if (cfg.inviteToken) {
        try {
          const accepted = await api('invitations/accept', { method: 'POST', body: JSON.stringify({ token: cfg.inviteToken }) });
          if (accepted.review_uuid) this.reviewId = accepted.review_uuid;
          this.message = 'Invitation accepted.';
          const url = new URL(window.location.href);
          url.searchParams.delete('sc_library_review_invite');
          window.history.replaceState({}, '', url.toString());
        } catch (error) {
          this.message = error.message;
        }
      }
      await this.load();
    }

    async load() {
      try {
        if (this.reviewId) {
          this.active = await api(`reviews/${this.reviewId}`);
          this.renderDetail();
          return;
        }
        const response = await api('reviews');
        this.items = response.items || [];
        this.renderList();
      } catch (error) {
        this.root.innerHTML = `<div class="sc-library-editorial-notice sc-library-editorial-notice--error">${esc(error.message)}</div>`;
      }
    }

    renderList() {
      const cards = this.items.length ? this.items.map((item) => `
        <article class="sc-library-editorial-card" data-review-id="${esc(item.review_uuid)}">
          <div class="sc-library-editorial-card__meta">
            <span class="sc-library-editorial-badge sc-library-editorial-badge--${esc(item.status)}">${esc(item.status_label)}</span>
            <span>${esc(item.priority)}</span>
            <span>${esc(cfg.subjectTypes?.[item.subject_type] || item.subject_type)}</span>
          </div>
          <h3>${esc(item.title)}</h3>
          <p>${esc(item.summary || 'No summary provided.')}</p>
          <dl>
            <div><dt>Owner</dt><dd>${esc(item.owner?.name || '—')}</dd></div>
            <div><dt>Due</dt><dd>${date(item.due_at)}</dd></div>
            <div><dt>Updated</dt><dd>${date(item.updated_at)}</dd></div>
          </dl>
          ${button('Open review', 'open')}
        </article>
      `).join('') : `<p class="sc-library-editorial-empty">${esc(cfg.strings?.empty || 'No reviews.')}</p>`;

      this.root.innerHTML = `
        ${this.message ? `<div class="sc-library-editorial-notice">${esc(this.message)}</div>` : ''}
        <div class="sc-library-editorial-toolbar">
          <div>
            <h3>Editorial reviews</h3>
            <p>Shared reviews, comments, suggestions, approvals, and attributed history.</p>
          </div>
          ${button('Create review', 'toggle-create', 'sc-library-editorial-button--primary')}
        </div>
        <form class="sc-library-editorial-create" data-create-form hidden>
          <div class="sc-library-editorial-form-grid">
            <label>Title<input name="title" required maxlength="255"></label>
            <label>Subject type<select name="subject_type">${subjectOptions()}</select></label>
            <label>Subject key<input name="subject_key" placeholder="Post ID, workspace UUID, book ID, or label"></label>
            <label>Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
            <label>Due date<input type="datetime-local" name="due_at"></label>
            <label>Visibility<select name="visibility"><option value="private">Private</option><option value="participants">Participants</option><option value="organization">Organization</option><option value="public">Public read-only summary</option></select></label>
          </div>
          <label>Summary<textarea name="summary" rows="4"></textarea></label>
          <div class="sc-library-editorial-actions">${button('Create editorial review', 'create-review', 'sc-library-editorial-button--primary')} ${button('Cancel', 'toggle-create')}</div>
        </form>
        <div class="sc-library-editorial-grid">${cards}</div>
      `;
      this.bindList();
    }

    bindList() {
      this.root.querySelectorAll('[data-action="open"]').forEach((el) => el.addEventListener('click', () => {
        const card = el.closest('[data-review-id]');
        this.reviewId = card?.dataset.reviewId || '';
        this.load();
      }));
      this.root.querySelectorAll('[data-action="toggle-create"]').forEach((el) => el.addEventListener('click', () => {
        const form = this.root.querySelector('[data-create-form]');
        if (form) form.hidden = !form.hidden;
      }));
      const create = this.root.querySelector('[data-action="create-review"]');
      if (create) create.addEventListener('click', async () => {
        const form = this.root.querySelector('[data-create-form]');
        if (!form || !form.reportValidity()) return;
        const data = Object.fromEntries(new FormData(form).entries());
        create.disabled = true;
        try {
          const item = await api('reviews', { method: 'POST', body: JSON.stringify(data) });
          this.reviewId = item.review_uuid;
          await this.load();
        } catch (error) {
          window.alert(error.message);
        } finally { create.disabled = false; }
      });
    }

    renderDetail() {
      const item = this.active;
      const comments = (item.comments || []).map((comment) => `
        <article class="sc-library-editorial-thread-item sc-library-editorial-thread-item--${esc(comment.status)}">
          <header><strong>${esc(comment.author?.name || 'Contributor')}</strong><span>${date(comment.created_at)}</span><span>${esc(comment.status)}</span></header>
          <div>${comment.body || ''}</div>
          ${comment.status === 'open' && item.permissions?.comment ? button('Resolve', `resolve-comment:${comment.comment_uuid}`) : ''}
        </article>
      `).join('') || '<p>No comments yet.</p>';

      const suggestions = (item.suggestions || []).map((suggestion) => `
        <article class="sc-library-editorial-suggestion sc-library-editorial-suggestion--${esc(suggestion.status)}">
          <header><strong>${esc(suggestion.author?.name || 'Contributor')}</strong><span>${esc(suggestion.field_key)}</span><span>${esc(suggestion.status)}</span></header>
          ${suggestion.original_text ? `<div class="sc-library-editorial-diff sc-library-editorial-diff--old">${suggestion.original_text}</div>` : ''}
          <div class="sc-library-editorial-diff sc-library-editorial-diff--new">${suggestion.proposed_text}</div>
          ${suggestion.rationale ? `<p>${esc(suggestion.rationale)}</p>` : ''}
          ${suggestion.status === 'pending' && item.permissions?.edit ? `<div class="sc-library-editorial-actions">${button('Accept', `suggestion:${suggestion.suggestion_uuid}:accepted`)} ${button('Reject', `suggestion:${suggestion.suggestion_uuid}:rejected`)}</div>` : ''}
        </article>
      `).join('') || '<p>No suggested edits yet.</p>';

      const participants = (item.participants || []).map((participant) => `
        <li><strong>${esc(participant.name || participant.email)}</strong> · ${esc(participant.role_label)} · ${esc(participant.status)}${item.permissions?.manage && participant.user_id !== item.owner?.id ? ` ${button('Remove', `remove-participant:${participant.participant_id}`)}` : ''}</li>
      `).join('') || '<li>No participants listed.</li>';

      const activity = (item.activity || []).map((event) => `<li><strong>${esc(event.actor?.name || 'System')}</strong> ${esc(event.event_type.replaceAll('_', ' '))} <time>${date(event.created_at)}</time></li>`).join('');
      const lockLabel = item.lock?.active ? `${item.lock.mine ? 'You hold' : esc(item.lock.user_name || 'Another editor holds')} the edit lock until ${date(item.lock.expires_at)}.` : 'No active edit lock.';

      this.root.innerHTML = `
        ${this.message ? `<div class="sc-library-editorial-notice">${esc(this.message)}</div>` : ''}
        <div class="sc-library-editorial-toolbar">
          <div>${button('← All reviews', 'back')}<h3>${esc(item.title)}</h3><p>${esc(item.summary || '')}</p></div>
          <span class="sc-library-editorial-badge sc-library-editorial-badge--${esc(item.status)}">${esc(item.status_label)}</span>
        </div>
        <div class="sc-library-editorial-detail-grid">
          <main>
            <section class="sc-library-editorial-panel">
              <h4>Review state</h4>
              <div class="sc-library-editorial-form-grid">
                <label>Status<select data-status>${statusOptions(item.status)}</select></label>
                <label>Revision<input value="${esc(item.revision)}" disabled></label>
                <label>Due<input value="${esc(item.due_at || '')}" disabled></label>
                <label>Subject<input value="${esc(cfg.subjectTypes?.[item.subject_type] || item.subject_type)}: ${esc(item.subject_key || item.post_id || item.workspace_uuid || '')}" disabled></label>
              </div>
              <label>Decision note<textarea data-decision-note rows="3">${esc(item.decision_note || '')}</textarea></label>
              <div class="sc-library-editorial-actions">${item.permissions?.edit || item.permissions?.approve ? button('Update status', 'transition', 'sc-library-editorial-button--primary') : ''}</div>
            </section>
            <section class="sc-library-editorial-panel">
              <h4>Comments</h4>
              <div class="sc-library-editorial-thread">${comments}</div>
              ${item.permissions?.comment ? `<label>Add comment<textarea data-comment rows="4"></textarea></label>${button('Add comment', 'add-comment', 'sc-library-editorial-button--primary')}` : ''}
            </section>
            <section class="sc-library-editorial-panel">
              <h4>Suggested edits</h4>
              <div>${suggestions}</div>
              ${item.permissions?.suggest ? `<div class="sc-library-editorial-form-grid"><label>Field<select data-suggestion-field><option value="content">Content</option><option value="title">Title</option><option value="excerpt">Excerpt</option><option value="metadata">Metadata</option></select></label><label>Original text<textarea data-suggestion-original rows="3"></textarea></label></div><label>Proposed text<textarea data-suggestion-proposed rows="5"></textarea></label><label>Rationale<textarea data-suggestion-rationale rows="3"></textarea></label>${button('Record suggested edit', 'add-suggestion', 'sc-library-editorial-button--primary')}` : ''}
            </section>
          </main>
          <aside>
            <section class="sc-library-editorial-panel">
              <h4>Record lock</h4><p>${lockLabel}</p>
              ${item.permissions?.edit ? (item.lock?.mine ? button('Release lock', 'unlock') : button('Acquire edit lock', 'lock')) : ''}
            </section>
            <section class="sc-library-editorial-panel"><h4>Participants</h4><ul class="sc-library-editorial-participants">${participants}</ul>
              ${item.permissions?.manage ? `<div class="sc-library-editorial-form-grid"><label>Email<input type="email" data-invite-email></label><label>Role<select data-invite-role>${roleOptions()}</select></label></div>${button('Invite participant', 'invite', 'sc-library-editorial-button--primary')}` : ''}
            </section>
            <section class="sc-library-editorial-panel"><h4>Activity and attribution</h4><ol class="sc-library-editorial-activity">${activity}</ol></section>
          </aside>
        </div>
      `;
      this.bindDetail();
    }

    bindDetail() {
      const run = async (fn) => {
        try { await fn(); await this.load(); } catch (error) { window.alert(error.message); }
      };
      this.root.querySelector('[data-action="back"]')?.addEventListener('click', () => { this.reviewId = ''; this.active = null; this.load(); });
      this.root.querySelector('[data-action="transition"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/transition`, { method: 'POST', body: JSON.stringify({ status: this.root.querySelector('[data-status]')?.value, note: this.root.querySelector('[data-decision-note]')?.value }) })));
      this.root.querySelector('[data-action="add-comment"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/comments`, { method: 'POST', body: JSON.stringify({ body: this.root.querySelector('[data-comment]')?.value }) })));
      this.root.querySelector('[data-action="add-suggestion"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/suggestions`, { method: 'POST', body: JSON.stringify({ field_key: this.root.querySelector('[data-suggestion-field]')?.value, original_text: this.root.querySelector('[data-suggestion-original]')?.value, proposed_text: this.root.querySelector('[data-suggestion-proposed]')?.value, rationale: this.root.querySelector('[data-suggestion-rationale]')?.value }) })));
      this.root.querySelector('[data-action="lock"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/lock`, { method: 'POST', body: '{}' })));
      this.root.querySelector('[data-action="unlock"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/unlock`, { method: 'POST', body: '{}' })));
      this.root.querySelector('[data-action="invite"]')?.addEventListener('click', () => run(() => api(`reviews/${this.reviewId}/participants`, { method: 'POST', body: JSON.stringify({ email: this.root.querySelector('[data-invite-email]')?.value, role: this.root.querySelector('[data-invite-role]')?.value }) })));
      this.root.querySelectorAll('[data-action^="resolve-comment:"]').forEach((el) => el.addEventListener('click', () => run(() => api(`comments/${el.dataset.action.split(':')[1]}`, { method: 'PATCH', body: JSON.stringify({ status: 'resolved' }) }))));
      this.root.querySelectorAll('[data-action^="suggestion:"]').forEach((el) => el.addEventListener('click', () => {
        const [, uuid, status] = el.dataset.action.split(':');
        run(() => api(`suggestions/${uuid}`, { method: 'PATCH', body: JSON.stringify({ status }) }));
      }));
      this.root.querySelectorAll('[data-action^="remove-participant:"]').forEach((el) => el.addEventListener('click', () => {
        const id = el.dataset.action.split(':')[1];
        run(() => api(`reviews/${this.reviewId}/participants?participant_id=${encodeURIComponent(id)}`, { method: 'DELETE' }));
      }));
    }
  }

  roots.forEach((root) => new EditorialApp(root));
})();
