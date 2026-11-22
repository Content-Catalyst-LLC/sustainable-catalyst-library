#!/usr/bin/env node
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js'), 'utf8');
const helperStart = source.indexOf('// Homepage showcase');
const helperEnd = source.indexOf('class Landscape{', helperStart);
assert.ok(helperStart >= 0 && helperEnd > helperStart, 'native showcase function must be present');
const sandbox = {};
vm.runInNewContext(source.slice(helperStart, helperEnd) + '\nthis.slice = showcaseSlice;', sandbox);
const pubs = Array.from({ length: 60 }, (_, i) => ({ id: 'p' + i, kind: 'publication', label: 'Publication ' + i }));
const topics = Array.from({ length: 60 }, (_, i) => ({ id: 't' + i, kind: 'topic', label: 'Topic ' + i }));
const edges = [];
for (let i = 0; i < 60; i++) for (let j = 0; j < 3; j++) {
  edges.push({ source: 'p' + i, target: 't' + ((i * 3 + j) % 60), relationship_basis: 'metadata-association' });
}
const relationships = Array.from({ length: 30 }, (_, i) => ({ source: 'p' + i, target: 'p' + (i + 1), topic_jaccard: .3 }));
const corpus = {
  nodes: [...pubs, ...topics], edges, publication_relationships: relationships,
  metrics: { publication_count: 161, topic_count: 877, edge_count: 5922 },
  knowledge_terrain_4d: { topic_anchors: [1, 2, 3] },
  topic_regions: topics.map(t => ({ topic_ids: [t.id] }))
};
const out = sandbox.slice(corpus);
const ids = new Set(out.nodes.map(n => n.id));
const knownEdges = new Set(edges.map(e => e.source + '|' + e.target));
assert.ok(out.nodes.length < corpus.nodes.length && out.nodes.length <= 40, 'bounded projection');
assert.ok(out.nodes.some(n => n.kind === 'topic'), 'topics included');
assert.ok(out.nodes.some(n => n.kind === 'publication'), 'publications included');
assert.ok(out.edges.length > 0, 'real links included');
assert.ok(out.edges.every(e => ids.has(e.source) && ids.has(e.target) && knownEdges.has(e.source + '|' + e.target)), 'no fabricated links');
assert.ok(out.publication_relationships.every(r => ids.has(r.source) && ids.has(r.target)), 'matrix uses visible publications');
assert.equal(out.metrics, corpus.metrics, 'corpus metrics unchanged');
assert.equal(out.knowledge_terrain_4d, corpus.knowledge_terrain_4d, 'native 4D terrain unchanged');
assert.equal(corpus.nodes.length, 120, 'input untouched');
assert.equal(JSON.stringify(out.nodes.map(n => n.id)), JSON.stringify(sandbox.slice(corpus).nodes.map(n => n.id)), 'deterministic');
for (const relative of ['assets/js/sc-library-knowledge-showcase.js', 'assets/js/sc-library-knowledge-landscape-v5270.js']) {
  new vm.Script(fs.readFileSync(path.join(root, 'sustainable-catalyst-library', relative), 'utf8'), { filename: relative });
}
console.log('PASS: native showcase uses actual bounded relationships, full metrics, 4D contract, and valid JS');
