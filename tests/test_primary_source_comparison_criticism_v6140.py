from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/'library-backend/app/main.py').read_text(); web=(ROOT/'library-web/index.html').read_text(); js=(ROOT/'library-web/assets/app.js').read_text()
    assert 'primary_source_comparison_criticism' in main
    assert '/api/library/v1/historical-archives/source-criticism/readiness' in main
    assert '/research/archives/compare' in web
    assert 'Primary-Source Comparison & Source Criticism Workspace' in web
    assert 'Web v2.14.0 · API v1' in web
    assert '/historical-archives/source-criticism/matrix' in js
