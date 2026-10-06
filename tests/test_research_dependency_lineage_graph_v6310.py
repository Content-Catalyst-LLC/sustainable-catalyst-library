from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
def read(path): return (ROOT / path).read_text()

def test_release_markers_and_surface():
    main = read("library-backend/app/main.py")
    web = read("library-web/index.html")
    wp = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "research_dependency_lineage_graph" in main
    assert "/api/library/v1/research-lineage-graph" in main
    assert 'data-view="research-lineage-graph"' in web
    assert "Research Dependency &amp; Lineage Graph" in web
    assert "Version: 6.31.0" in wp

def test_generations_and_port_repair():
    assert 'webVersion: "2.31.0"' in read("library-web/config.js")
    assert 'version = "1.31.0"' in read("clients/python/pyproject.toml")
    assert '"version": "1.31.0"' in read("clients/javascript/package.json")
    compose = read("library-web/compose.yml")
    assert '${SC_LIBRARY_WEB_BIND_PORT:-8095}' in compose

def test_previous_releases_preserved():
    main = read("library-backend/app/main.py")
    assert "unified_research_project_workspace" in main
    assert "/api/library/v1/research-project-workspace" in main
    assert "cross_product_research_handoff_certification" in main
    assert "library_workspace_research_integration" in main
