(function(){'use strict';
  var cfg=window.SCPublicLibraryNetwork||{};
  function connect(button){
    if(!cfg.signedIn||!cfg.ajaxUrl){return;}
    var item=button.closest('[data-library-id]'); if(!item){return;}
    var status=button.parentNode.querySelector('[aria-live="polite"]');
    var body=new URLSearchParams(); body.set('action','sc_library_v4326_connect_public_library'); body.set('nonce',cfg.nonce||''); body.set('library_id',button.getAttribute('data-sc-connect-public-library')||''); body.set('relation',button.getAttribute('data-relation')||'member');
    button.disabled=true; if(status){status.textContent='Connecting…';}
    fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json();}).then(function(payload){
      if(!payload||!payload.success){throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Unable to connect library.');}
      item.querySelectorAll('[data-sc-connect-public-library]').forEach(function(b){b.disabled=true;});
      if(status){status.textContent=payload.data.message||'Library connected.';}
      item.classList.add('is-connected');
    }).catch(function(err){button.disabled=false;if(status){status.textContent=err.message||'Unable to connect library.';}});
  }
  document.addEventListener('click',function(event){var button=event.target.closest('[data-sc-connect-public-library]'); if(!button){return;} event.preventDefault(); connect(button);});
})();
