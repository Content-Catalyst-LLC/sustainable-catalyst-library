#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
php = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundations-first-edition-v210.php').read_text(encoding='utf-8')
system = (root / 'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php').read_text(encoding='utf-8')

checks = {
    'v2.1.5 provisioner': "private const RELEASE = '2.1.5';" in php,
    'v2.1.5 system': "SC_LIBRARY_FOUNDATIONS_VERSION', '2.1.5" in system,
    'Foundations family assigned': "'sc_document_family' => ['foundations', 'Foundations']" in php,
    'Foundation Document type assigned': "'sc_document_type' => ['foundation-document', 'Foundation Document']" in php,
    'taxonomy association': "register_taxonomy_for_object_type($taxonomy, 'sc_foundation_doc')" in php,
    'Knowledge Library collection preserved': 'SC_Library_Taxonomies::COLLECTION' in php,
    'documentation category preserved': 'institutional-foundations' in php,
    'catalog readiness counter': 'private function count_catalog_ready()' in php,
    'completion requires readiness': '$this->count_catalog_ready() === self::EXPECTED_COUNT' in php,
    'current lifecycle': "'_sc_document_lifecycle_status', 'current'" in php,
    'repository metadata': "'_sc_document_version'" in php and "'_sc_document_repository_order'" in php,
    'cache cleanup': 'clean_object_term_cache' in php and 'clean_post_cache' in php,
    'repair action v215': 'sc_foundations_v215_reprovision' in php,
}
failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if failed:
    print('FAILED: ' + ', '.join(failed), file=sys.stderr)
    raise SystemExit(1)
print(f'PASS: {len(checks)} live catalog activation checks')
