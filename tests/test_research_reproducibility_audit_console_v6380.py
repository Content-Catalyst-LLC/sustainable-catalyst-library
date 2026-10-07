from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_markers_and_surfaces():
    main = (ROOT / "library-backend/app/main.py").read_text()
    interface = (ROOT / "library-backend/app/research_interface.py").read_text()
    nav = (ROOT / "library-backend/app/navigation_service.py").read_text()
    web = (ROOT / "library-web/assets/app.js").read_text()
    html = (ROOT / "library-web/index.html").read_text()
    compose = (ROOT / "library-web/compose.yml").read_text()
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()

    assert '"2.38.0"' in main or '"1.38.0"' in main
    assert '/research/audit' in interface
    assert '/research/audit' in nav
    assert '/api/library/v1/research-audit' in main
    assert 'research-audit' in web
    assert 'Research Reproducibility &amp; Audit Console' in html
    assert 'SC_LIBRARY_WEB_BIND_PORT:-8095' in compose
    assert "SC_LIBRARY_RESEARCH_REPRODUCIBILITY_AUDIT_ROUTE', '/research/audit'" in plugin


def test_preserves_prior_release_surfaces():
    main = (ROOT / "library-backend/app/main.py").read_text()
    for path in [
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
