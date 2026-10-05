from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def read(path): return (ROOT / path).read_text()


def test_release_markers_and_surface():
    main = read("library-backend/app/main.py")
    web = read("library-web/index.html")
    wp = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "cross_product_research_handoff_certification" in main
    assert "/api/library/v1/cross-product-certification" in main
    assert 'data-view="integration-certification"' in web
    assert "Cross-Product Research Handoff &amp; Contract Certification" in web
    assert "Web v2.29.0 · API v1" in web
    assert "SC_LIBRARY_CROSS_PRODUCT_CERTIFICATION_ROUTE" in wp


def test_guardrails_and_next_release():
    source = read("library-backend/app/cross_product_research_handoff_certification.py")
    assert '"automatic_cross_product_push_delivery": False' in source
    assert '"automatic_remote_execution": False' in source
    assert '"remote_runtime_observation_required_for_live_runtime_certification": True' in source
    assert '"database_migration_required": False' in source
    assert '"wordpress_required": False' in source
    assert '"next_release": "6.30.0"' in source
