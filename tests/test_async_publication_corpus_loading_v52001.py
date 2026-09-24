from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_patch_identity_and_async_shell_contract():
    plugin=(ROOT/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    backend=(ROOT/"library-backend/app/__init__.py").read_text()
    php=(ROOT/"sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    bridge=(ROOT/"sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    js=(ROOT/"sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5200.js").read_text()
    css=(ROOT/"sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5200.css").read_text()
    main=(ROOT/"library-backend/app/main.py").read_text()
    # Preserve the v5.20.0.1 async transport capability without freezing later release identity.
    assert "asynchronous Publication Library corpus loading" in plugin
    assert "__version__" in backend
    assert "$async_corpus = true" in php
    assert "sc-kl__config" in php and "data-sc-kl-load-state" in php
    assert "rest_url(SC_Library_Python_Backend::REST_NAMESPACE" in php
    assert "wp_remote_post" in bridge
    assert "Content-Type' => 'application/json'" in bridge
    assert "publication_record_ids($max_publications)" in bridge
    assert '@app.post("/v1/publication-knowledge-maps/corpus")' in main
    assert '"publication_async_corpus_transport": True' in main
    assert "fetchCorpus" in js and "method:'POST'" in js
    assert "Publication corpus unavailable" in js and "data-sc-kl-retry" in php
    assert ".sc-kl__load-state" in css

def test_corpus_page_render_no_longer_calls_python_synchronously():
    php=(ROOT/"sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    corpus_branch=php.split("} else {",1)[1]
    assert "publication_corpus_knowledge_map(" not in corpus_branch.split("$this->enqueue_assets();",1)[0]
