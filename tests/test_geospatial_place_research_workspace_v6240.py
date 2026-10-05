from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "geospatial_place_research_workspace" in main
    assert '/api/library/v1/geospatial-research/readiness' in main
    assert '/api/library/v1/geospatial-research/relation-preview' in main
    assert '/api/library/v1/geospatial-research/coverage-audit' in main
    assert '/research/geospatial' in web
    assert 'Geospatial &amp; Place-Based Research Workspace' in web
    assert 'Web v2.24.0 · API v1' in web
    assert 'view === "geospatial"' in app
    assert 'loadGeospatialResearchWorkspace()' in app
    assert '"research-geospatial"' in nav
    assert '"Geospatial"' in nav
    assert 'geospatial-place-research-readiness' in api
