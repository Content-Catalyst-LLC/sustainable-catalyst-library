from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_preserves_v5171_corpus_capability():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    backend = (ROOT / "library-backend/app/__init__.py").read_text()
    readme = (ROOT / "sustainable-catalyst-library/readme.txt").read_text()
    assert "SC_LIBRARY_VERSION" in plugin
    assert "__version__" in backend
    assert "Stable tag:" in readme

def test_shortcode_defaults_to_live_corpus_not_current_page():
    code = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    assert "'scope' => 'corpus'" in code
    assert "'source_key' => 'wordpress-main'" in code
    bridge = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    assert "publication_corpus_knowledge_map" in bridge
    assert "publication-corpus-knowledge-map" in code
    assert "if ('publication' === $scope)" in code
    assert "No eligible published publications are currently indexed for this Research Library corpus." in code


def test_backend_has_live_corpus_contract_and_route():
    engine = (ROOT / "library-backend/app/publication_corpus_maps.py").read_text()
    main = (ROOT / "library-backend/app/main.py").read_text()
    assert 'CORPUS_KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-corpus-knowledge-map/1.0"' in engine
    assert 'source_key: str = "wordpress-main"' in engine
    assert '"publication-topic-cooccurrence"' in engine
    assert '"corpus_is_live_library_records": True' in engine
    assert '@app.get("/v1/publication-knowledge-maps/corpus")' in main
    assert '"publication_corpus_integration": True' in main


def test_relationships_are_measured_not_inferred():
    engine = (ROOT / "library-backend/app/publication_corpus_maps.py").read_text()
    assert '"llm_inferred_edges": False' in engine
    assert '"semantic_edges_are_truth_claims": False' in engine
    assert 'stored-embedding-cosine-similarity-if-available' in engine
