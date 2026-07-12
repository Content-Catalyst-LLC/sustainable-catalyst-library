(() => {
  'use strict';
  const cfg = window.SCLibraryPlanner || {};
  const base = String(cfg.restBase || '').replace(/\/$/, '');
  const strings = cfg.strings || {};
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const api = async (path, params = {}) => {
    const url = new URL(`${base}/${path}`);
    Object.entries(params).forEach(([k,v]) => {
      if (typeof v === 'boolean') { url.searchParams.set(k, v ? '1' : '0'); return; }
      if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, String(v));
    });
    const res = await fetch(url.toString(), {headers:{Accept:'application/json'}});
    if (!res.ok) throw new Error(`Request failed: ${res.status}`);
    return res.json();
  };
  const metricLabels = {
    published:'Published', current_documentation:'Current docs', in_development:'In development',
    planned:'Planned', scheduled:'Scheduled', superseded:'Superseded', archived:'Archived', total:'Total records'
  };
  const renderMetrics = (target, summary = {}) => {
    if (!target) return;
    target.innerHTML = Object.entries(metricLabels).map(([key,label]) => `<div class="${target.classList.contains('sc-library-roadmap-tracker__summary') ? 'sc-library-roadmap-tracker__metric' : 'sc-library-registry__metric'}"><span>${esc(label)}</span><strong>${Number(summary[key] || 0)}</strong></div>`).join('');
  };
  const badgeClass = (state) => {
    if (['planned','proposed','idea','deferred'].includes(state)) return 'plan';
    if (['researching','drafting','review','scheduled'].includes(state)) return 'development';
    if (['archived','superseded','cancelled','pdf_snapshot'].includes(state)) return 'historical';
    return 'published';
  };
  const optionHtml = (items, first) => `<option value="">${esc(first)}</option>` + (items || []).map(i => `<option value="${esc(i.value)}">${esc(i.label)} (${Number(i.count || 0)})</option>`).join('');
  const csvCell = (v) => `"${String(v ?? '').replace(/"/g,'""')}"`;
  const download = (name, content, type) => {
    const blob = new Blob([content], {type});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  };

  document.querySelectorAll('[data-sc-registry]').forEach((root) => {
    const form = root.querySelector('[data-registry-form]');
    const results = root.querySelector('[data-registry-results]');
    const status = root.querySelector('[data-registry-status]');
    const pagination = root.querySelector('[data-registry-pagination]');
    const summary = root.querySelector('[data-registry-summary]');
    const stateSelect = root.querySelector('[data-registry-state]');
    const typeSelect = root.querySelector('[data-registry-type]');
    const areaSelect = root.querySelector('[data-registry-area]');
    const productSelect = root.querySelector('[data-registry-product]');
    const state = {search:'',state:'',type:'',area:'',product:'',collection:root.dataset.collection || '',include_archived:true,sort:'updated',page:1,per_page:Number(root.dataset.perPage || 20)};
    let lastItems = [];

    const renderRecords = (items) => {
      lastItems = items || [];
      if (!results) return;
      if (!lastItems.length) { results.innerHTML = `<p>${esc(strings.empty || 'No records found.')}</p>`; return; }
      results.innerHTML = lastItems.map((r) => {
        const release = r.expected_release?.display ? `<p class="sc-library-registry-record__release">${esc(r.expected_release.display)}</p>` : '';
        const map = r.article_map_title ? ` · ${esc(r.article_map_title)}${Number(r.series_order)>0 ? ` #${esc(r.series_order)}` : ''}` : '';
        const authority = r.authority_label ? ` · Authority: ${esc(r.authority_label)}` : '';
        const notice = r.notice ? `<p class="sc-library-registry-record__notice">${esc(r.notice)}</p>` : '';
        const primaryUrl = r.published_url || r.url || '#';
        const primaryLabel = r.kind === 'plan' ? 'View plan' : (r.kind === 'document' ? 'Open record' : 'Read');
        return `<article class="sc-library-registry-record">
          <div>
            <div class="sc-library-registry-record__badges"><span class="sc-library-registry-record__badge sc-library-registry-record__badge--${badgeClass(r.record_state)}">${esc(r.record_state_label)}</span><span class="sc-library-registry-record__badge">${esc(r.content_type_label)}</span></div>
            <h3><a href="${esc(primaryUrl)}">${esc(r.title)}</a></h3>
            <p class="sc-library-registry-record__meta">${esc(r.area || 'Unassigned area')}${r.product ? ` · ${esc(r.product)}` : ''}${map}${authority}</p>
            <p>${esc(r.excerpt || '')}</p>${release}${notice}
          </div>
          <div class="sc-library-registry-record__actions"><a href="${esc(primaryUrl)}">${esc(primaryLabel)}</a>${r.article_map_url ? `<a href="${esc(r.article_map_url)}">Explore article map</a>` : ''}</div>
        </article>`;
      }).join('');
    };
    const renderPages = (p = {}) => {
      if (!pagination) return;
      const total = Number(p.total_pages || 0), current = Number(p.page || 1);
      if (total < 2) { pagination.innerHTML=''; return; }
      const start = Math.max(1,current-2), end = Math.min(total,current+2); let html='';
      if (current>1) html += `<button type="button" data-page="${current-1}">Previous</button>`;
      for(let i=start;i<=end;i++) html += `<button type="button" data-page="${i}" ${i===current?'aria-current="page"':''}>${i}</button>`;
      if(current<total) html += `<button type="button" data-page="${current+1}">Next</button>`;
      pagination.innerHTML=html;
      pagination.querySelectorAll('[data-page]').forEach(b=>b.addEventListener('click',()=>{state.page=Number(b.dataset.page); load(); root.scrollIntoView({behavior:'smooth',block:'start'});}));
    };
    const load = async () => {
      if (status) status.textContent = strings.loading || 'Loading…';
      try {
        const data = await api('registry', state);
        renderMetrics(summary, data.summary || {}); renderRecords(data.items || []); renderPages(data.pagination || {});
        if (status) status.textContent = `${Number(data.pagination?.total || 0)} public registry records`;
      } catch (e) { if(status) status.textContent = strings.error || 'Could not load registry.'; if(results) results.innerHTML=''; }
    };
    const loadFacets = async () => {
      try {
        const data = await api('registry/facets'); const f=data.facets || {};
        if(stateSelect) stateSelect.innerHTML=optionHtml(f.states,'All states');
        if(typeSelect) typeSelect.innerHTML=optionHtml(f.types,'All record types');
        if(areaSelect) areaSelect.innerHTML=optionHtml(f.areas,'All areas');
        if(productSelect) productSelect.innerHTML=optionHtml(f.products,'All products');
      } catch(e) { /* Registry remains usable without facets. */ }
    };
    form?.addEventListener('submit',(e)=>{e.preventDefault(); const fd=new FormData(form); state.search=String(fd.get('search')||''); state.state=String(fd.get('state')||''); state.type=String(fd.get('type')||''); state.area=String(fd.get('area')||''); state.product=String(fd.get('product')||''); state.sort=String(fd.get('sort')||'updated'); state.include_archived=Boolean(fd.get('include_archived')); state.page=1; load();});
    root.querySelector('[data-registry-reset]')?.addEventListener('click',()=>{form?.reset(); state.search=state.state=state.type=state.area=state.product=''; state.sort='updated'; state.include_archived=true; state.page=1; load();});
    const allFilteredRecords = async () => {
      const first = await api('registry', {...state, page:1, per_page:100});
      const all = [...(first.items || [])];
      const pages = Number(first.pagination?.total_pages || 1);
      for (let page=2; page<=pages; page+=1) {
        const next = await api('registry', {...state, page, per_page:100});
        all.push(...(next.items || []));
      }
      return all;
    };
    root.querySelectorAll('[data-registry-export]').forEach((button)=>button.addEventListener('click',async()=>{
      const format=button.dataset.registryExport;
      const original=button.textContent; button.disabled=true; button.textContent='Preparing…';
      try {
        const exportItems=await allFilteredRecords();
        if(format==='json') download(`sustainable-catalyst-registry-${Date.now()}.json`,JSON.stringify({schema:'sc-library-registry/1.0',exported_at:new Date().toISOString(),records:exportItems},null,2),'application/json');
        else { const header=['ID','Identifier','Title','State','Type','Area','Product','Expected release','Article map','URL']; const rows=exportItems.map(r=>[r.id,r.record_identifier,r.title,r.record_state,r.content_type,r.area,r.product,r.expected_release?.display||'',r.article_map_title||'',r.url]); download(`sustainable-catalyst-registry-${Date.now()}.csv`,[header,...rows].map(row=>row.map(csvCell).join(',')).join('\n'),'text/csv'); }
      } catch(e) { if(status) status.textContent='The registry export could not be prepared.'; }
      finally { button.disabled=false; button.textContent=original; }
    }));
    loadFacets(); load();
  });

  const table = (rows = [], firstLabel='Area') => {
    if(!rows.length) return '<p>No records available.</p>';
    return `<table class="sc-library-roadmap-table"><thead><tr><th>${esc(firstLabel)}</th><th>Published/current</th><th>In development</th><th>Planned</th><th>Historical</th><th>Total</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${esc(r.label)}</td><td>${Number(r.published||0)}</td><td>${Number(r.in_development||0)}</td><td>${Number(r.planned||0)}</td><td>${Number(r.archived||0)}</td><td><strong>${Number(r.total||0)}</strong></td></tr>`).join('')}</tbody></table>`;
  };
  document.querySelectorAll('[data-sc-roadmap-tracker]').forEach(async (root) => {
    const status=root.querySelector('[data-tracker-status]'), summary=root.querySelector('[data-tracker-summary]'), areas=root.querySelector('[data-tracker-areas]'), products=root.querySelector('[data-tracker-products]'), maps=root.querySelector('[data-tracker-maps]');
    if(status) status.textContent='Loading roadmap tracker…';
    try {
      const data=await api('roadmap/tracker',{collection:root.dataset.collection||''}); renderMetrics(summary,data.summary||{});
      if(areas) areas.innerHTML=table(data.by_area||[],'Area'); if(products) products.innerHTML=table(data.by_product||[],'Product');
      if(maps) maps.innerHTML=(data.article_maps||[]).length ? `<table class="sc-library-roadmap-table"><thead><tr><th>Article map</th><th>Published</th><th>In development</th><th>Planned</th><th>Total</th></tr></thead><tbody>${data.article_maps.map(m=>`<tr><td>${m.url?`<a href="${esc(m.url)}">${esc(m.title)}</a>`:esc(m.title)}</td><td>${Number(m.published||0)}</td><td>${Number(m.in_development||0)}</td><td>${Number(m.planned||0)}</td><td><strong>${Number(m.total||0)}</strong></td></tr>`).join('')}</tbody></table>` : '<p>No article-map planning records are public yet.</p>';
      if(status) status.textContent=`${Number(data.summary?.total||0)} public records across the registry.`;
    } catch(e) { if(status) status.textContent='The roadmap tracker could not be loaded.'; }
  });
})();
