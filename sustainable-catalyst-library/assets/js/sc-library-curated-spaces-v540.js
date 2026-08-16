(()=>{
'use strict';
const q=(s,r=document)=>r.querySelector(s), qa=(s,r=document)=>Array.from(r.querySelectorAll(s));
const clean=(v)=>String(v??'');
function renumber(root){
  qa('[data-sc-curated-section]',root).forEach((section,si)=>{
    const idx=q('[data-sc-section-index]',section); if(idx) idx.value=String(si);
    qa('[name]',section).forEach(el=>{el.name=el.name.replace(/sc_curated_sections\[\d+\]/,`sc_curated_sections[${si}]`);});
    qa('[data-sc-curated-item]',section).forEach((item,ii)=>qa('[name]',item).forEach(el=>{el.name=el.name.replace(/\[items\]\[\d+\]/,`[items][${ii}]`);}));
  });
}
function initAdmin(root){
  const sections=q('[data-sc-curated-sections]',root), st=q('[data-sc-curated-section-template]',root), it=q('[data-sc-curated-item-template]',root); if(!sections||!st||!it)return;
  root.addEventListener('click',e=>{
    const addSection=e.target.closest('[data-sc-curated-add-section]');
    const removeSection=e.target.closest('[data-sc-curated-remove-section]');
    const addItem=e.target.closest('[data-sc-curated-add-item]');
    const removeItem=e.target.closest('[data-sc-curated-remove-item]');
    if(addSection){e.preventDefault(); if(qa('[data-sc-curated-section]',sections).length>=24)return; const si=qa('[data-sc-curated-section]',sections).length; const wrap=document.createElement('div'); wrap.innerHTML=st.innerHTML.replaceAll('__S__',String(si)); const section=wrap.firstElementChild; sections.append(section); renumber(root); return;}
    if(removeSection){e.preventDefault(); const section=removeSection.closest('[data-sc-curated-section]'); if(section)section.remove(); renumber(root); return;}
    if(addItem){e.preventDefault(); const section=addItem.closest('[data-sc-curated-section]'); const items=q('[data-sc-curated-items]',section); if(!items||qa('[data-sc-curated-item]',items).length>=40)return; const si=qa('[data-sc-curated-section]',sections).indexOf(section), ii=qa('[data-sc-curated-item]',items).length; const wrap=document.createElement('div'); wrap.innerHTML=it.innerHTML.replaceAll('__S__',String(si)).replaceAll('__I__',String(ii)); items.append(wrap.firstElementChild); renumber(root); return;}
    if(removeItem){e.preventDefault(); const item=removeItem.closest('[data-sc-curated-item]'); if(item)item.remove(); renumber(root);}
  });
  renumber(root);
}
function el(tag,cls,text){const n=document.createElement(tag); if(cls)n.className=cls; if(text!==undefined)n.textContent=clean(text); return n;}
function renderSpace(space){
  const art=el('article','sc-curated-space'); art.dataset.scCuratedSpaceId=clean(space.id);
  const head=el('header'); head.append(el('p','',clean(space.kind_label).toUpperCase()),el('h4','',space.title)); if(space.subtitle)head.append(el('span','',space.subtitle)); head.append(el('small','',`Curated by ${clean(space.curator?.display_name)||'Sustainable Catalyst'} · ${Number(space.reference_count||0)} public references`)); art.append(head);
  if(space.summary)art.append(el('p','sc-curated-space__summary',space.summary));
  if(space.curator_note){const a=el('aside'); a.append(el('strong','', 'Curator note'),el('p','',space.curator_note)); art.append(a);}
  const sections=el('div','sc-curated-space__sections'); (space.sections||[]).forEach(section=>{const s=el('section'); s.append(el('h5','',section.title)); if(section.narrative)s.append(el('p','',section.narrative)); const ol=el('ol'); (section.items||[]).forEach(item=>{const li=el('li'),d=el('div'); d.append(el('small','',item.type),el('strong','',item.curator_label||item.title)); if(item.curator_note||item.summary)d.append(el('span','',item.curator_note||item.summary)); if(item.canonical_id)d.append(el('code','',item.canonical_id)); li.append(d); if(item.url){const a=el('a','', 'Open'); a.href=item.url; li.append(a);} ol.append(li);}); s.append(ol); sections.append(s);}); art.append(sections);
  const foot=el('footer'); foot.append(el('span','', 'References only · underlying records retain their own ownership, publication state, provenance, and access rules.'),el('code','',space.manifest_sha256)); art.append(foot); return art;
}
function initPublic(root){
  const base=root.dataset.apiBase, detail=q('[data-sc-curated-detail]',root); if(!base||!detail)return;
  root.addEventListener('click',async e=>{const a=e.target.closest('[data-sc-curated-open]'); if(!a)return; e.preventDefault(); const id=a.dataset.scCuratedOpen; if(!/^\d+$/.test(id))return; const controller=new AbortController(), timer=setTimeout(()=>controller.abort(),12000); detail.setAttribute('aria-busy','true'); detail.textContent='Loading curated space…'; try{const res=await fetch(`${base}/${id}`,{credentials:'omit',headers:{Accept:'application/json'},signal:controller.signal}); if(!res.ok)throw new Error(String(res.status)); const data=await res.json(); detail.replaceChildren(renderSpace(data)); history.replaceState(null,'',a.href);}catch(err){detail.textContent='This curated public space is temporarily unavailable.';}finally{clearTimeout(timer); detail.removeAttribute('aria-busy');}});
}
qa('[data-sc-curated-section-builder]').forEach(initAdmin); qa('[data-sc-curated-spaces]').forEach(initPublic);
})();
