#!/usr/bin/env python3
from pathlib import Path
import json, sys, re

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'foundations-first-edition/manifest.json').read_text(encoding='utf-8'))
payload = json.loads((root / 'foundations-first-edition/import/foundations-first-edition.json').read_text(encoding='utf-8'))
provisioner = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').read_text(encoding='utf-8')
system = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').read_text(encoding='utf-8')

checks = {
    'document count': len(manifest['documents']) == 13 == len(payload['documents']),
    'unique ids': len({d['document_id'] for d in manifest['documents']}) == 13,
    'all under review': all(d['status'] == 'under-review' for d in payload['documents']),
    'all published HTML records': all(d['post_status'] == 'publish' and len(d['content_html'].strip()) > 500 for d in payload['documents']),
    'all source files': all((root / d['markdown_file']).is_file() and (root / d['html_file']).is_file() and (root / d['pdf_file']).is_file() for d in manifest['documents']),
    'combined pdf': (root / 'foundations-first-edition/collection/Sustainable_Catalyst_Institutional_Foundations_First_Edition_v2.1.0.pdf').is_file(),
    'automatic provisioner': "add_action('admin_init', [$this, 'maybe_provision'], 25)" in provisioner,
    'manual import removed': 'admin_post_sc_foundations_v210_import' not in provisioner and 'Import First Edition' not in provisioner,
    'draft-first publication flow': "'post_status' => 'draft'" in provisioner and "wp_update_post(wp_slash([" in provisioner,
    'stable id update': "'_sc_foundation_document_id'" in provisioner and 'find_by_document_id' in provisioner,
    'HTML content installed': "'post_content' => wp_kses_post($content)" in provisioner,
    'PDF attachment preserved': '_sc_foundation_pdf_attachment_id' in provisioner,
    'Foundations collection assigned': 'wp_set_object_terms' in provisioner,
    'system version': "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.4" in system,
}

for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if not all(checks.values()):
    sys.exit(1)
print('PASS: Foundations v2.1.4 native HTML publication package')
