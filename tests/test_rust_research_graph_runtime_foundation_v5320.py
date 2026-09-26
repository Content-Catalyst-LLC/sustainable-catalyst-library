from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
BACKEND=ROOT/'library-backend'

def read(p): return p.read_text(encoding='utf-8')

def test_release_identity_and_native_crate():
    main=read(PLUGIN/'sustainable-catalyst-library.php'); backend=read(BACKEND/'app/__init__.py')
    assert 'Version: 5.32.0' in main
    assert "define('SC_LIBRARY_VERSION', '5.32.0');" in main
    assert '__version__ = "2.43.0"' in backend
    assert (BACKEND/'native-graph-runtime/Cargo.toml').exists()
    rust=read(BACKEND/'native-graph-runtime/src/main.rs')
    assert 'sc-library-native-graph-runtime/1.0' in rust
    assert 'pathfind' in rust

def test_python_adapter_and_safe_fallback_contract():
    adapter=read(BACKEND/'app/native_graph_runtime.py')
    graph=read(BACKEND/'app/research_graph_pathfinding.py')
    assert 'NATIVE_GRAPH_CONTRACT = "sc-library-native-graph-runtime/1.0"' in adapter
    assert 'native_path_steps' in adapter
    assert 'runtime_requested' in graph and 'python-fallback' in graph
    assert 'native_runtime_changes_research_semantics' in graph

def test_docker_builds_and_installs_rust_runtime():
    docker=read(BACKEND/'Dockerfile')
    assert 'FROM rust:1.90-slim-bookworm AS rust-builder' in docker
    assert 'cargo build --release --locked' in docker
    assert 'sc-library-graph-runtime' in docker

def test_backend_routes_and_capabilities():
    main=read(BACKEND/'app/main.py')
    assert '@app.get("/v1/runtime/native-graph/status")' in main
    assert '@app.post("/v1/runtime/native-graph/pathfind")' in main
    assert '"native_rust_graph_runtime_foundation": True' in main
    assert '"native_graph_runtime_python_fallback": True' in main

def test_wordpress_runtime_console_and_proxy():
    main=read(PLUGIN/'sustainable-catalyst-library.php')
    proxy=read(PLUGIN/'includes/class-sc-library-python-backend.php')
    console=read(PLUGIN/'includes/class-sc-library-native-graph-runtime.php')
    assert 'class-sc-library-native-graph-runtime.php' in main
    assert '/backend/native-graph-runtime-status' in proxy
    assert '/v1/runtime/native-graph/status' in proxy
    assert "SHORTCODE = 'sc_library_native_graph_runtime'" in console

def test_previous_integrity_boundaries_are_retained():
    graph=read(BACKEND/'app/research_graph_pathfinding.py')
    assert '"path_implies_truth": False' in graph
    assert '"path_implies_causality": False' in graph
    assert '"analytical_relationships_opt_in": True' in graph
    assert '"methodology_description_edges_are_default_evidence_paths": False' in graph
