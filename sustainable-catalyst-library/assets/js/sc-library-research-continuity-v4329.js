(function(){
  'use strict';
  const cfg=window.SCLibraryResearchContinuity||{};
  const qa=(root,sel)=>Array.from(root.querySelectorAll(sel));
  const status=(node,msg)=>{if(node) node.textContent=msg||'';};
  const send=async(action,form,extra)=>{
    const body=new FormData(form||undefined);
    body.set('action',action); body.set('nonce',cfg.nonce||'');
    Object.entries(extra||{}).forEach(([k,v])=>body.set(k,v));
    const response=await fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',body});
    let payload={}; try{payload=await response.json();}catch(e){throw new Error('The Library returned an unreadable response.');}
    if(!response.ok||!payload.success) throw new Error((payload.data&&payload.data.message)||'The saved-research request failed.');
    return payload.data||{};
  };
  const bindForm=(root,selector,action)=>qa(root,selector).forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault(); const live=form.querySelector('[aria-live]'); status(live,'Saving…');
    try{const data=await send(action,form); status(live,data.message||'Saved.'); window.setTimeout(()=>window.location.reload(),220);}catch(err){status(live,err.message);}
  }));
  const bindButtons=(root,selector,action,confirmText)=>qa(root,selector).forEach(button=>button.addEventListener('click',async()=>{
    if(confirmText&&!window.confirm(confirmText)) return;
    button.disabled=true;
    try{await send(action,null,{record_id:button.getAttribute(selector.replace('[','').replace(']','').split('=')[0])||button.dataset.scContinuityDeleteSearch||button.dataset.scContinuityDeleteWatch||button.dataset.scContinuityDeleteQueue||button.dataset.scContinuityReviewWatch||''}); window.location.reload();}
    catch(err){window.alert(err.message); button.disabled=false;}
  }));
  const boot=root=>{
    bindForm(root,'[data-sc-continuity-search-form]','sc_library_v4329_save_search');
    bindForm(root,'[data-sc-continuity-watch-form]','sc_library_v4329_add_watch');
    bindForm(root,'[data-sc-continuity-queue-form]','sc_library_v4329_add_queue_item');
    bindForm(root,'[data-sc-continuity-update-queue]','sc_library_v4329_update_queue_item');
    qa(root,'[data-sc-continuity-delete-search]').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Remove this saved search?'))return;try{await send('sc_library_v4329_delete_search',null,{record_id:b.dataset.scContinuityDeleteSearch});location.reload();}catch(e){alert(e.message);}}));
    qa(root,'[data-sc-continuity-delete-watch]').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Remove this watchlist item?'))return;try{await send('sc_library_v4329_delete_watch',null,{record_id:b.dataset.scContinuityDeleteWatch});location.reload();}catch(e){alert(e.message);}}));
    qa(root,'[data-sc-continuity-review-watch]').forEach(b=>b.addEventListener('click',async()=>{try{await send('sc_library_v4329_mark_watch_reviewed',null,{record_id:b.dataset.scContinuityReviewWatch});location.reload();}catch(e){alert(e.message);}}));
    qa(root,'[data-sc-continuity-delete-queue]').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Remove this research queue item?'))return;try{await send('sc_library_v4329_delete_queue_item',null,{record_id:b.dataset.scContinuityDeleteQueue});location.reload();}catch(e){alert(e.message);}}));
  };
  document.addEventListener('DOMContentLoaded',()=>qa(document,'[data-sc-research-continuity]').forEach(boot));
})();
