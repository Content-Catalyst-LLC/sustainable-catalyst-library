#!/usr/bin/env python3
from pathlib import Path
import importlib.util
import tempfile

ROOT = Path(__file__).resolve().parents[1]
MODULE = ROOT / 'sustainable-catalyst-library/includes/class-sc-library-foundation-system-v200.php'
PATCHER = ROOT / 'apply_foundations_v2_0_3.py'

module_text = MODULE.read_text(encoding='utf-8')
patcher_text = PATCHER.read_text(encoding='utf-8')

checks = {
    'v2.0.3 marker': "SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.3" in module_text,
    'post type route enforcement': 'foundation_document_post_type_args' in module_text,
    'document route base': "'slug'       => 'foundation-documents'" in module_text,
    'legacy rewrite removal': 'remove_legacy_foundation_document_rules' in module_text,
    'canonical page guard': 'protect_foundations_page_route' in module_text,
    'canonical page path': 'institution/foundations' in module_text,
    'legacy redirect removed': 'recover_legacy_foundations_route' not in module_text,
    'flexible slug regex': 'slug_pattern = re.compile' in patcher_text,
    'absence is accepted': 'No legacy route declaration was present' in patcher_text,
    'partial recovery documented': 'Failed v2.0.2 recovery' in (ROOT/'FOUNDATIONS_ROUTE_REPAIR_SETUP_v2.0.3.md').read_text(),
}

for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if not all(checks.values()):
    raise SystemExit(1)

spec = importlib.util.spec_from_file_location('fnd203patcher', PATCHER)
patcher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(patcher)

variants = {
    'single_quotes': "$args['rewrite'] = array( 'slug' => 'foundations', 'with_front' => false ); add_rewrite_rule( '^foundations/([^/]+)/?$', 'index.php' );",
    'double_quotes_compact': '$args["rewrite"]=["slug"=>"foundations"]; add_rewrite_rule("^foundations/([^/]+)/?$", "index.php");',
    'already_repaired': "$args['rewrite'] = array( 'slug' => 'foundation-documents' ); add_rewrite_rule( '^foundation-documents/([^/]+)/?$', 'index.php' );",
    'declaration_absent': 'public function register_document_type() { return true; }',
}

with tempfile.TemporaryDirectory() as td:
    td = Path(td)
    for name, content in variants.items():
        path = td / f'{name}.php'
        path.write_text(content, encoding='utf-8')
        patcher.patch_foundation_pages(path)
        result = path.read_text(encoding='utf-8')
        assert not patcher.contains_legacy_slug(result), name
        assert patcher.OLD_RULE not in result, name
        print('PASS: patch variant ' + name)

print(f'PASS: {len(checks)} package checks and {len(variants)} patch variants')
