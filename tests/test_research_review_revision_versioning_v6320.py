from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_v6320_static_markers():
    assert '6.32.0' in (ROOT / 'library-backend/app/research_review_revision_versioning.py').read_text()
    assert '/research/project/review' in (ROOT / 'library-web/index.html').read_text()
    app = (ROOT / 'library-web/assets/app.js').read_text()
    assert '/research-review-versioning/bootstrap' in app
    assert 'research-review-versioning' in (ROOT / 'library-backend/app/independent_api.py').read_text()
    assert "SC_LIBRARY_RESEARCH_REVIEW_VERSIONING_ROUTE" in (ROOT / 'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    assert '${SC_LIBRARY_WEB_BIND_PORT:-8095}' in (ROOT / 'library-web/compose.yml').read_text()
