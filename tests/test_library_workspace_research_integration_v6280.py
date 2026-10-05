from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path): return (ROOT / path).read_text()

def test_release_markers_and_surfaces():
    main = read("library-backend/app/main.py")
    web = read("library-web/index.html")
    wp = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert 'library_workspace_research_integration' in main
    assert '/api/library/v1/workspace-integration' in main
    assert 'data-view="workspace-integration"' in web
    assert 'Library ↔ Workspace Research Integration' in web
    assert 'Web v2.28.0 · API v1' in web
    assert "SC_LIBRARY_WORKSPACE_RESEARCH_INTEGRATION_ROUTE" in wp


def test_guardrails_and_next_release():
    source = read("library-backend/app/library_workspace_research_integration.py")
    assert '"live_workspace_transport_certified": False' in source
    assert '"automatic_cross_product_push_delivery": False' in source
    assert '"server_side_exchange_persistence": False' in source
    assert '"database_migration_required": False' in source
    assert '"wordpress_required": False' in source
    assert '"next_release": "6.29.0"' in source
