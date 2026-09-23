from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_and_backend_version():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    readme = (ROOT / "sustainable-catalyst-library/readme.txt").read_text()
    backend = (ROOT / "library-backend/app/__init__.py").read_text()
    assert "SC_LIBRARY_VERSION" in plugin
    assert "Stable tag:" in readme


def test_scientific_knowledge_map_backend_contract_exists():
    main = (ROOT / "library-backend/app/main.py").read_text()
    engine = (ROOT / "library-backend/app/publication_knowledge_maps.py").read_text()
    assert '@app.get("/v1/publication-knowledge-maps/readiness")' in main
    assert '@app.get("/v1/publication-knowledge-maps")' in main
    assert 'KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-knowledge-map/1.0"' in engine
    assert '"source-span-cooccurrence"' in engine
    assert '"embedding-cosine-similarity"' in engine
    assert '"semantic_edges_are_truth_claims": False' in engine
    assert '"llm_inferred_edges": False' in engine


def test_research_library_interactive_renderer_is_packaged():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    view = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    css = (ROOT / "sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5170.css").read_text()
    js = (ROOT / "sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5170.js").read_text()
    assert "class-sc-library-knowledge-landscape.php" in plugin
    assert "SC_Library_Knowledge_Landscape" in plugin
    assert "sc_library_knowledge_landscape" in view
    assert "Knowledge Landscape" in view
    assert "Semantic Overlay" in view
    assert "source-span measured" in view.lower()
    assert "sc-kl__workspace" in css
    assert "embedding-cosine-similarity" in js
    assert "data-sc-kl-layout" in view


def test_wordpress_backend_proxy_contract_exists():
    bridge = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    assert "/v1/publication-knowledge-maps/readiness" in bridge
    assert "/v1/publication-knowledge-maps" in bridge
    assert "publication_knowledge_map" in bridge
    assert "/backend/publication-knowledge-map" in bridge


def test_platform_core_alignment_is_explicit_without_claiming_core_render_execution():
    engine = (ROOT / "library-backend/app/publication_knowledge_maps.py").read_text()
    assert '"/v1/visual-runtime/unified"' in engine
    assert '"/v1/visual-runtime/grammar"' in engine
    assert '"/v1/visual-runtime/linked-views"' in engine
    assert '"/v1/visual-runtime/query"' in engine
    assert '"/v1/visualization"' in engine
    assert '"governed_visual_reasoning_authority": "platform-core"' in engine
