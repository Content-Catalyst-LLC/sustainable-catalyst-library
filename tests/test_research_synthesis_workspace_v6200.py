from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "research_synthesis_workspace" in main
    assert '/api/library/v1/research-synthesis/readiness' in main
    assert '/api/library/v1/research-synthesis/evidence-matrix' in main
    assert '/api/library/v1/research-synthesis/contradictions' in main
    assert '/api/library/v1/research-synthesis/convergence' in main
    assert '/research/synthesis' in web
    assert 'Research Synthesis Workspace' in web
    assert 'Web v2.20.0 · API v1' in web
    assert 'view === "synthesis"' in app
    assert 'loadResearchSynthesisWorkspace()' in app
    assert '"research-synthesis"' in nav
    assert '"Synthesis"' in nav
    assert 'research-synthesis-readiness' in api
