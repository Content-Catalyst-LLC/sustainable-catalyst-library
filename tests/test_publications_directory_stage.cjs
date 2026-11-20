const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { JSDOM, VirtualConsole } = require('jsdom');
const axe = require('axe-core');

const stage = process.env.SEO031_STAGE;
assert.ok(stage, 'Set SEO031_STAGE to the isolated fixture captures');
const origin = 'http://127.0.0.1:18765';
const read = name => fs.readFileSync(path.join(stage, name), 'utf8');
const parse = (file, options = {}) => new JSDOM(read(file), {
  url: origin + '/publications/', virtualConsole: new VirtualConsole(), ...options,
});
const before = parse('hub-0.html');
const after = parse('hub-1.html');
const directory = after.window.document.querySelector('[data-sc-publications-directory]');

test('initial HTML contains a named directory for the 14 existing fields', () => {
  assert.equal(before.window.document.querySelector('[data-sc-publications-directory]'), null);
  assert.equal(directory.tagName, 'NAV');
  const title = after.window.document.getElementById(directory.getAttribute('aria-labelledby'));
  assert.equal(title.textContent.trim(), 'Article Map directory');
  assert.equal(directory.querySelectorAll('section > h4').length, 14);
});

test('only verified populated destinations enter the directory and hrefs are unique', () => {
  const classified = JSON.parse(read('classifications.json'));
  const counts = Object.fromEntries(['empty', 'populated', 'unavailable', 'unresolved']
    .map(state => [state, classified.filter(row => row.status === state).length]));
  assert.deepEqual(counts, { empty: 14, populated: 28, unavailable: 14, unresolved: 114 });
  const destinations = [...directory.querySelectorAll('a[href]')];
  assert.equal(destinations.length, 56);
  assert.equal(new Set(destinations.map(a => a.getAttribute('href'))).size, 56);
  const actualMaps = [...directory.querySelectorAll('[data-sc-directory-map]')].map(a => a.href).sort();
  assert.deepEqual(actualMaps, classified.filter(row => row.status === 'populated').map(row => row.url).sort());
  assert.doesNotMatch(directory.textContent, /Protected fixture body|Synthetic protected map|Synthetic empty map/);
});

test('spotlight markup, parameter selection and map article anchors are unchanged', () => {
  const stages = dom => [...dom.window.document.querySelectorAll('[data-sc-field-spotlights]')].map(el => el.outerHTML);
  assert.equal(stages(after).length, 14);
  assert.deepEqual(stages(before), stages(after));
  const param0 = parse('parameter-0.html');
  const param1 = parse('parameter-1.html');
  assert.deepEqual(stages(param0), stages(param1));
  assert.ok(param1.window.document.querySelector('[data-initial-panel-key="ancient-near-eastern-law-early-legal-codes"]'));
  assert.equal(read('map-0.html'), read('map-1.html'));
  assert.match(read('map-1.html'), /href="[^"]*fixture-article-international-law\//);
});

test('canonical, robots and HTTP redirect behavior are preserved', () => {
  const canonical = dom => dom.window.document.querySelector('link[rel=canonical]').href;
  const robots = dom => [...dom.window.document.querySelectorAll('meta[name=robots]')].map(el => el.content);
  assert.equal(canonical(before), origin + '/publications/');
  assert.equal(canonical(after), canonical(before));
  assert.deepEqual(robots(before), robots(after));
  for (const file of ['hub-0.headers', 'hub-1.headers', 'parameter-0.headers', 'parameter-1.headers']) {
    assert.match(read(file), /^HTTP\/1\.1 200/m);
    assert.doesNotMatch(read(file), /^Location:/im);
  }
});

test('spotlight playback still changes the active panel in the staged DOM', async () => {
  const runtime = parse('hub-1.html', { runScripts: 'outside-only' });
  const win = runtime.window;
  win.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {} });
  const script = path.join(__dirname, '../sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js');
  const ready = new Promise(resolve => win.addEventListener('DOMContentLoaded', resolve, { once: true }));
  win.eval(fs.readFileSync(script, 'utf8'));
  await ready;
  const spotlight = win.document.querySelector('.sc-field-spotlight');
  const previous = spotlight.dataset.activePanelKey;
  assert.ok(previous);
  spotlight.querySelector('[data-panel-next]').click();
  assert.notEqual(spotlight.dataset.activePanelKey, previous);
  assert.equal(win.document.querySelectorAll('[data-sc-directory-map]').length, 28);
  win.close();
});

test('directory passes scoped axe WCAG A and AA DOM checks', async () => {
  const runtime = parse('hub-1.html', { runScripts: 'outside-only' });
  runtime.window.eval(axe.source);
  const result = await runtime.window.axe.run('[data-sc-publications-directory]', {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    rules: { 'color-contrast': { enabled: false } },
  });
  assert.equal(result.violations.length, 0, Array.from(result.violations, v => v.id).join(', '));
  runtime.window.close();
});
