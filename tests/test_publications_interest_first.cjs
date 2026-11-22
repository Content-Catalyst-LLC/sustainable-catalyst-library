/**
 * SC-PUB-RELEASE-02 design contract.
 * Tests editorial intent + source integrity without impersonating WordPress.
 * Browser/mobile/CWV validation remains a separate staging acceptance gate.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..', 'sustainable-catalyst-library');
const page = fs.readFileSync(path.join(root, 'templates/field-spotlights.php'), 'utf8');
const directory = fs.readFileSync(path.join(root, 'templates/publications-directory.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/sc-library-field-spotlights.css'), 'utf8');

test('interest-led page leads with real questions and concise answers', () => {
  assert.match(page, /What would you like to understand\?/);
  for (const domain of [
    'Sustainable Systems',
    'Technology & Systems Intelligence',
    'Natural Science',
    'Global Governance',
    'Psychology',
    'Problem Solving',
  ]) {
    assert.ok(page.includes(domain), 'missing audience interest: ' + domain);
  }
  assert.match(page, /data-sc-publications-interest-entry/);
  assert.ok(page.indexOf('data-sc-publications-interest-entry') < page.indexOf('sc-field-stack__index'));
  assert.match(page, /Environmental limits arise from interacting ecological systems/);
  assert.match(page, /Complex problems require clear framing/);
});
test('existing editorial records remain the only source for article links', () => {
  assert.match(page, /\$publications_directory\[\s*\$entry_key\s*\]\['maps'\]\[0\]/);
  assert.match(page, /!\s*empty\(\s*\$interest\['map'\]\['url'\]\s*\)/);
  assert.match(page, /esc_url\(\s*\$interest\['map'\]\['url'\]\s*\)/);
  assert.doesNotMatch(page, /FAQPage|QAPage|itemReviewed|aggregateRating/);
  assert.match(page, /\/feature-suggestions\//);
});
test('complete catalog is retained but not forced open on initial load', () => {
  assert.match(page, /data-sc-publications-field-disclosure/);
  assert.match(page, /data-sc-publications-full-collection/);
  assert.match(page, /<details class="sc-pubintent__full-collection"/);
  assert.match(page, /\$requested_field \? 'open' : ''/);
  assert.match(page, /include SC_LIBRARY_DIR \. 'templates\/field-spotlight-stage.php'/);
  assert.match(page, /data-sc-field-spotlights="v4\.3\.22\.4"/);
  assert.match(directory, /data-sc-directory-map/);
  assert.match(directory, /data-sc-directory-article/);
  assert.match(directory, /<details class="sc-pubdir2__shell"/);
});
test('responsive readable layout, keyboard focus and reduced motion', () => {
  assert.match(css, /\.sc-pubintent__grid\{/);
  assert.match(css, /grid-template-columns:repeat\(3,minmax\(0,1fr\)\)/);
  assert.match(css, /@media\(max-width:680px\)/);
  assert.match(css, /:focus-visible/);
  assert.match(css, /prefers-reduced-motion:reduce/);
});
