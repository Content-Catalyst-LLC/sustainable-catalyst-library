(() => {
  'use strict';
  const NS = 'http://www.w3.org/2000/svg';
  const clamp = (v,a,b)=>Math.max(a,Math.min(b,v));
  const short = (s,n=30)=>String(s||'').length>n?String(s).slice(0,n-1)+'…':String(s||'');

  function parseData(root){
    const el=root.querySelector('.sc-kl__data');
    if(!el) return null;
    try{return JSON.parse(el.textContent||'{}');}catch(e){return null;}
  }
  function svgEl(name, attrs={}){
    const el=document.createElementNS(NS,name);
    Object.entries(attrs).forEach(([k,v])=>el.setAttribute(k,String(v)));
    return el;
  }

  class KnowledgeMap {
    constructor(root){
      this.root=root; this.data=parseData(root)||{}; this.svg=root.querySelector('.sc-kl__svg');
      if(!this.svg) return;
      this.nodes=(this.data.nodes||[]).map((n,i)=>({...n,_i:i,x:0,y:0,vx:0,vy:0,visible:true}));
      this.edges=(this.data.edges||[]).map(e=>({...e,visible:true}));
      this.byId=new Map(this.nodes.map(n=>[String(n.id),n]));
      this.view='knowledge-landscape'; this.layout='force'; this.zoom=1; this.panX=0; this.panY=0; this.dragNode=null; this.panStart=null;
      this.initPositions(); this.build(); this.bind(); this.applyFilters(); this.simulate(170); this.render();
    }
    initPositions(){
      const cx=600,cy=375;
      const pubs=this.nodes.filter(n=>n.kind==='publication');
      const topics=this.nodes.filter(n=>n.kind==='topic');
      pubs.forEach((n,i)=>{const a=(Math.PI*2*i)/Math.max(1,pubs.length); const r=n.root?0:235; n.x=cx+Math.cos(a)*r;n.y=cy+Math.sin(a)*r;});
      topics.forEach((n,i)=>{const a=(Math.PI*2*i)/Math.max(1,topics.length); const r=135+(i%4)*48;n.x=cx+Math.cos(a)*r;n.y=cy+Math.sin(a)*r;});
    }
    build(){
      this.svg.innerHTML='';
      const defs=svgEl('defs');
      const marker=svgEl('marker',{id:'sc-kl-arrow-'+this.root.id,viewBox:'0 0 10 10',refX:'8',refY:'5',markerWidth:'5',markerHeight:'5',orient:'auto-start-reverse'});
      marker.appendChild(svgEl('path',{d:'M 0 0 L 10 5 L 0 10 z',fill:'#5fd7ff'})); defs.appendChild(marker); this.svg.appendChild(defs);
      this.viewport=svgEl('g',{class:'sc-kl-viewport'}); this.edgeLayer=svgEl('g',{class:'sc-kl-edges'}); this.nodeLayer=svgEl('g',{class:'sc-kl-nodes'});
      this.viewport.appendChild(this.edgeLayer); this.viewport.appendChild(this.nodeLayer); this.svg.appendChild(this.viewport);
      this.edgeEls=new Map(); this.edges.forEach((e,i)=>{const line=svgEl('line',{class:'sc-kl-edge','data-basis':e.relationship_basis||'', 'data-index':i}); if(e.directed) line.setAttribute('marker-end',`url(#sc-kl-arrow-${this.root.id})`); this.edgeLayer.appendChild(line); this.edgeEls.set(i,line);});
      this.nodeEls=new Map(); this.nodes.forEach((n,i)=>{const g=svgEl('g',{class:'sc-kl-node'+(n.root?' is-root':''),'data-kind':n.kind||'node','data-index':i,tabindex:'0',role:'button','aria-label':n.label||n.id}); const metric=Number(n.metrics?.weighted_degree||0); const r=n.root?18:(n.kind==='publication'?11+Math.min(8,metric*1.4):7+Math.min(7,metric*.8)); const c=svgEl('circle',{r:r}); const t=svgEl('text',{class:'sc-kl-label'+(n.root?' is-root':''),'text-anchor':'middle',y:r+17}); t.textContent=short(n.label,n.root?34:25); g.appendChild(c);g.appendChild(t);this.nodeLayer.appendChild(g);this.nodeEls.set(i,g);});
      this.tooltip=document.createElement('div');this.tooltip.className='sc-kl__tooltip';this.tooltip.hidden=true;this.root.querySelector('.sc-kl__stage').appendChild(this.tooltip);
    }
    visibleGraph(){
      const nodes=this.nodes.filter(n=>n.visible); const ids=new Set(nodes.map(n=>String(n.id))); const edges=this.edges.filter(e=>e.visible&&ids.has(String(e.source))&&ids.has(String(e.target))); return {nodes,edges};
    }
    simulate(steps=100){
      if(this.layout==='radial'){this.radial();return;}
      const {nodes,edges}=this.visibleGraph(); if(!nodes.length)return;
      const centerX=600,centerY=370;
      for(let step=0;step<steps;step++){
        const alpha=(1-step/steps)*.14;
        for(let i=0;i<nodes.length;i++) for(let j=i+1;j<nodes.length;j++){
          const a=nodes[i],b=nodes[j]; let dx=b.x-a.x,dy=b.y-a.y; let d2=dx*dx+dy*dy; if(d2<25){dx+=2;dy+=2;d2=dx*dx+dy*dy;} const d=Math.sqrt(d2); const rep=(a.kind===b.kind?1900:1300)/d2; const fx=dx/d*rep,fy=dy/d*rep;a.vx-=fx;b.vx+=fx;a.vy-=fy;b.vy+=fy;
        }
        edges.forEach(e=>{const a=this.byId.get(String(e.source)),b=this.byId.get(String(e.target));if(!a||!b)return;let dx=b.x-a.x,dy=b.y-a.y,d=Math.sqrt(dx*dx+dy*dy)||1;const desired=e.relationship_basis==='embedding-cosine-similarity'?115:(e.relationship_basis==='explicit-citation'?175:90);const k=.0035*(1+Math.min(3,Number(e.weight||1)));const f=(d-desired)*k;const fx=dx/d*f,fy=dy/d*f;a.vx+=fx;b.vx-=fx;a.vy+=fy;b.vy-=fy;});
        nodes.forEach(n=>{const rootPull=n.root?.018:.004;n.vx+=(centerX-n.x)*rootPull;n.vy+=(centerY-n.y)*rootPull;n.vx*=.78;n.vy*=.78;if(!n._pinned){n.x+=n.vx*alpha*10;n.y+=n.vy*alpha*10;n.x=clamp(n.x,45,1155);n.y=clamp(n.y,45,715);}});
      }
    }
    radial(){
      const {nodes}=this.visibleGraph(),cx=600,cy=370; const pubs=nodes.filter(n=>n.kind==='publication'),topics=nodes.filter(n=>n.kind==='topic');
      pubs.forEach((n,i)=>{if(n.root){n.x=cx;n.y=cy;return;}const a=(i/Math.max(1,pubs.length))*Math.PI*2;n.x=cx+Math.cos(a)*250;n.y=cy+Math.sin(a)*250;});
      topics.forEach((n,i)=>{const a=(i/Math.max(1,topics.length))*Math.PI*2;const r=120+(i%3)*45;n.x=cx+Math.cos(a)*r;n.y=cy+Math.sin(a)*r;});
    }
    applyFilters(){
      const kinds=new Set([...this.root.querySelectorAll('[data-sc-kl-node-kind]:checked')].map(x=>x.dataset.scKlNodeKind));
      const bases=new Set([...this.root.querySelectorAll('[data-sc-kl-edge-basis]:checked')].map(x=>x.dataset.scKlEdgeBasis));
      const min=Number(this.root.querySelector('[data-sc-kl-strength]')?.value||0);
      this.nodes.forEach(n=>n.visible=kinds.has(String(n.kind)));
      this.edges.forEach(e=>{let ok=bases.has(String(e.relationship_basis)); const w=Number(e.relationship_basis==='embedding-cosine-similarity'?(e.similarity??e.weight):Math.min(1,e.weight||1)); if(w<min)ok=false; if(this.view==='topic-graph')ok=ok&&e.relationship_basis!=='explicit-citation'&&e.relationship_basis!=='embedding-cosine-similarity'; if(this.view==='citation-overlay')ok=ok&&e.relationship_basis==='explicit-citation'; if(this.view==='semantic-overlay')ok=ok&&e.relationship_basis==='embedding-cosine-similarity'; e.visible=ok;});
      if(this.view==='topic-graph') this.nodes.forEach(n=>n.visible=n.kind==='topic'||n.root); if(this.view==='citation-overlay'||this.view==='semantic-overlay') this.nodes.forEach(n=>n.visible=n.kind==='publication');
      this.simulate(70); this.render();
    }
    render(){
      if(!this.viewport)return;this.viewport.setAttribute('transform',`translate(${this.panX} ${this.panY}) scale(${this.zoom})`);
      this.edges.forEach((e,i)=>{const el=this.edgeEls.get(i);const a=this.byId.get(String(e.source)),b=this.byId.get(String(e.target));const show=e.visible&&a?.visible&&b?.visible;el.style.display=show?'':'none';if(!show)return;el.setAttribute('x1',a.x);el.setAttribute('y1',a.y);el.setAttribute('x2',b.x);el.setAttribute('y2',b.y);el.setAttribute('stroke-width',String(.8+Math.min(3.5,Number(e.weight||1)*1.4)));});
      this.nodes.forEach((n,i)=>{const el=this.nodeEls.get(i);el.style.display=n.visible?'':'none';if(n.visible)el.setAttribute('transform',`translate(${n.x} ${n.y})`);});
    }
    inspect(n){
      this.nodeEls.forEach(x=>x.classList.remove('is-selected'));this.nodeEls.get(n._i)?.classList.add('is-selected');
      const m=n.metrics||{}; const source=n.source_type||n.object_type||'Library record'; const inspector=this.root.querySelector('[data-sc-kl-inspector]');
      inspector.innerHTML=`<h3>${this.escape(n.label||n.id)}</h3><p class="sc-kl__muted">${this.escape(n.kind||'node')} · ${this.escape(source)}</p><dl><div><dt>Weighted degree</dt><dd>${Number(m.weighted_degree||0).toFixed(2)}</dd></div>${n.kind==='topic'?`<div><dt>Publications</dt><dd>${m.publication_count||0}</dd></div>`:''}<div><dt>Citation links</dt><dd>${m.citation_links||0}</dd></div><div><dt>Semantic links</dt><dd>${m.semantic_links||0}</dd></div>${n.confidence!=null?`<div><dt>Source confidence</dt><dd>${Number(n.confidence).toFixed(2)}</dd></div>`:''}</dl>${n.source_locator?`<p><strong>Source:</strong> ${this.escape(n.source_locator)}</p>`:''}${n.canonical_url?`<p><a href="${this.escapeAttr(n.canonical_url)}">Open publication</a></p>`:''}`;
    }
    escape(s){const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;}
    escapeAttr(s){return String(s??'').replace(/["'<>]/g,'');}
    bind(){
      this.root.querySelectorAll('[data-sc-kl-node-kind],[data-sc-kl-edge-basis]').forEach(x=>x.addEventListener('change',()=>this.applyFilters()));
      const slider=this.root.querySelector('[data-sc-kl-strength]'),out=this.root.querySelector('[data-sc-kl-strength-output]'); if(slider)slider.addEventListener('input',()=>{if(out)out.textContent=Number(slider.value).toFixed(2);this.applyFilters();});
      this.root.querySelectorAll('[data-sc-kl-view]').forEach(btn=>btn.addEventListener('click',()=>{if(btn.disabled)return;this.root.querySelectorAll('[data-sc-kl-view]').forEach(b=>b.classList.remove('is-active'));btn.classList.add('is-active');this.view=btn.dataset.scKlView;this.applyFilters();}));
      this.root.querySelectorAll('[data-sc-kl-layout]').forEach(btn=>btn.addEventListener('click',()=>{this.root.querySelectorAll('[data-sc-kl-layout]').forEach(b=>b.classList.remove('is-active'));btn.classList.add('is-active');this.layout=btn.dataset.scKlLayout;this.simulate(100);this.render();}));
      this.root.querySelectorAll('[data-sc-kl-zoom]').forEach(btn=>btn.addEventListener('click',()=>{this.zoom=clamp(this.zoom*(btn.dataset.scKlZoom==='in'?1.18:.85),.45,3);this.render();}));
      this.root.querySelector('[data-sc-kl-fit]')?.addEventListener('click',()=>{this.zoom=1;this.panX=0;this.panY=0;this.render();});
      this.root.querySelector('[data-sc-kl-reset]')?.addEventListener('click',()=>{this.root.querySelectorAll('[data-sc-kl-node-kind],[data-sc-kl-edge-basis]').forEach(x=>{if(!x.disabled)x.checked=true;}); if(slider){slider.value='0';if(out)out.textContent='0.00';}this.view='knowledge-landscape';this.layout='force';this.zoom=1;this.panX=0;this.panY=0;this.root.querySelectorAll('[data-sc-kl-view]').forEach((b,i)=>b.classList.toggle('is-active',i===0));this.root.querySelectorAll('[data-sc-kl-layout]').forEach(b=>b.classList.toggle('is-active',b.dataset.scKlLayout==='force'));this.initPositions();this.applyFilters();});
      this.nodeEls.forEach((g,i)=>{const n=this.nodes[i]; const activate=()=>this.inspect(n);g.addEventListener('click',activate);g.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();activate();}});g.addEventListener('pointerenter',e=>{this.tooltip.hidden=false;this.tooltip.innerHTML=`<strong>${this.escape(n.label||n.id)}</strong><br>${this.escape(n.kind||'node')}`;});g.addEventListener('pointermove',e=>{const r=this.root.getBoundingClientRect();this.tooltip.style.left=(e.clientX-r.left+12)+'px';this.tooltip.style.top=(e.clientY-r.top+12)+'px';});g.addEventListener('pointerleave',()=>this.tooltip.hidden=true);g.addEventListener('pointerdown',e=>{e.stopPropagation();this.dragNode=n;n._pinned=true;g.setPointerCapture?.(e.pointerId);});});
      const stage=this.root.querySelector('.sc-kl__stage'); stage?.addEventListener('pointerdown',e=>{if(this.dragNode)return;this.panStart={x:e.clientX,y:e.clientY,px:this.panX,py:this.panY};}); stage?.addEventListener('pointermove',e=>{const rect=this.svg.getBoundingClientRect();if(this.dragNode){const sx=1200/rect.width,sy=760/rect.height;this.dragNode.x=clamp((e.clientX-rect.left)*sx/this.zoom-this.panX/this.zoom,20,1180);this.dragNode.y=clamp((e.clientY-rect.top)*sy/this.zoom-this.panY/this.zoom,20,740);this.render();}else if(this.panStart){this.panX=this.panStart.px+(e.clientX-this.panStart.x)*(1200/rect.width);this.panY=this.panStart.py+(e.clientY-this.panStart.y)*(760/rect.height);this.render();}}); window.addEventListener('pointerup',()=>{this.dragNode=null;this.panStart=null;}); stage?.addEventListener('wheel',e=>{e.preventDefault();this.zoom=clamp(this.zoom*(e.deltaY<0?1.08:.93),.45,3);this.render();},{passive:false});
    }
  }
  const boot=()=>document.querySelectorAll('[data-sc-kl-root]').forEach(root=>{if(!root.dataset.scKlReady){root.dataset.scKlReady='1';new KnowledgeMap(root);}});
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
