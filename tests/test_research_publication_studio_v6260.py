from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_v6260_release_markers():
    main = (ROOT / "library-backend/app/main.py").read_text()
    api = (ROOT / "library-backend/app/independent_api.py").read_text()
    web = (ROOT / "library-web/assets/app.js").read_text()
    html = (ROOT / "library-web/index.html").read_text()
    wp = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    module = (ROOT / "library-backend/app/research_publication_studio.py").read_text()

    assert 'research_publication_studio_contract' in main
    assert '/api/library/v1/research-publication/readiness' in main
    assert '/api/library/v1/research-publication/export' in main
    assert '"research-publication-studio"' in api
    assert 'data-view="publication"' in html
    assert '/research/publication' in html
    assert 'Research Publication Studio' in html
    assert 'loadResearchPublicationStudio' in web
    assert 'research-publication/readiness' in web
    assert "SC_LIBRARY_VERSION', '6.26.0'" in wp
    assert "SC_LIBRARY_RESEARCH_PUBLICATION_STUDIO_ROUTE', '/research/publication'" in wp
    assert 'LIBRARY_VERSION = "6.26.0"' in module
    assert 'BACKEND_VERSION = "3.26.0"' in module


def test_generation_markers():
    assert (ROOT / "library-backend/app/__init__.py").read_text().strip() == '__version__ = "3.26.0"'
    assert 'webVersion: "2.26.0"' in (ROOT / "library-web/config.js").read_text()
    assert 'Web v2.26.0 · API v1' in (ROOT / "library-web/index.html").read_text()
    assert '__version__ = "1.26.0"' in (ROOT / "clients/python/sustainable_catalyst_library/__init__.py").read_text()
    assert 'version = "1.26.0"' in (ROOT / "clients/python/pyproject.toml").read_text()
    assert '"version": "1.26.0"' in (ROOT / "clients/javascript/package.json").read_text()
