from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def test_release_contract():
 main=(ROOT/"library-backend/app/main.py").read_text(); web=(ROOT/"library-web/index.html").read_text(); app=(ROOT/"library-web/assets/app.js").read_text(); nav=(ROOT/"library-backend/app/navigation_service.py").read_text(); api=(ROOT/"library-backend/app/independent_api.py").read_text()
 assert "dataset_discovery_statistical_evidence_workspace" in main
 assert '/api/library/v1/statistical-evidence/readiness' in main
 assert '/api/library/v1/statistical-evidence/discover' in main
 assert '/api/library/v1/statistical-evidence/statistical-table' in main
 assert '/api/library/v1/statistical-evidence/uncertainty-audit' in main
 assert '/research/data' in web
 assert 'Dataset Discovery &amp; Statistical Evidence Workspace' in web
 assert 'Web v2.23.0 · API v1' in web
 assert 'view === "data"' in app
 assert 'loadStatisticalEvidenceWorkspace()' in app
 assert '"research-data"' in nav
 assert '"Data"' in nav
 assert 'dataset-discovery-statistical-evidence-readiness' in api
