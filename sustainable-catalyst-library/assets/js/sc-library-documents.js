(() => {
  'use strict';
  const shared = window.SCDocumentsShared || {};
  const restBase = String(shared.restBase || '/wp-json/sustainable-catalyst/v1/library/documents').replace(/\/$/, '');
  const nonce = shared.nonce || '';
  const strings = shared.strings || {};
  const roots = new WeakSet();
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const formatDate = (value) => value ? new Date(value).toLocaleString() : '';
  const formatBytes = (value) => { const n = Number(value || 0); if (!n) return '—'; if (n < 1024) return `${n} B`; if (n < 1048576) return `${(n/1024).toFixed(1)} KB`; return `${(n/1048576).toFixed(1)} MB`; };
  const api = async (path = '', options = {}) => {
    const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(nonce ? { 'X-WP-Nonce': nonce } : {}), ...(options.headers || {}) };
    const response = await fetch(`${restBase}${path}`, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || data.code || `HTTP ${response.status}`);
    return data;
  };
  const make = (root) => {
    if (roots.has(root)) return;
    roots.add(root);
    const stage = root.querySelector('[data-document-production-stage]') || root;
    let tab = 'jobs'; let jobs = []; let editions = []; let status = null; let notice = '';
    const load = async () => {
      notice = '';
      try {
        [status, jobs, editions] = await Promise.all([
          api('/status'), api('/jobs?per_page=50').then((d) => d.items || []), api('/editions?per_page=50').then((d) => d.items || []),
        ]);
      } catch (error) { notice = error.message; }
      render();
    };
    const jobRow = (job) => `<tr><td><strong>${escapeHtml(job.title)}</strong><br><small>${escapeHtml(job.job_uuid)}</small></td><td><span class="sc-library-documents__badge" data-status="${escapeHtml(job.status)}">${escapeHtml(job.status)}</span><div class="sc-library-documents__progress"><span style="width:${Math.max(0,Math.min(100,Number(job.progress||0)))}%"></span></div></td><td>${job.attempt}/${job.max_attempts}</td><td>${formatBytes(job.output_bytes)}${job.output_sha256 ? `<br><small>SHA-256 ${escapeHtml(job.output_sha256.slice(0,16))}…</small>` : ''}</td><td>${escapeHtml(formatDate(job.updated_at))}${job.error ? `<p class="notice-error">${escapeHtml(job.error)}</p>` : ''}</td><td><div class="sc-library-documents__actions">${job.output_url ? `<a class="is-primary" href="${escapeHtml(job.output_url)}" target="_blank" rel="noopener">Open PDF</a>` : ''}${['queued','processing','submitting','completed'].includes(job.status) ? `<button type="button" data-document-refresh="${escapeHtml(job.job_uuid)}">Refresh</button>` : ''}${job.status === 'error' && job.attempt < job.max_attempts ? `<button type="button" data-document-retry="${escapeHtml(job.job_uuid)}">Retry</button>` : ''}<button type="button" data-document-delete="${escapeHtml(job.job_uuid)}">Delete job</button></div>${job.diagnostics && Object.keys(job.diagnostics).length ? `<details><summary>Diagnostics</summary><pre class="sc-library-documents__diagnostics">${escapeHtml(JSON.stringify(job.diagnostics,null,2))}</pre></details>` : ''}</td></tr>`;
    const editionRow = (edition) => `<tr><td><strong>${escapeHtml(edition.title)}</strong><br><small>${escapeHtml(edition.edition)}</small></td><td>${escapeHtml(formatDate(edition.frozen_at))}</td><td><small>${escapeHtml(edition.content_hash.slice(0,20))}…</small></td><td><small>${escapeHtml(edition.output_sha256.slice(0,20))}…</small></td><td>${edition.url ? `<a class="is-primary" href="${escapeHtml(edition.url)}" target="_blank" rel="noopener">Open frozen PDF</a>` : 'Unavailable'}</td></tr>`;
    const render = () => {
      const counts = { queued:0, processing:0, completed:0, error:0 };
      jobs.forEach((j) => { if (['queued','submitting'].includes(j.status)) counts.queued++; else if (j.status === 'processing') counts.processing++; else if (['completed','imported'].includes(j.status)) counts.completed++; else if (j.status === 'error') counts.error++; });
      stage.innerHTML = `<div class="sc-library-documents">${notice ? `<div class="sc-library-documents__status is-error">${escapeHtml(notice)}</div>` : ''}<div class="sc-library-documents__status">${status?.configured ? `Render service: <strong>${escapeHtml(status.remote?.state || 'configured')}</strong>. Completed files ${status.auto_import ? 'are imported automatically' : 'require manual refresh/import'}.` : 'Render service is not configured. Browser Print / Save as PDF remains available.'}</div><div class="sc-library-documents__metrics"><article><strong>${counts.queued}</strong><span>Queued</span></article><article><strong>${counts.processing}</strong><span>Processing</span></article><article><strong>${counts.completed}</strong><span>Completed</span></article><article><strong>${counts.error}</strong><span>Errors</span></article><article><strong>${editions.length}</strong><span>Frozen editions</span></article></div><nav class="sc-library-documents__tabs"><button type="button" data-doc-tab="jobs" class="${tab==='jobs'?'is-active':''}">Render jobs</button><button type="button" data-doc-tab="editions" class="${tab==='editions'?'is-active':''}">Frozen editions</button></nav>${tab === 'jobs' ? `<div class="sc-library-documents__table-wrap"><table><thead><tr><th>Book</th><th>Status</th><th>Attempts</th><th>Output</th><th>Updated</th><th>Actions</th></tr></thead><tbody>${jobs.length ? jobs.map(jobRow).join('') : '<tr><td colspan="6"><div class="sc-library-documents__empty">No server document jobs yet. Create one from the Book Builder PDF / Export tab.</div></td></tr>'}</tbody></table></div>` : `<div class="sc-library-documents__table-wrap"><table><thead><tr><th>Edition</th><th>Frozen</th><th>Content hash</th><th>PDF checksum</th><th>File</th></tr></thead><tbody>${editions.length ? editions.map(editionRow).join('') : '<tr><td colspan="5"><div class="sc-library-documents__empty">No frozen server-rendered editions yet.</div></td></tr>'}</tbody></table></div>`}</div>`;
    };
    root.addEventListener('click', async (event) => {
      const tabButton = event.target.closest('[data-doc-tab]'); if (tabButton) { tab = tabButton.dataset.docTab; render(); return; }
      if (event.target.closest('[data-document-refresh-list]')) { await load(); return; }
      const refresh = event.target.closest('[data-document-refresh]'); if (refresh) { notice = strings.refreshing || 'Refreshing…'; render(); try { await api(`/jobs/${refresh.dataset.documentRefresh}/refresh`, { method:'POST', body:'{}' }); await load(); } catch(e){ notice=e.message;render(); } return; }
      const retry = event.target.closest('[data-document-retry]'); if (retry) { notice = strings.retrying || 'Retrying…'; render(); try { await api(`/jobs/${retry.dataset.documentRetry}/retry`, { method:'POST', body:'{}' }); await load(); } catch(e){ notice=e.message;render(); } return; }
      const del = event.target.closest('[data-document-delete]'); if (del) { if (!window.confirm(strings.confirmDelete || 'Delete this job?')) return; try { await api(`/jobs/${del.dataset.documentDelete}`, { method:'DELETE' }); await load(); } catch(e){ notice=e.message;render(); } }
    });
    load();
  };
  const mount = () => document.querySelectorAll('[data-sc-library-documents-root]').forEach(make);
  document.addEventListener('DOMContentLoaded', mount); mount();
})();
