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

    assert '"6.36.0"' in main or '"2.36.0"' in main
    assert '/research/intelligence' in interface
    assert '/research/intelligence' in nav
    assert '/api/library/v1/research-intelligence' in main
    assert 'research-intelligence' in web
    assert 'Library–Librarian Unified Research Intelligence' in html
    assert 'SC_LIBRARY_WEB_BIND_PORT:-8095' in compose
    assert "SC_LIBRARY_RESEARCH_INTELLIGENCE_ROUTE', '/research/intelligence'" in plugin


def test_preserves_prior_release_surfaces():
    main = (ROOT / "library-backend/app/main.py").read_text()
    assert '/api/library/v1/research-rooms' in main
    assert '/api/library/v1/research-object-exchange' in main
    assert '/api/library/v1/research-package-readiness' in main
    assert '/api/library/v1/research-review-versioning' in main
    assert '/api/library/v1/research-lineage-graph' in main
    assert '/api/library/v1/research-project-workspace' in main
