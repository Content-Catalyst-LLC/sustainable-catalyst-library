(function(){
  'use strict';
  const cfg=window.SCLibraryPersonalLibrary||{};
  const q=(root,sel)=>root.querySelector(sel);
  const qa=(root,sel)=>Array.from(root.querySelectorAll(sel));
  const text=(node,value)=>{ if(node) node.textContent=value||''; };
  const send=async(action,form)=>{
    const body=new FormData(form||undefined);
    body.set('action',action);
    body.set('nonce',cfg.nonce||'');
    const response=await fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',body});
    let data={};
    try{ data=await response.json(); }catch(e){ throw new Error('The Library returned an unreadable response.'); }
    if(!response.ok||!data.success){ throw new Error((data.data&&data.data.message)||'The personal Library request failed.'); }
    return data.data||{};
  };
  const applyFilters=(root)=>{
    const search=(q(root,'[data-sc-personal-search]')?.value||'').trim().toLowerCase();
    const type=q(root,'[data-sc-personal-type-filter]')?.value||'';
    const relationship=q(root,'[data-sc-personal-relationship-filter]')?.value||'';
    const collection=q(root,'[data-sc-personal-collection-filter]')?.value||'';
    qa(root,'[data-sc-personal-item]').forEach(item=>{
      const okSearch=!search||(item.dataset.search||'').includes(search);
      const okType=!type||item.dataset.type===type;
      const okRelationship=!relationship||item.dataset.relationship===relationship;
      const okCollection=!collection||item.dataset.collection===collection;
      item.hidden=!(okSearch&&okType&&okRelationship&&okCollection);
    });
  };
  const boot=(root)=>{
    qa(root,'[data-sc-personal-search],[data-sc-personal-type-filter],[data-sc-personal-relationship-filter],[data-sc-personal-collection-filter]').forEach(control=>{
      control.addEventListener(control.tagName==='INPUT'?'input':'change',()=>applyFilters(root));
    });

    const addForm=q(root,'[data-sc-personal-add-form]');
    if(addForm){
      addForm.addEventListener('submit',async(event)=>{
        event.preventDefault();
        const status=q(root,'[data-sc-personal-add-status]');
        text(status,'Saving…');
        try{
          const data=await send('sc_library_v4328_add_item',addForm);
          text(status,data.message||'Saved.');
          window.setTimeout(()=>window.location.reload(),250);
        }catch(error){ text(status,error.message); }
      });
    }

    const collectionForm=q(root,'[data-sc-personal-collection-form]');
    if(collectionForm){
      collectionForm.addEventListener('submit',async(event)=>{
        event.preventDefault();
        const status=collectionForm.querySelector('[aria-live]');
        text(status,'Creating…');
        try{
          const data=await send('sc_library_v4328_create_collection',collectionForm);
          text(status,data.message||'Created.');
          window.setTimeout(()=>window.location.reload(),250);
        }catch(error){ text(status,error.message); }
      });
    }

    qa(root,'[data-sc-personal-update-form]').forEach(form=>{
      form.addEventListener('submit',async(event)=>{
        event.preventDefault();
        const status=form.querySelector('[aria-live]');
        text(status,'Saving…');
        try{
          const data=await send('sc_library_v4328_update_item',form);
          text(status,data.message||'Updated.');
          window.setTimeout(()=>window.location.reload(),250);
        }catch(error){ text(status,error.message); }
      });
    });

    qa(root,'[data-sc-personal-delete]').forEach(button=>{
      button.addEventListener('click',async()=>{
        if(!window.confirm('Remove this item from your private Library?')) return;
        const form=button.closest('form')||document.createElement('form');
        let input=form.querySelector('input[name="item_id"]');
        if(!input){ input=document.createElement('input'); input.name='item_id'; input.value=button.dataset.scPersonalDelete||''; form.appendChild(input); }
        const status=form.querySelector('[aria-live]');
        text(status,'Removing…');
        try{
          const data=await send('sc_library_v4328_delete_item',form);
          text(status,data.message||'Removed.');
          const item=button.closest('[data-sc-personal-item]');
          if(item) item.remove();
        }catch(error){ text(status,error.message); }
      });
    });
  };
  document.addEventListener('DOMContentLoaded',()=>qa(document,'[data-sc-personal-library]').forEach(boot));
})();
