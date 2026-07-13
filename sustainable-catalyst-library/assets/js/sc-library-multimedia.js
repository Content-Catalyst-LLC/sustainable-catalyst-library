(() => {
  'use strict';

  const shared = window.SCMultimediaShared || {};
  const roots = [...document.querySelectorAll('[data-sc-library-multimedia-root]')];
  if (!roots.length) return;

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const attr = esc;
  const jsonHeaders = () => ({'Content-Type':'application/json', ...(shared.nonce ? {'X-WP-Nonce': shared.nonce} : {})});
  const api = async (path = '', options = {}) => {
    const response = await fetch(`${shared.restBase}${path}`, {credentials:'same-origin', ...options, headers:{...jsonHeaders(), ...(options.headers || {})}});
    const text = await response.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch (error) { data = {message:text || response.statusText}; }
    if (!response.ok) throw new Error(data.message || data.detail || `Request failed (${response.status})`);
    return data;
  };
  const msToTime = (ms) => {
    const total = Math.max(0, Math.floor(Number(ms || 0) / 1000));
    const hours = String(Math.floor(total / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    const seconds = String(total % 60).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
  };
  const timeToMs = (value) => {
    const bits = String(value || '').trim().split(':').map(Number);
    if (bits.some((item) => Number.isNaN(item))) return 0;
    if (bits.length === 3) return ((bits[0] * 3600) + (bits[1] * 60) + bits[2]) * 1000;
    if (bits.length === 2) return ((bits[0] * 60) + bits[1]) * 1000;
    return (bits[0] || 0) * 1000;
  };
  const fmtBytes = (bytes) => {
    const value = Number(bytes || 0);
    if (!value) return '—';
    const units = ['B','KB','MB','GB']; let index = 0; let output = value;
    while (output >= 1024 && index < units.length - 1) { output /= 1024; index += 1; }
    return `${output.toFixed(index ? 1 : 0)} ${units[index]}`;
  };
  const date = (value) => value ? new Date(value).toLocaleString() : '—';
  const statusBadge = (value) => `<span class="sc-media-status sc-media-status--${attr(value || 'draft')}">${esc(String(value || 'draft').replaceAll('_', ' '))}</span>`;
  const rightsOptions = (selected) => Object.entries(shared.rightsStatuses || {}).map(([key, label]) => `<option value="${attr(key)}" ${key === selected ? 'selected' : ''}>${esc(label)}</option>`).join('');

  roots.forEach((root) => {
    const state = {tab:'assets', assets:[], clips:[], reels:[], jobs:[], loading:true, error:'', notice:''};

    const setNotice = (message, error = false) => {
      state.notice = error ? '' : message;
      state.error = error ? message : '';
      render();
    };

    const load = async () => {
      if (!shared.canEdit) { state.loading = false; render(); return; }
      state.loading = true; state.error = '';
      render();
      try {
        const [assets, clips, reels, jobs] = await Promise.all([
          api('/assets'), api('/clips'), api('/reels'), api('/jobs')
        ]);
        state.assets = assets.items || [];
        state.clips = clips.items || [];
        state.reels = reels.items || [];
        state.jobs = jobs.items || [];
      } catch (error) { state.error = error.message; }
      state.loading = false; render();
    };

    const tabs = () => `<nav class="sc-library-multimedia__tabs" aria-label="Multimedia Studio sections">
      ${[['assets','Media assets'],['clips','Clip studio'],['reels','Evidence reels'],['jobs','Processing jobs']].map(([id,label]) => `<button type="button" class="${state.tab === id ? 'is-active' : ''}" data-media-tab="${id}">${label}<span>${state[id]?.length || 0}</span></button>`).join('')}
    </nav>`;

    const assetCard = (item) => `<article class="sc-library-media-card" data-asset-card="${attr(item.asset_uuid)}">
      <div class="sc-library-media-card__preview">${item.media_type === 'audio' ? '♫' : '▶'}</div>
      <div><p class="sc-library-multimedia__eyebrow">${esc(item.media_type)} · ${esc(item.source_kind)}</p><h3>${esc(item.title)}</h3>
      <p>${esc(item.description || 'No description')}</p><dl><div><dt>Duration</dt><dd>${msToTime(item.duration_ms)}</dd></div><div><dt>Rights</dt><dd>${esc((shared.rightsStatuses || {})[item.rights_status] || item.rights_status)}</dd></div><div><dt>Transcript</dt><dd>${item.transcript_text || item.transcript_vtt ? 'Available' : 'Not added'}</dd></div></dl>
      <div class="sc-library-multimedia__actions"><button type="button" data-edit-asset="${attr(item.asset_uuid)}">Edit</button><a href="${attr(item.source_url)}" target="_blank" rel="noopener">Open source</a><button class="is-danger" type="button" data-delete-asset="${attr(item.asset_uuid)}">Delete</button></div></div>
    </article>`;

    const assetForm = (item = {}) => `<form class="sc-library-multimedia__form" data-asset-form data-uuid="${attr(item.asset_uuid || '')}">
      <h3>${item.asset_uuid ? 'Edit media asset' : 'Register authorized media'}</h3>
      <div class="sc-library-multimedia__grid"><label><span>Title</span><input required name="title" value="${attr(item.title || '')}"></label><label><span>Media type</span><select name="media_type"><option value="video" ${item.media_type !== 'audio' ? 'selected' : ''}>Video</option><option value="audio" ${item.media_type === 'audio' ? 'selected' : ''}>Audio</option></select></label></div>
      <label><span>Description</span><textarea name="description" rows="3">${esc(item.description || '')}</textarea></label>
      <fieldset><legend>Source</legend><div class="sc-library-multimedia__grid"><label><span>WordPress attachment ID</span><input name="attachment_id" type="number" min="0" value="${attr(item.attachment_id || '')}"></label><label><span>Duration (HH:MM:SS)</span><input name="duration" value="${attr(msToTime(item.duration_ms || 0))}"></label></div>
      <div class="sc-library-multimedia__actions">${shared.canUpload ? '<button type="button" data-media-picker>Select from Media Library</button>' : ''}</div>
      <label><span>Source URL ${shared.allowRemote ? '(remote sources enabled)' : '(reference only unless selected from Media Library)'}</span><input name="source_url" type="url" value="${attr(item.source_url || '')}"></label></fieldset>
      <fieldset><legend>Rights and provenance</legend><div class="sc-library-multimedia__grid"><label><span>Rights status</span><select name="rights_status">${rightsOptions(item.rights_status || 'unknown')}</select></label><label><span>Rights holder</span><input name="rights_holder" value="${attr(item.rights_holder || '')}"></label></div><div class="sc-library-multimedia__grid"><label><span>License</span><input name="license_name" value="${attr(item.license_name || '')}"></label><label><span>License URL</span><input name="license_url" type="url" value="${attr(item.license_url || '')}"></label></div><label><span>Rights note</span><textarea name="rights_note" rows="2">${esc(item.rights_note || '')}</textarea></label><label><span>Source citation</span><textarea name="source_citation" rows="2">${esc(item.source_citation || '')}</textarea></label></fieldset>
      <fieldset><legend>Transcript, captions, and accessibility</legend><label><span>Plain transcript</span><textarea name="transcript_text" rows="7">${esc(item.transcript_text || '')}</textarea></label><label><span>WebVTT transcript or captions</span><textarea name="transcript_vtt" rows="7" class="code">${esc(item.transcript_vtt || '')}</textarea></label><div class="sc-library-multimedia__grid"><label><span>Caption file URL</span><input name="captions_url" type="url" value="${attr(item.captions_url || '')}"></label><label><span>Poster time (HH:MM:SS)</span><input name="poster_time" value="${attr(msToTime(item.poster_time_ms || 0))}"></label></div><label><span>Visual description / accessibility note</span><textarea name="accessibility_text" rows="3">${esc(item.accessibility_text || '')}</textarea></label></fieldset>
      <div class="sc-library-multimedia__grid"><label><span>Visibility</span><select name="visibility"><option value="private" ${item.visibility !== 'shared' && item.visibility !== 'public' ? 'selected' : ''}>Private</option><option value="shared" ${item.visibility === 'shared' ? 'selected' : ''}>Shared</option><option value="public" ${item.visibility === 'public' ? 'selected' : ''}>Public</option></select></label></div>
      <div class="sc-library-multimedia__actions"><button class="is-primary" type="submit">${item.asset_uuid ? 'Update asset' : 'Save asset'}</button>${item.asset_uuid ? '<button type="button" data-cancel-form>Cancel</button>' : ''}</div>
    </form>`;

    const assetsView = () => `<div class="sc-library-multimedia__layout"><div>${assetForm()}</div><div><div class="sc-library-multimedia__section-heading"><h2>Registered media</h2><p>Original media is never changed by clip definitions.</p></div>${state.assets.length ? state.assets.map(assetCard).join('') : '<p class="sc-library-multimedia__empty">No media assets have been registered.</p>'}</div></div>`;

    const clipCard = (item) => `<article class="sc-library-media-card sc-library-media-card--clip"><div class="sc-library-media-card__preview">${item.poster_url ? `<img src="${attr(item.poster_url)}" alt="">` : '✂'}</div><div><div class="sc-library-media-card__title"><h3>${esc(item.title)}</h3>${statusBadge(item.status)}</div><p>${esc(item.asset_title)}</p><p><strong>${msToTime(item.start_ms)}–${msToTime(item.end_ms)}</strong> · ${Math.round((item.end_ms-item.start_ms)/1000)} seconds</p>${item.transcript_excerpt ? `<blockquote>${esc(item.transcript_excerpt)}</blockquote>` : ''}<div class="sc-library-multimedia__actions"><button type="button" data-edit-clip="${attr(item.clip_uuid)}">Edit</button>${shared.configured && item.status !== 'processing' ? `<button class="is-primary" type="button" data-process-clip="${attr(item.clip_uuid)}">Create snippet</button>` : ''}${item.output_url ? `<a href="${attr(item.output_url)}" target="_blank" rel="noopener">Open rendered clip</a>` : ''}<button class="is-danger" type="button" data-delete-clip="${attr(item.clip_uuid)}">Delete</button></div></div></article>`;

    const assetSelect = (selected) => `<select required name="asset_uuid"><option value="">Select media asset</option>${state.assets.map((item) => `<option value="${attr(item.asset_uuid)}" ${item.asset_uuid === selected ? 'selected' : ''}>${esc(item.title)}</option>`).join('')}</select>`;
    const clipForm = (item = {}) => `<form class="sc-library-multimedia__form" data-clip-form data-uuid="${attr(item.clip_uuid || '')}"><h3>${item.clip_uuid ? 'Edit clip definition' : 'Define a non-destructive clip'}</h3><label><span>Media asset</span>${assetSelect(item.asset_uuid || '')}</label><label><span>Clip title</span><input required name="title" value="${attr(item.title || '')}"></label><label><span>Description</span><textarea name="description" rows="2">${esc(item.description || '')}</textarea></label><div class="sc-library-multimedia__grid sc-library-multimedia__grid--three"><label><span>Start</span><input required name="start" value="${attr(msToTime(item.start_ms || 0))}"></label><label><span>End</span><input required name="end" value="${attr(msToTime(item.end_ms || 0))}"></label><label><span>Poster frame</span><input name="poster" value="${attr(msToTime(item.poster_time_ms || item.start_ms || 0))}"></label></div><label><span>Transcript excerpt</span><textarea name="transcript_excerpt" rows="5">${esc(item.transcript_excerpt || '')}</textarea></label><label><span>Caption text</span><textarea name="caption_text" rows="3">${esc(item.caption_text || '')}</textarea></label><label><span>Annotations (JSON array: time_ms, label, note)</span><textarea name="annotations" rows="5" class="code">${esc(JSON.stringify(item.annotations || [], null, 2))}</textarea></label><div class="sc-library-multimedia__grid"><label><span>Status</span><select name="status">${['draft','ready','archived'].map((value) => `<option value="${value}" ${item.status === value ? 'selected' : ''}>${value}</option>`).join('')}</select></label><label><span>Visibility</span><select name="visibility">${['private','shared','public'].map((value) => `<option value="${value}" ${item.visibility === value ? 'selected' : ''}>${value}</option>`).join('')}</select></label></div><div class="sc-library-multimedia__actions"><button class="is-primary" type="submit">${item.clip_uuid ? 'Update clip' : 'Save clip definition'}</button>${item.clip_uuid ? '<button type="button" data-cancel-form>Cancel</button>' : ''}</div></form>`;
    const clipsView = () => `<div class="sc-library-multimedia__layout"><div>${state.assets.length ? clipForm() : '<div class="sc-library-multimedia__notice">Register a media asset before creating clips.</div>'}</div><div><div class="sc-library-multimedia__section-heading"><h2>Clip definitions</h2><p>Start/end selections remain metadata until a snippet job is explicitly created.</p></div>${state.clips.length ? state.clips.map(clipCard).join('') : '<p class="sc-library-multimedia__empty">No clip definitions yet.</p>'}</div></div>`;

    const clipChecks = (selected = []) => state.clips.map((item) => `<label class="sc-library-multimedia__check"><input type="checkbox" name="clip_uuids" value="${attr(item.clip_uuid)}" ${selected.includes(item.clip_uuid) ? 'checked' : ''}><span><strong>${esc(item.title)}</strong><small>${esc(item.asset_title)} · ${msToTime(item.start_ms)}–${msToTime(item.end_ms)}</small></span></label>`).join('');
    const reelForm = (item = {}) => `<form class="sc-library-multimedia__form" data-reel-form data-uuid="${attr(item.reel_uuid || '')}"><h3>${item.reel_uuid ? 'Edit evidence reel' : 'Assemble an evidence reel'}</h3><label><span>Title</span><input required name="title" value="${attr(item.title || '')}"></label><label><span>Description</span><textarea name="description" rows="3">${esc(item.description || '')}</textarea></label><fieldset><legend>Ordered clips</legend><div class="sc-library-multimedia__checks">${clipChecks(item.clip_uuids || []) || '<p>No clips available.</p>'}</div></fieldset><div class="sc-library-multimedia__grid"><label><span>Visibility</span><select name="visibility">${['private','shared','public'].map((value) => `<option value="${value}" ${item.visibility === value ? 'selected' : ''}>${value}</option>`).join('')}</select></label><label><span>Edition treatment</span><select name="edition_mode"><option value="linked" ${item.edition_mode !== 'embedded' ? 'selected' : ''}>Linked clips with PDF fallback</option><option value="embedded" ${item.edition_mode === 'embedded' ? 'selected' : ''}>Embedded where supported</option></select></label></div><div class="sc-library-multimedia__actions"><button class="is-primary" type="submit">${item.reel_uuid ? 'Update reel' : 'Save reel'}</button>${item.reel_uuid ? '<button type="button" data-cancel-form>Cancel</button>' : ''}</div></form>`;
    const reelCard = (item) => `<article class="sc-library-media-card sc-library-media-card--reel"><div class="sc-library-media-card__preview">▤</div><div><div class="sc-library-media-card__title"><h3>${esc(item.title)}</h3>${statusBadge(item.visibility)}</div><p>${esc(item.description || 'No description')}</p><p>${item.clip_uuids.length} clip${item.clip_uuids.length === 1 ? '' : 's'}</p><code>${esc(item.shortcode)}</code><div class="sc-library-multimedia__actions"><button type="button" data-edit-reel="${attr(item.reel_uuid)}">Edit</button><button type="button" data-copy="${attr(item.shortcode)}">Copy shortcode</button><button class="is-danger" type="button" data-delete-reel="${attr(item.reel_uuid)}">Delete</button></div></div></article>`;
    const reelsView = () => `<div class="sc-library-multimedia__layout"><div>${reelForm()}</div><div><div class="sc-library-multimedia__section-heading"><h2>Evidence reels</h2><p>Reels preserve clip order, source links, transcript excerpts, and rights metadata.</p></div>${state.reels.length ? state.reels.map(reelCard).join('') : '<p class="sc-library-multimedia__empty">No evidence reels yet.</p>'}</div></div>`;

    const jobsView = () => `<div class="sc-library-multimedia__section"><div class="sc-library-multimedia__section-heading"><h2>Processing jobs</h2><p>Render processing is optional and never changes the original media asset.</p></div>${shared.configured ? '' : '<div class="sc-library-multimedia__notice">The optional media processing service is not configured.</div>'}<div class="sc-library-multimedia__jobs">${state.jobs.length ? state.jobs.map((job) => `<article><div><strong>${esc(job.clip_uuid)}</strong>${statusBadge(job.status)}</div><progress max="100" value="${Number(job.progress || 0)}"></progress><dl><div><dt>Progress</dt><dd>${Number(job.progress || 0)}%</dd></div><div><dt>Output</dt><dd>${fmtBytes(job.output_bytes)}</dd></div><div><dt>Updated</dt><dd>${date(job.updated_at)}</dd></div></dl>${job.error ? `<p class="sc-library-multimedia__error">${esc(job.error)}</p>` : ''}<div class="sc-library-multimedia__actions">${['queued','processing','submitting'].includes(job.status) ? `<button type="button" data-refresh-job="${attr(job.job_uuid)}">Refresh</button>` : ''}${job.status === 'error' && Number(job.attempt || 0) < Number(job.max_attempts || 3) ? `<button type="button" data-retry-job="${attr(job.job_uuid)}">Retry</button>` : ''}${job.output_url ? `<a href="${attr(job.output_url)}" target="_blank" rel="noopener">Open output</a>` : ''}</div></article>`).join('') : '<p class="sc-library-multimedia__empty">No processing jobs.</p>'}</div></div>`;

    const render = () => {
      if (!shared.canEdit) { root.innerHTML = '<div class="sc-library-multimedia__notice">Sign in with editorial access to use the Multimedia Studio.</div>'; return; }
      root.innerHTML = `${tabs()}${state.error ? `<div class="sc-library-multimedia__error" role="alert">${esc(state.error)}</div>` : ''}${state.notice ? `<div class="sc-library-multimedia__success" role="status">${esc(state.notice)}</div>` : ''}${state.loading ? '<p class="sc-library-multimedia__loading">Loading Multimedia Studio…</p>' : (state.tab === 'assets' ? assetsView() : state.tab === 'clips' ? clipsView() : state.tab === 'reels' ? reelsView() : jobsView())}`;
      bind();
    };

    const replaceForm = (selector, html) => {
      const form = root.querySelector(selector); if (!form) return;
      form.outerHTML = html; bind();
    };

    const bind = () => {
      root.querySelectorAll('[data-media-tab]').forEach((button) => button.addEventListener('click', () => { state.tab = button.dataset.mediaTab; state.notice = ''; state.error = ''; render(); }));
      root.querySelectorAll('[data-cancel-form]').forEach((button) => button.addEventListener('click', () => render()));

      const assetFormNode = root.querySelector('[data-asset-form]');
      if (assetFormNode) {
        const picker = assetFormNode.querySelector('[data-media-picker]');
        if (picker) picker.addEventListener('click', () => {
          if (!window.wp?.media) { setNotice('WordPress Media Library picker is unavailable on this page.', true); return; }
          const frame = window.wp.media({title:'Select video or audio', library:{type:['video','audio']}, multiple:false});
          frame.on('select', () => {
            const item = frame.state().get('selection').first().toJSON();
            assetFormNode.elements.attachment_id.value = item.id || '';
            assetFormNode.elements.source_url.value = item.url || '';
            if (!assetFormNode.elements.title.value) assetFormNode.elements.title.value = item.title || item.filename || '';
            assetFormNode.elements.media_type.value = String(item.mime || '').startsWith('audio/') ? 'audio' : 'video';
            if (item.meta?.length_formatted) assetFormNode.elements.duration.value = item.meta.length_formatted;
          });
          frame.open();
        });
        assetFormNode.addEventListener('submit', async (event) => {
          event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); let response;
          const body = {title:data.get('title'), media_type:data.get('media_type'), description:data.get('description'), attachment_id:Number(data.get('attachment_id') || 0), source_url:data.get('source_url'), source_kind:Number(data.get('attachment_id') || 0) ? 'attachment' : 'remote', duration_ms:timeToMs(data.get('duration')), rights_status:data.get('rights_status'), rights_holder:data.get('rights_holder'), license_name:data.get('license_name'), license_url:data.get('license_url'), rights_note:data.get('rights_note'), source_citation:data.get('source_citation'), transcript_text:data.get('transcript_text'), transcript_vtt:data.get('transcript_vtt'), captions_url:data.get('captions_url'), poster_time_ms:timeToMs(data.get('poster_time')), accessibility_text:data.get('accessibility_text'), visibility:data.get('visibility')};
          try { response = await api(form.dataset.uuid ? `/assets/${form.dataset.uuid}` : '/assets', {method:form.dataset.uuid ? 'PUT' : 'POST', body:JSON.stringify(body)}); setNotice(shared.strings.saved || 'Saved.'); await load(); }
          catch (error) { setNotice(error.message, true); }
        });
      }
      root.querySelectorAll('[data-edit-asset]').forEach((button) => button.addEventListener('click', () => { const item = state.assets.find((row) => row.asset_uuid === button.dataset.editAsset); replaceForm('[data-asset-form]', assetForm(item)); root.querySelector('[data-asset-form]')?.scrollIntoView({behavior:'smooth'}); }));
      root.querySelectorAll('[data-delete-asset]').forEach((button) => button.addEventListener('click', async () => { if (!confirm(shared.strings.confirmDelete || 'Delete?')) return; try { await api(`/assets/${button.dataset.deleteAsset}`, {method:'DELETE'}); await load(); } catch (error) { setNotice(error.message, true); } }));

      const clipFormNode = root.querySelector('[data-clip-form]');
      if (clipFormNode) clipFormNode.addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); let annotations = []; try { annotations = JSON.parse(String(data.get('annotations') || '[]')); } catch (error) { setNotice('Annotations must be valid JSON.', true); return; } const body = {asset_uuid:data.get('asset_uuid'), title:data.get('title'), description:data.get('description'), start_ms:timeToMs(data.get('start')), end_ms:timeToMs(data.get('end')), poster_time_ms:timeToMs(data.get('poster')), transcript_excerpt:data.get('transcript_excerpt'), caption_text:data.get('caption_text'), annotations, status:data.get('status'), visibility:data.get('visibility')}; try { await api(form.dataset.uuid ? `/clips/${form.dataset.uuid}` : '/clips', {method:form.dataset.uuid ? 'PUT' : 'POST', body:JSON.stringify(body)}); await load(); } catch (error) { setNotice(error.message, true); } });
      root.querySelectorAll('[data-edit-clip]').forEach((button) => button.addEventListener('click', () => { const item = state.clips.find((row) => row.clip_uuid === button.dataset.editClip); replaceForm('[data-clip-form]', clipForm(item)); root.querySelector('[data-clip-form]')?.scrollIntoView({behavior:'smooth'}); }));
      root.querySelectorAll('[data-delete-clip]').forEach((button) => button.addEventListener('click', async () => { if (!confirm(shared.strings.confirmDelete || 'Delete?')) return; try { await api(`/clips/${button.dataset.deleteClip}`, {method:'DELETE'}); await load(); } catch (error) { setNotice(error.message, true); } }));
      root.querySelectorAll('[data-process-clip]').forEach((button) => button.addEventListener('click', async () => { try { await api(`/clips/${button.dataset.processClip}/process`, {method:'POST', body:JSON.stringify({burn_captions:false})}); state.tab = 'jobs'; await load(); } catch (error) { setNotice(error.message, true); } }));

      const reelFormNode = root.querySelector('[data-reel-form]');
      if (reelFormNode) reelFormNode.addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); const body = {title:data.get('title'), description:data.get('description'), clip_uuids:data.getAll('clip_uuids'), visibility:data.get('visibility'), edition_mode:data.get('edition_mode')}; try { await api(form.dataset.uuid ? `/reels/${form.dataset.uuid}` : '/reels', {method:form.dataset.uuid ? 'PUT' : 'POST', body:JSON.stringify(body)}); await load(); } catch (error) { setNotice(error.message, true); } });
      root.querySelectorAll('[data-edit-reel]').forEach((button) => button.addEventListener('click', () => { const item = state.reels.find((row) => row.reel_uuid === button.dataset.editReel); replaceForm('[data-reel-form]', reelForm(item)); root.querySelector('[data-reel-form]')?.scrollIntoView({behavior:'smooth'}); }));
      root.querySelectorAll('[data-delete-reel]').forEach((button) => button.addEventListener('click', async () => { if (!confirm(shared.strings.confirmDelete || 'Delete?')) return; try { await api(`/reels/${button.dataset.deleteReel}`, {method:'DELETE'}); await load(); } catch (error) { setNotice(error.message, true); } }));
      root.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => { await navigator.clipboard.writeText(button.dataset.copy || ''); setNotice('Shortcode copied.'); }));
      root.querySelectorAll('[data-refresh-job]').forEach((button) => button.addEventListener('click', async () => { try { await api(`/jobs/${button.dataset.refreshJob}/refresh`, {method:'POST', body:'{}'}); await load(); } catch (error) { setNotice(error.message, true); } }));
      root.querySelectorAll('[data-retry-job]').forEach((button) => button.addEventListener('click', async () => { try { await api(`/jobs/${button.dataset.retryJob}/retry`, {method:'POST', body:'{}'}); await load(); } catch (error) { setNotice(error.message, true); } }));
    };

    load();
  });
})();
