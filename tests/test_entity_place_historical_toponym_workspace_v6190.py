from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "entity_place_historical_toponym_workspace" in main
    assert '/api/library/v1/entity-place-workspace/readiness' in main
    assert '/api/library/v1/entity-place-workspace/toponym-timeline' in main
    assert '/api/library/v1/entity-place-workspace/decision-preview' in main
    assert '/research/entities' in web
    assert 'Entity, Place & Historical Toponym Workspace' in web
    assert 'Web v2.19.0 · API v1' in web
    assert 'view === "entities"' in app
    assert 'loadEntityPlaceWorkspace()' in app
    assert '"research-entities"' in nav
    assert '"Entities & Places"' in nav
    assert 'entity-place-historical-toponym-readiness' in api
