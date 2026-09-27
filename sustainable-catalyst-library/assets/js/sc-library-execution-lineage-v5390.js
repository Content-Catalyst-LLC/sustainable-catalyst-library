(() => {
  const renderCards=(root,data)=>{
    const box=root.querySelector('[data-sc-lineage-cards]'); if(!box)return; box.innerHTML='';
    const env=data?.execution_environment||{};
    (env.runtimes||[]).forEach(r=>{const el=document.createElement('article'); el.className='sc-execution-lineage__card'; el.innerHTML=`<strong>${r.engine||'runtime'}</strong><span>${r.runtime_version||'unknown'}</span><em>${r.available?'Available':'Unavailable'}</em><small>${r.transport||''}</small>`; box.appendChild(el);});
  };
  document.querySelectorAll('[data-sc-execution-lineage-root]').forEach(root=>{
    const statusEl=root.querySelector('[data-sc-lineage-status]'), out=root.querySelector('[data-sc-lineage-output]');
    const load=async()=>{statusEl.textContent='Loading reproducibility contract…'; try{const res=await fetch(root.dataset.statusEndpoint,{credentials:'same-origin'}); const data=await res.json(); if(!res.ok)throw new Error(data?.message||data?.error||'Status failed.'); renderCards(root,data); const fp=data?.execution_environment?.environment_fingerprint_sha256||''; statusEl.textContent=`Backend ${data.backend_version||''} · environment ${fp.slice(0,12)}…`; out.textContent=JSON.stringify(data,null,2);}catch(err){statusEl.textContent='Reproducibility status unavailable.'; out.textContent=String(err);}};
    root.querySelector('[data-sc-lineage-refresh]')?.addEventListener('click',load); load();
  });
})();
