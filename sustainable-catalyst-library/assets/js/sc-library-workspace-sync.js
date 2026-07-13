(() => {
  'use strict';
  const cfg = window.SCLibraryAccountSync || {};
  if (!cfg.enabled) return;
  const roots = () => Array.from(document.querySelectorAll('[data-sc-library-sync-inline]'));
  const metaKey = 'scLibraryWorkspaceAccountLinkV1';
  let items = [];
  let health = null;
  let busy = false;
  let autoTimer = null;
  let suppressAuto = false;
  let lastNotice = '';
  let lastNoticeError = false;

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const fallbackAPI = {
    getWorkspace() {
      try {
        const saved = JSON.parse(localStorage.getItem(cfg.storageKey || 'scLibraryWorkspaceV120') || 'null');
        if (saved) return saved;
      } catch {}
      const stamp = new Date().toISOString();
      return {schema:cfg.schemas?.workspace || 'sc-library-workspace/1.7',version:'1.13.2',createdAt:stamp,updatedAt:stamp,collections:[{id:'collection_inbox',title:'Research Inbox',description:'Newly saved Library records and research material.',createdAt:stamp,updatedAt:stamp}],savedRecords:[],notes:[],sources:[],matrices:[],boards:[],handoffs:[],annotations:[],books:[]};
    },
    replaceWorkspace(next) { localStorage.setItem(cfg.storageKey || 'scLibraryWorkspaceV120', JSON.stringify(next)); window.dispatchEvent(new CustomEvent('sc-library-workspace-updated')); return next; },
  };
  const api = () => window.SCLibraryWorkspaceAPI || fallbackAPI;
  const getMeta = () => { try { return JSON.parse(localStorage.getItem(metaKey) || '{}'); } catch { return {}; } };
  const setMeta = (value) => localStorage.setItem(metaKey, JSON.stringify(value || {}));
  const fmt = (v) => { const d = new Date(v); return Number.isNaN(d.getTime()) ? '' : d.toLocaleString(); };
  const statusClass = (s) => ['error','conflict'].includes(s) ? `is-${s}` : s === 'synced' ? 'is-online' : s === 'pending' ? 'is-pending' : '';

  const request = async (path, options = {}) => {
    const response = await fetch(`${cfg.restRoot}${path}`, {
      credentials: 'same-origin',
      ...options,
      headers: {'Content-Type':'application/json','X-WP-Nonce':cfg.nonce,...(options.headers || {})},
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const err = new Error(payload.message || `Request failed (${response.status})`);
      err.status = response.status;
      err.data = payload.data || payload;
      throw err;
    }
    return payload;
  };

  const notice = (message, error = false) => {
    lastNotice = message;
    lastNoticeError = error;
    roots().forEach((root) => {
      const node = root.querySelector('[data-sync-notice]');
      if (!node) return;
      node.textContent = message;
      node.hidden = !message;
      node.classList.toggle('is-error', error);
    });
  };

  const localBackup = () => {
    const workspace = api()?.getWorkspace?.();
    if (!workspace) return;
    const blob = new Blob([JSON.stringify(workspace, null, 2)], {type:'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `sc-library-workspace-backup-${new Date().toISOString().slice(0,10)}.json`; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  const card = (item) => {
    const meta = getMeta();
    const linked = meta.uuid === item.workspace_uuid;
    return `<article class="sc-library-sync-card ${linked ? 'is-linked' : ''}" data-sync-card="${esc(item.workspace_uuid)}">
      <div><span class="sc-library-sync-badge ${statusClass(item.sync_status)}">${esc(item.sync_status || 'local')}</span>${linked ? ' <span class="sc-library-sync-badge">linked browser copy</span>' : ''}</div>
      <h4>${esc(item.title)}</h4><p>${esc(item.description || 'No description')}</p>
      <dl><dt>Revision</dt><dd>${Number(item.revision || 0)}</dd><dt>Visibility</dt><dd>${esc(item.visibility)}</dd><dt>Updated</dt><dd>${esc(fmt(item.updated_at))}</dd>${item.last_synced_at ? `<dt>Render sync</dt><dd>${esc(fmt(item.last_synced_at))}</dd>` : ''}</dl>
      ${item.sync_error ? `<p class="sc-library-sync-notice is-error">${esc(item.sync_error)}</p>` : ''}
      <div class="sc-library-sync-actions">
        <button type="button" class="secondary" data-sync-load="${esc(item.workspace_uuid)}">Load</button>
        <button type="button" data-sync-save="${esc(item.workspace_uuid)}">Save current</button>
        ${cfg.renderConfigured ? `<button type="button" class="secondary" data-sync-render="${esc(item.workspace_uuid)}">Sync Render</button>` : ''}
        <button type="button" class="secondary" data-sync-history="${esc(item.workspace_uuid)}">History</button>
        ${item.owned ? `<button type="button" class="secondary" data-sync-share="${esc(item.workspace_uuid)}">Share</button><button type="button" class="danger" data-sync-delete="${esc(item.workspace_uuid)}">Delete</button>` : ''}
      </div><div data-sync-card-detail></div>
    </article>`;
  };

  const render = () => {
    roots().forEach((root) => {
      if (!cfg.loggedIn) {
        root.innerHTML = `<section class="sc-library-sync-studio"><div class="sc-library-sync-notice">Sign in to save this browser workspace to your account and use it across devices.</div><div class="sc-library-sync-actions"><a href="${esc(cfg.loginUrl)}">Sign in</a><button type="button" class="secondary" data-sync-backup>Export local backup</button></div></section>`;
        return;
      }
      const meta = getMeta();
      root.innerHTML = `<section class="sc-library-sync-studio">
        <div class="sc-library-sync-studio__status"><span class="sc-library-sync-badge is-online">Account active</span><span>${esc(cfg.user?.name || '')}</span><span>Mode: <strong>${esc(cfg.storageMode)}</strong></span><span>Render: <strong>${cfg.renderConfigured ? esc(health?.health?.label || 'configured') : 'not configured'}</strong></span></div>
        <div class="sc-library-sync-notice ${lastNoticeError ? 'is-error' : ''}" data-sync-notice ${lastNotice ? '' : 'hidden'}>${esc(lastNotice)}</div>
        <form class="sc-library-sync-form" data-sync-create-form><h3>Save current browser workspace</h3><label>Workspace title<input name="title" required value="${esc(meta.title || 'Research Workspace')}"></label><label>Description<textarea name="description" rows="2"></textarea></label><label>Visibility<select name="visibility"><option value="private">Private</option><option value="shared">Shared by invitation</option><option value="public">Public read-only record</option></select></label><div class="sc-library-sync-actions"><button type="submit">Create account workspace</button><button type="button" class="secondary" data-sync-backup>Export local backup</button><button type="button" class="secondary" data-sync-refresh>Refresh list</button></div></form>
        <div><h3>Account workspaces</h3>${items.length ? `<div class="sc-library-sync-grid">${items.map(card).join('')}</div>` : '<div class="sc-library-sync-empty">No account workspaces yet. Save the current browser workspace to create one.</div>'}</div>
      </section>`;
    });
  };

  const refresh = async () => {
    if (!cfg.loggedIn || busy) { render(); return; }
    busy = true;
    try {
      const [list, service] = await Promise.all([request('workspaces'), cfg.renderConfigured ? request('workspaces/render/status').catch(() => null) : Promise.resolve(null)]);
      items = list.items || []; health = service;
    } catch (e) { notice(e.message, true); }
    finally { busy = false; render(); }
  };

  const create = async (form) => {
    const workspace = api()?.getWorkspace?.();
    if (!workspace) throw new Error('The local workspace is not ready.');
    const data = new FormData(form);
    const result = await request('workspaces', {method:'POST', body:JSON.stringify({title:data.get('title'),description:data.get('description'),visibility:data.get('visibility'),workspace})});
    setMeta({uuid:result.workspace_uuid,revision:result.revision,contentHash:result.content_hash,title:result.title});
    notice(cfg.strings?.migrated || 'Workspace saved to your account.');
    await refresh();
  };

  const save = async (uuid, quiet = false) => {
    const workspace = api()?.getWorkspace?.();
    const item = items.find((x) => x.workspace_uuid === uuid);
    if (!workspace || !item) throw new Error('Workspace unavailable.');
    const result = await request(`workspaces/${uuid}`, {method:'PUT', body:JSON.stringify({title:item.title,description:item.description,visibility:item.visibility,expected_revision:item.revision,workspace})});
    setMeta({uuid:result.workspace_uuid,revision:result.revision,contentHash:result.content_hash,title:result.title});
    if (!quiet) notice(cfg.strings?.saved || 'Workspace saved.');
    await refresh();
  };

  const loadRemote = async (uuid) => {
    if (!window.confirm('Export a backup first if the current browser workspace contains unsaved work. Replace it with this account revision?')) return;
    const result = await request(`workspaces/${uuid}`);
    suppressAuto = true;
    api()?.replaceWorkspace?.(result.workspace);
    setMeta({uuid:result.workspace_uuid,revision:result.revision,contentHash:result.content_hash,title:result.title});
    setTimeout(() => { suppressAuto = false; }, 500);
    notice(cfg.strings?.loaded || 'Account workspace loaded.');
    await refresh();
  };

  const renderHistory = async (uuid, cardNode) => {
    const result = await request(`workspaces/${uuid}/history`);
    cardNode.querySelector('[data-sync-card-detail]').innerHTML = `<div class="sc-library-sync-history"><h4>Recent revisions</h4>${(result.items || []).map((x) => `<article><span>Revision ${Number(x.revision)} · ${esc(x.change_type)}</span><time>${esc(fmt(x.created_at))}</time></article>`).join('') || '<p>No history.</p>'}</div>`;
  };

  const share = async (uuid, cardNode) => {
    const email = window.prompt('WordPress account email to invite:'); if (!email) return;
    const role = window.prompt('Role: viewer or editor', 'viewer') || 'viewer';
    const result = await request(`workspaces/${uuid}/share`, {method:'POST', body:JSON.stringify({email,role})});
    cardNode.querySelector('[data-sync-card-detail]').innerHTML = `<p class="sc-library-sync-notice">Shared with ${esc(result.user?.name || email)} as ${esc(result.role)}.</p>`;
  };

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-sync-create-form]'); if (!form) return;
    event.preventDefault(); create(form).catch((e) => notice(e.message, true));
  });
  document.addEventListener('click', (event) => {
    const backup = event.target.closest('[data-sync-backup]'); if (backup) { localBackup(); return; }
    const refreshButton = event.target.closest('[data-sync-refresh]'); if (refreshButton) { refresh(); return; }
    const button = event.target.closest('[data-sync-load],[data-sync-save],[data-sync-render],[data-sync-history],[data-sync-share],[data-sync-delete]'); if (!button) return;
    const cardNode = button.closest('[data-sync-card]');
    const action = button.hasAttribute('data-sync-load') ? 'load' : button.hasAttribute('data-sync-save') ? 'save' : button.hasAttribute('data-sync-render') ? 'render' : button.hasAttribute('data-sync-history') ? 'history' : button.hasAttribute('data-sync-share') ? 'share' : 'delete';
    const uuid = button.getAttribute(`data-sync-${action}`);
    const run = async () => {
      if (action === 'load') return loadRemote(uuid);
      if (action === 'save') return save(uuid);
      if (action === 'render') { const r = await request(`workspaces/${uuid}/sync`, {method:'POST',body:'{}'}); notice(r.synced ? (cfg.strings?.syncComplete || 'Synchronized.') : 'Sync completed.'); return refresh(); }
      if (action === 'history') return renderHistory(uuid, cardNode);
      if (action === 'share') return share(uuid, cardNode);
      if (action === 'delete' && window.confirm('Delete this account workspace and its saved revision history?')) { await request(`workspaces/${uuid}`, {method:'DELETE'}); if (getMeta().uuid === uuid) setMeta({}); return refresh(); }
    };
    run().catch((e) => {
      if (e.status === 409) notice(cfg.strings?.conflict || e.message, true); else notice(e.message, true);
    });
  });

  window.addEventListener('sc-library-workspace-updated', () => {
    if (!cfg.loggedIn || !cfg.autoSync || suppressAuto) return;
    const meta = getMeta(); if (!meta.uuid) return;
    clearTimeout(autoTimer);
    autoTimer = setTimeout(() => save(meta.uuid, true).catch((e) => notice(e.message, true)), 2500);
  });
  window.addEventListener('sc-library-account-sync-render', render);
  document.addEventListener('sc-library-account-sync-render', render);
  refresh();
})();
