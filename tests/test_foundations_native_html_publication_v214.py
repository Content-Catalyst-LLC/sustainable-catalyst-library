#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
php = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').read_text(encoding='utf-8')
patcher = (root / 'apply_foundations_native_html_publication_fix_v214.py').read_text(encoding='utf-8')
system = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').read_text(encoding='utf-8')

checks = {
    'v2.1.4 provisioner': "private const RELEASE = '2.1.4';" in php,
    'v2.1.4 system': "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.4" in system,
    'draft-first insert': "'post_status' => 'draft'" in php,
    'publish after metadata': "wp_update_post(wp_slash([" in php and "'post_status' => 'publish'" in php,
    'native source marker': "'_sc_foundation_source_mode', 'native_html'" in php,
    'conversion compatibility': "'_sc_document_extraction_status', 'legacy_content'" in php,
    'review compatibility': "'_sc_document_reviewed', 1" in php,
    'native method': "'_sc_document_extraction_method', 'native_html'" in php,
    'all compatible PDF keys': all(key in php for key in [
        '_sc_library_foundation_page_pdf_id',
        '_sc_library_pdf_attachment_id',
        '_sc_library_foundation_pdf_attachment_id',
        '_sc_library_foundation_attachment_id',
        '_sc_foundation_pdf_attachment_id',
        'sc_library_pdf_attachment_id',
    ]),
    'guard patch targets both classes': 'prevent_publish_without_pdf' in patcher and 'validate_publication' in patcher,
    'guard marker': 'Foundations v2.1.4 native HTML publication exemption' in patcher,
    'HTML required for exemption': "'' !== $native_content" in patcher,
    'manual repair action updated': 'sc_foundations_v214_reprovision' in php,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if failed:
    print('FAILED: ' + ', '.join(failed), file=sys.stderr)
    raise SystemExit(1)
print(f'PASS: {len(checks)} native HTML publication checks')
