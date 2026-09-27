from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKEND = ROOT / "library-backend"


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity_and_polyglot_versions():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.38.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.38.0');" in plugin
    assert '__version__ = "2.49.0"' in read("library-backend/app/__init__.py")
    assert 'version  = "0.1.0"' in read("library-backend/go-ingestion-runtime/main.go")
    assert 'version = "0.2.0"' in read("library-backend/native-graph-runtime/Cargo.toml")


def test_contract_routes_and_capabilities():
    main = read("library-backend/app/main.py")
    module = read("library-backend/app/unified_runtime_contract.py")
    for route in [
        '/v1/runtime/research/status',
        '/v1/runtime/research/resolve',
        '/v1/runtime/research/execute',
    ]:
        assert route in main
    for token in [
        'sc-library-research-runtime-contract/1.0',
        'sc-library-runtime-descriptor/1.0',
        'sc-library-runtime-routing-decision/1.0',
        'sc-library-runtime-execution-envelope/1.0',
    ]:
        assert token in module
    for capability in [
        '"unified_research_runtime_contract": True',
        '"unified_runtime_discovery": True',
        '"unified_runtime_routing": True',
        '"unified_runtime_execution_envelopes": True',
        '"unified_runtime_explicit_fallback_policy": True',
    ]:
        assert capability in main


def test_runtime_boundary_guardrails():
    module = read("library-backend/app/unified_runtime_contract.py")
    for boundary in [
        '"runtime_selection_implies_evidence_quality": False',
        '"runtime_success_implies_result_truth": False',
        '"faster_runtime_implies_better_research": False',
        '"cross_runtime_result_is_automatically_equivalent": False',
        '"runtime_execution_automatically_promotes_core_objects": False',
        '"durable_governed_research_objects": "platform-core"',
    ]:
        assert boundary in module


def test_wordpress_proxy_console_and_publication_surface():
    proxy = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    for route in ['/backend/unified-runtime-status', '/backend/unified-runtime-resolve', '/backend/unified-runtime-execute']:
        assert route in proxy
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert 'class-sc-library-unified-runtime.php' in plugin
    assert 'SC_Library_Unified_Runtime' in plugin
    console = read("sustainable-catalyst-library/includes/class-sc-library-unified-runtime.php")
    assert "SHORTCODE = 'sc_library_unified_runtime'" in console
    corpus = read("library-backend/app/publication_corpus_maps.py")
    assert '"key": "unified-runtime-contract"' in corpus


def test_runtime_sources_remain_clean():
    assert not (BACKEND / "native-graph-runtime/target").exists()
    assert not (BACKEND / "go-ingestion-runtime/sc-library-ingestion-runtime").exists()
