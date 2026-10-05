from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_contract():
    main=(ROOT/"library-backend/app/main.py").read_text()
    web=(ROOT/"library-web/index.html").read_text()
    app=(ROOT/"library-web/assets/app.js").read_text()
    nav=(ROOT/"library-backend/app/navigation_service.py").read_text()
    api=(ROOT/"library-backend/app/independent_api.py").read_text()
    assert "corpus_computational_linguistics_workspace" in main
    assert '/api/library/v1/corpus-workspace/readiness' in main
    assert '/api/library/v1/corpus-workspace/ngrams' in main
    assert '/api/library/v1/corpus-workspace/cooccurrence' in main
    assert '/research/corpus' in web
    assert 'Corpus & Computational Linguistics Workspace' in web
    assert 'Web v2.18.0 · API v1' in web
    assert 'view === "corpus"' in app
    assert 'loadCorpusWorkspace()' in app
    assert '"research-corpus"' in nav
    assert '"Corpus"' in nav
    assert 'computational-linguistics-readiness' in api
