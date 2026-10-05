from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "evidence_matrix_claim_support_workspace" in main
    assert '/api/library/v1/evidence-matrix/readiness' in main
    assert '/api/library/v1/evidence-matrix/support-profiles' in main
    assert '/api/library/v1/evidence-matrix/source-dependencies' in main
    assert '/api/library/v1/evidence-matrix/gaps' in main
    assert '/research/evidence' in web
    assert 'Evidence Matrix &amp; Claim Support Analysis' in web
    assert 'Web v2.22.0 · API v1' in web
    assert 'view === "evidence"' in app
    assert 'loadEvidenceMatrixWorkspace()' in app
    assert '"research-evidence"' in nav
    assert '"Evidence"' in nav
    assert 'evidence-matrix-claim-support-readiness' in api
