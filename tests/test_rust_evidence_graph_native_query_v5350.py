from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
BACKEND=ROOT/'library-backend'

def text(rel): return (ROOT/rel).read_text(encoding='utf-8')

def test_release_identity_and_native_runtime_v02():
    plugin=text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.35.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.35.0');" in plugin
    assert '__version__ = "2.46.0"' in text('library-backend/app/__init__.py')
    cargo=text('library-backend/native-graph-runtime/Cargo.toml')
    rust=text('library-backend/native-graph-runtime/src/main.rs')
    assert 'version = "0.2.0"' in cargo
    assert 'const VERSION: &str = "0.2.0";' in rust
    for capability in ['neighborhood','reachability','connected-components','subgraph','structural-stats']:
        assert capability in rust

def test_backend_query_contract_routes_and_capabilities():
    query=text('library-backend/app/native_graph_query.py')
    main=text('library-backend/app/main.py')
    assert 'sc-library-native-graph-query/1.0' in text('library-backend/app/native_graph_runtime.py')
    assert 'query_native_graph' in query
    assert '@app.post("/v1/runtime/native-graph/query")' in main
    assert '@app.post("/v1/publication-knowledge-maps/native-graph-query")' in main
    for capability in [
        '"native_rust_evidence_graph_acceleration": True',
        '"native_graph_filtered_neighborhoods": True',
        '"native_graph_reachability": True',
        '"native_graph_connected_components": True',
        '"native_graph_induced_subgraphs": True',
        '"native_graph_structural_statistics": True',
    ]:
        assert capability in main

def test_python_retains_policy_and_guardrails():
    query=text('library-backend/app/native_graph_query.py')
    for boundary in [
        '"connectivity_implies_evidence_support": False',
        '"connectivity_implies_causality": False',
        '"component_membership_implies_consensus": False',
        '"degree_or_connectivity_is_quality_score": False',
        '"analytical_relationships_are_opt_in": True',
        '"python_selects_relationship_policy_before_native_execution": True',
        '"platform_core_durable_authority": True',
    ]:
        assert boundary in query

def test_wordpress_proxy_and_console():
    proxy=text('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    console=text('sustainable-catalyst-library/includes/class-sc-library-native-graph-runtime.php')
    assert '/backend/publication-native-graph-query' in proxy
    assert '/v1/publication-knowledge-maps/native-graph-query' in proxy
    assert "QUERY_SHORTCODE = 'sc_library_native_graph_query'" in console
    assert 'VERSION = \'5.35.0\'' in console
    assert 'sc-library-native-graph-runtime-v5350.js' in console

def test_corpus_surface_and_rust_target_cleanup():
    corpus=text('library-backend/app/publication_corpus_maps.py')
    assert '"key": "native-graph-query"' in corpus
    assert 'native-neighborhood-query' in corpus
    assert 'native_graph_connectivity_implies_causality' in corpus
    assert not (BACKEND/'native-graph-runtime/target').exists()
    assert 'library-backend/native-graph-runtime/target/' in text('.gitignore')
