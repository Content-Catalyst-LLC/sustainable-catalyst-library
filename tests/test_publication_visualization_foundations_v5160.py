from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_and_backend_version():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    readme = (ROOT / "sustainable-catalyst-library/readme.txt").read_text()
    backend = (ROOT / "library-backend/app/__init__.py").read_text()
    assert "SC_LIBRARY_VERSION" in plugin
    assert "Stable tag:" in readme


def test_visualization_storage_and_routes_exist():
    schema = (ROOT / "library-backend/app/schema.sql").read_text()
    main = (ROOT / "library-backend/app/main.py").read_text()
    assert "CREATE TABLE IF NOT EXISTS library_publication_visualizations" in schema
    assert '@app.get("/v1/publication-visualizations/readiness")' in main
    assert '@app.post("/v1/publication-visualizations/build")' in main
    assert '@app.get("/v1/publication-visualizations")' in main
    assert '@app.post("/v1/publication-visualizations/core-handoff")' in main


def test_platform_core_visual_research_handoff_is_bounded():
    core = (ROOT / "library-backend/app/platform_core.py").read_text()
    module = (ROOT / "library-backend/app/publication_visualizations.py").read_text()
    assert '"visual-research-object.create": ("POST", "/v1/cross-product-visual-research")' in core
    assert 'operation="visual-research-object.create"' in module
    assert '"automatic_truth_promotion": False' in module
    assert '"machine_inferred_graph_edges": False' in module


def test_research_library_wordpress_delivery_exists():
    main = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    renderer = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-publication-visualizations.php").read_text()
    bridge = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    assert "class-sc-library-publication-visualizations.php" in main
    assert "SC_Library_Publication_Visualizations" in main
    assert "sc_library_publication_visualizations" in renderer
    assert "Accessible data view" in renderer
    assert "/v1/publication-visualizations" in bridge
