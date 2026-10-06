from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
def read(path): return (ROOT / path).read_text()

def test_release_markers_and_surface():
    main = read("library-backend/app/main.py"); web = read("library-web/index.html"); wp = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "unified_research_project_workspace" in main
    assert "/api/library/v1/research-project-workspace" in main
    assert 'data-view="research-project-workspace"' in web
    assert "Unified Research Project Workspace" in web
    assert "Version: 6.30.0" in wp

def test_client_and_web_generations():
    assert 'webVersion: "2.30.0"' in read("library-web/config.js")
    assert 'version = "1.30.0"' in read("clients/python/pyproject.toml")
    assert '"version": "1.30.0"' in read("clients/javascript/package.json")

def test_previous_release_preserved():
    main = read("library-backend/app/main.py")
    assert "cross_product_research_handoff_certification" in main
    assert "/api/library/v1/cross-product-certification" in main
    assert "library_workspace_research_integration" in main
