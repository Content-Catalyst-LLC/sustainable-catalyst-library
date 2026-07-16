#!/usr/bin/env python3
from pathlib import Path
import json, re, sys

root = Path(__file__).resolve().parents[1]
payload = json.loads((root / 'foundations-first-edition/import/foundations-first-edition.json').read_text(encoding='utf-8'))
php = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').read_text(encoding='utf-8')

documents = payload.get('documents', [])
ids = [d.get('document_id') for d in documents]
slugs = [d.get('slug') for d in documents]

checks = {
    'payload release v2.1.3': payload.get('release') == '2.1.3',
    'payload publishes by default': payload.get('default_post_status') == 'publish',
    '13 stable ids': len(ids) == 13 and len(set(ids)) == 13 and all(re.fullmatch(r'SC-FND-\d{3}', x or '') for x in ids),
    '13 unique slugs': len(slugs) == 13 and len(set(slugs)) == 13,
    'substantial HTML': all(len(re.sub(r'<[^>]+>', '', d.get('content_html', '')).strip()) > 1800 for d in documents),
    'HTML section headings': all('<h2 id=' in d.get('content_html', '') for d in documents),
    'PDF assets declared': all(d.get('plugin_pdf_file', '').startswith('assets/foundations/v2.1.0/pdf/') for d in documents),
    'automatic admin provisioning': "add_action('admin_init', [$this, 'maybe_provision'], 25)" in php,
    'idempotent lookup': "'meta_key' => '_sc_foundation_document_id'" in php,
    'publishes records': "'post_status' => 'publish'" in php,
    'stores HTML body': "'post_content' => wp_kses_post($content)" in php,
    'no import checkbox': 'name="publish"' not in php,
    'repair action available': 'sc_foundations_v213_reprovision' in php,
    'status screen available': 'First Edition Status' in php,
    'content edition retained': "private const CONTENT_EDITION = '2.1.0';" in php,
}

for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
failed = [name for name, passed in checks.items() if not passed]
if failed:
    print('FAILED: ' + ', '.join(failed), file=sys.stderr)
    sys.exit(1)
print(f'PASS: {len(checks)} automatic HTML provisioning checks')
