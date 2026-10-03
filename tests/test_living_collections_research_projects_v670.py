from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
service = (ROOT / "library-backend/app/living_collections_projects.py").read_text(encoding="utf-8")
main = (ROOT / "library-backend/app/main.py").read_text(encoding="utf-8")
domain = (ROOT / "library-backend/app/domain_authority.py").read_text(encoding="utf-8")
plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text(encoding="utf-8")
web = (ROOT / "library-web/assets/app.js").read_text(encoding="utf-8")
html = (ROOT / "library-web/index.html").read_text(encoding="utf-8")
pyclient = (ROOT / "clients/python/sustainable_catalyst_library/client.py").read_text(encoding="utf-8")
jsclient = (ROOT / "clients/javascript/src/index.js").read_text(encoding="utf-8")
packager = (ROOT / "tests/test_living_collections_research_projects_v670.py").read_text(encoding="utf-8")

for marker in (
    'LIBRARY_VERSION = "6.7.0"',
    'BACKEND_VERSION = "3.7.0"',
    'def create_living_collection(',
    'def collection_state(',
    'def refresh_collection(',
    'def apply_refresh(',
    'def project_brief(',
    'def readiness()',
    '"refresh_automatically_mutates_membership": False',
    '"apply_requires_explicit_selected_record_ids": True',
    '"collection_membership_implies_truth": False',
    '"project_membership_implies_research_truth": False',
    '"automatic_platform_core_promotion": False',
):
    assert marker in service, marker

for route in (
    '/api/library/v1/living-research',
    '/api/library/v1/living-research/readiness',
    '/api/library/v1/living-research/projects/{project_id:path}/brief',
    '/api/library/v1/living-research/collections/{collection_id:path}',
    '/api/library/v1/living-research/collections',
    '/api/library/v1/living-research/collections/{collection_id:path}/refresh',
    '/api/library/v1/living-research/collections/{collection_id:path}/apply',
):
    assert route in main, route

assert '"living_collections_research_projects": True' in main
assert '"living_collection_refresh_auto_applies": False' in main
assert '"library_web_version": "2.7.0"' in main
assert '"library_sdk_version": "1.7.0"' in main
assert '"living-collections-research-projects": {' in domain
assert 'SC_LIBRARY_LIVING_RESEARCH_AUTHORITY' in plugin
assert 'Version: 6.7.0' in plugin
assert 'workspace-living-form' in html
assert 'workspace-living-list' in html
assert '/living-research/readiness' in web
assert 'createLivingCollection' in web
assert 'refreshLivingCollection' in web
assert 'applyLivingCollection' in web
assert 'loadLivingProjectBrief' in web
assert 'def living_research(self)' in pyclient
assert 'def living_collection_refresh(' in pyclient
assert 'def living_collection_apply(' in pyclient
assert 'livingResearch(){' in jsclient
assert 'livingCollectionRefresh(' in jsclient
assert 'livingCollectionApply(' in jsclient
assert json.loads((ROOT / 'clients/javascript/package.json').read_text())['version'] == '1.7.0'

print('PASS: Library v6.7.0 Living Collections & Research Projects static release contract')
