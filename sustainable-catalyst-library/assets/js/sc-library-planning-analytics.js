(() => {
  'use strict';
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  document.querySelectorAll('[data-sc-dependency-graph]').forEach((target) => {
    const source = target.parentElement?.querySelector('[data-sc-dependency-data]');
    if (!source) return;
    let data;
    try { data = JSON.parse(source.textContent || '{}'); } catch (error) { target.textContent = 'Dependency graph data could not be read.'; return; }
    const nodes = Array.isArray(data.nodes) ? data.nodes : [];
    const edges = Array.isArray(data.edges) ? data.edges : [];
    if (!nodes.length) { target.innerHTML = '<p>No dependency records are available yet.</p>'; return; }
    const width = Math.max(840, target.clientWidth || 840);
    const laneOrder = ['idea','proposed','planned','researching','drafting','review','scheduled','published'];
    const laneIndex = (status) => { const index = laneOrder.indexOf(status); return index < 0 ? 2 : index; };
    const grouped = new Map();
    nodes.forEach((node) => { const lane = laneIndex(node.status); if (!grouped.has(lane)) grouped.set(lane, []); grouped.get(lane).push(node); });
    const maxRows = Math.max(...Array.from(grouped.values()).map((items) => items.length), 1);
    const height = Math.max(360, 100 + maxRows * 92);
    const laneWidth = Math.max(150, (width - 60) / laneOrder.length);
    const positions = new Map();
    grouped.forEach((items, lane) => items.forEach((node, row) => positions.set(Number(node.id), {x: 30 + lane * laneWidth, y: 55 + row * 88})));
    const lines = edges.map((edge) => {
      const from = positions.get(Number(edge.source)); const to = positions.get(Number(edge.target));
      if (!from || !to) return '';
      const x1 = from.x + 130, y1 = from.y + 26, x2 = to.x, y2 = to.y + 26;
      const mx = (x1 + x2) / 2;
      return `<path d="M ${x1} ${y1} C ${mx} ${y1}, ${mx} ${y2}, ${x2} ${y2}" class="${edge.resolved ? 'is-resolved' : 'is-unresolved'}" marker-end="url(#sc-arrow)"/>`;
    }).join('');
    const cards = nodes.map((node) => {
      const position = positions.get(Number(node.id)); if (!position) return '';
      return `<g transform="translate(${position.x},${position.y})"><rect width="130" height="54" rx="8" class="${node.blocked ? 'is-blocked' : 'is-ready'}"/><text x="10" y="18">${esc(String(node.title).slice(0,22))}</text><text x="10" y="37" class="meta">${esc(node.status)} · ${Number(node.progress || 0)}%</text></g>`;
    }).join('');
    const labels = laneOrder.map((label, index) => `<text x="${30 + index * laneWidth}" y="22" class="lane">${esc(label.replace('_',' '))}</text>`).join('');
    target.innerHTML = `<div class="sc-library-dependency-graph__scroll"><svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Planning dependency graph"><defs><marker id="sc-arrow" markerWidth="8" markerHeight="8" refX="7" refY="3" orient="auto"><path d="M0,0 L0,6 L7,3 z"/></marker></defs>${labels}<g class="edges">${lines}</g><g class="nodes">${cards}</g></svg></div>`;
  });
})();
