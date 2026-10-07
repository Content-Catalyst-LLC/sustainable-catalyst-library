from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_markers_and_library7_gate_wiring():
    main = (ROOT / "library-backend/app/main.py").read_text()
    api = (ROOT / "library-backend/app/independent_api.py").read_text()
    web = (ROOT / "library-web/assets/app.js").read_text()
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()

    assert '__version__ = "3.39.0"' in (ROOT / "library-backend/app/__init__.py").read_text()
    assert 'webVersion: "2.39.0"' in (ROOT / "library-web/config.js").read_text()
    assert '/api/library/v1/library7-certification' in main
    assert '/api/library/v1/library7-certification' in api
    assert "Library 7 readiness gate" in web
    assert "SC_LIBRARY_VERSION', '6.39.0'" in plugin
    assert "SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '7.0.0'" in plugin
    assert "SC_LIBRARY7_PRODUCTION_CERTIFICATION_AUTHORITY" in plugin


def test_preserves_v638_and_prior_research_surfaces():
    main = (ROOT / "library-backend/app/main.py").read_text()
    for path in [
        '/api/library/v1/research-audit',
        '/api/library/v1/research-federation',
        '/api/library/v1/research-intelligence',
        '/api/library/v1/research-rooms',
        '/api/library/v1/research-object-exchange',
        '/api/library/v1/research-package-readiness',
        '/api/library/v1/research-review-versioning',
        '/api/library/v1/research-lineage-graph',
        '/api/library/v1/research-project-workspace',
        '/api/library/v1/cross-product-certification',
        '/api/library/v1/workspace-integration',
    ]:
        assert path in main


def test_web_bind_and_api_stability_boundaries_remain_intact():
    compose = (ROOT / "library-web/compose.yml").read_text()
    nginx = (ROOT / "library-web/nginx.conf").read_text()
    module = (ROOT / "library-backend/app/library7_production_consolidation_certification.py").read_text()
    assert 'SC_LIBRARY_WEB_BIND_PORT:-8095' in compose
    assert 'location /api/library/' in nginx
    assert 'api_v1_remains_stability_boundary' in module
    assert 'wordpress_required": False' in module
    assert 'automatic_database_migration": False' in module
