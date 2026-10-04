from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]


def test_release_contract():
    main = (ROOT / 'library-backend/app/main.py').read_text()
    web = (ROOT / 'library-web/index.html').read_text()
    js = (ROOT / 'library-web/assets/app.js').read_text()
    nav = (ROOT / 'library-backend/app/navigation_service.py').read_text()
    assert 'historical_event_timeline' in main
    assert '/api/library/v1/historical-archives/timeline-workspace/readiness' in main
    assert '/research/archives/timeline' in web
    assert 'Research Timeline & Historical Event Workspace' in web
    assert 'Web v2.15.0 · API v1' in web
    assert '/historical-archives/timeline-workspace/compare' in js
    assert '"mode": "historical-timeline"' in nav
