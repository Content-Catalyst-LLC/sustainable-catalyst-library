from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "research_question_investigation_workspace" in main
    assert '/api/library/v1/research-investigation/readiness' in main
    assert '/api/library/v1/research-investigation/execution-plan' in main
    assert '/api/library/v1/research-investigation/handoff-preview' in main
    assert '/research/investigation' in web
    assert 'Research Question &amp; Investigation Workspace' in web
    assert 'Web v2.21.0 · API v1' in web
    assert 'view === "investigation"' in app
    assert 'loadResearchInvestigationWorkspace()' in app
    assert '"research-investigation"' in nav
    assert '"Investigation"' in nav
    assert 'research-question-investigation-readiness' in api
