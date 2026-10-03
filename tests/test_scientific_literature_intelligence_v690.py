from pathlib import Path

root = Path(__file__).resolve().parents[1]
service = (root / "library-backend/app/scientific_literature_intelligence.py").read_text()
main = (root / "library-backend/app/main.py").read_text()
plugin = (root / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
web = (root / "library-web/assets/app.js").read_text()
index = (root / "library-web/index.html").read_text()

markers = [
    'LIBRARY_VERSION = "6.9.0"',
    'BACKEND_VERSION = "3.9.0"',
    'PUBLICATION_CONTRACT = "sc-library-scientific-publication-profile/1.0"',
    'LITERATURE_SET_CONTRACT = "sc-library-scientific-literature-set/1.0"',
    'def normalize_publication(',
    'def analyze_publication(',
    'def literature_set(',
    'def review_intelligence(',
    'def validate_publication(',
    'def readiness(',
    '"citation_count_implies_quality": False',
    '"journal_venue_implies_quality": False',
    '"study_design_indicator_is_definitive_classification": False',
    '"automatic_meta_analysis": False',
    '"automatic_consensus_inference": False',
    '"database_migration_required": False',
]
for marker in markers:
    assert marker in service, marker

for route in [
    '/api/library/v1/scientific-literature',
    '/api/library/v1/scientific-literature/readiness',
    '/api/library/v1/scientific-literature/schemas',
    '/api/library/v1/scientific-literature/publications/normalize',
    '/api/library/v1/scientific-literature/publications/analyze',
    '/api/library/v1/scientific-literature/sets/analyze',
    '/api/library/v1/scientific-literature/reviews/analyze',
    '/api/library/v1/scientific-literature/validate',
]:
    assert route in main, route

for marker in [
    '"scientific_literature_intelligence": True',
    '"scientific_literature_database_migration_required": False',
    '"scientific_literature_citation_count_implies_quality": False',
    '"library_web_version": "2.9.0"',
    '"library_sdk_version": "1.9.0"',
]:
    assert marker in main, marker

assert 'Version: 6.9.0' in plugin
assert 'SC_LIBRARY_SCIENTIFIC_LITERATURE_AUTHORITY' in plugin
assert 'client_label:\'library-web-v2.9.0\'' in web
assert '/scientific-literature/readiness' in web
assert 'previewWorkingSetLiterature' in web
assert 'working-set-literature-preview-button' in index
assert 'Web v2.9.0 · API v1' in index
print('PASS: Library v6.9.0 Scientific Literature Intelligence static release contract')
