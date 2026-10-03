from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
service = (ROOT / "library-backend/app/research_graph_navigation.py").read_text(encoding="utf-8")
main = (ROOT / "library-backend/app/main.py").read_text(encoding="utf-8")
domain = (ROOT / "library-backend/app/domain_authority.py").read_text(encoding="utf-8")
plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text(encoding="utf-8")
web = (ROOT / "library-web/assets/app.js").read_text(encoding="utf-8")
pyclient = (ROOT / "clients/python/sustainable_catalyst_library/client.py").read_text(encoding="utf-8")
jsclient = (ROOT / "clients/javascript/src/index.js").read_text(encoding="utf-8")

assert 'LIBRARY_VERSION = "6.6.0"' in service
assert 'BACKEND_VERSION = "3.6.0"' in service
assert '"graph_connectivity_implies_truth": False' in service
assert '"graph_connectivity_implies_causality": False' in service
assert '"shorter_path_implies_stronger_evidence": False' in service
assert '"citation_implies_support": False' in service
assert 'def neighborhood(' in service
assert 'def summary(' in service
assert 'def path(' in service
assert 'def readiness()' in service

for route in (
    '/api/library/v1/research-graph',
    '/api/library/v1/research-graph/readiness',
    '/api/library/v1/research-graph/records/{record_id:path}/neighborhood',
    '/api/library/v1/research-graph/records/{record_id:path}/summary',
    '/api/library/v1/research-graph/path',
):
    assert route in main, route

assert '"research_graph_evidence_navigation": True' in main
assert '"research_graph_shortest_paths": True' in main
assert '"research_graph_path_implies_causality": False' in main
assert '"library_web_version": "2.6.0"' in main
assert '"library_sdk_version": "1.6.0"' in main
assert '"research-graph-evidence-navigation": {' in domain
assert "SC_LIBRARY_RESEARCH_GRAPH_NAVIGATION_AUTHORITY" in plugin
assert "/research-graph/readiness" in web
assert "loadEvidenceNavigation" in web
assert "def research_graph(self)" in pyclient
assert "def research_graph_path(" in pyclient
assert "researchGraph(){" in jsclient
assert "researchGraphPath(payload)" in jsclient

print("PASS: Library v6.6.0 Research Graph & Evidence Navigation static release contract")
