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

    assert '"6.34.0"' in main or '"2.34.0"' in main
    assert '/research/exchange' in interface
    assert '/research/exchange' in nav
    assert '/api/library/v1/research-object-exchange' in main
    assert 'research-object-exchange' in web
    assert 'Portable Research Object &amp; Exchange Format' in html
    assert 'SC_LIBRARY_WEB_BIND_PORT:-8095' in compose
    assert "SC_LIBRARY_RESEARCH_OBJECT_EXCHANGE_ROUTE', '/research/exchange'" in plugin


def test_preserves_prior_release_surfaces():
    main = (ROOT / "library-backend/app/main.py").read_text()
    assert '/api/library/v1/research-package-readiness' in main
    assert '/api/library/v1/research-review-versioning' in main
    assert '/api/library/v1/research-lineage-graph' in main
    assert '/api/library/v1/research-project-workspace' in main
