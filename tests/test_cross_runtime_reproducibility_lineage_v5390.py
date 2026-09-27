from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
BACKEND=ROOT/'library-backend'

def read(rel): return (ROOT/rel).read_text(encoding='utf-8')

def test_release_identity_and_runtime_versions():
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.39.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.39.0');" in plugin
    assert '__version__ = "2.50.0"' in read('library-backend/app/__init__.py')
    assert 'version  = "0.1.0"' in read('library-backend/go-ingestion-runtime/main.go')
    assert 'version = "0.2.0"' in read('library-backend/native-graph-runtime/Cargo.toml')

def test_lineage_routes_contracts_and_capabilities():
    main=read('library-backend/app/main.py'); mod=read('library-backend/app/execution_lineage.py')
    for route in ['/v1/runtime/reproducibility/status','/v1/runtime/reproducibility/environment','/v1/runtime/reproducibility/record','/v1/runtime/reproducibility/verify']: assert route in main
    for token in ['sc-library-cross-runtime-reproducibility/1.0','sc-library-execution-environment/1.0','sc-library-execution-lineage/1.0','sc-library-reproducibility-record/1.0','sc-library-runtime-verification/1.0']: assert token in mod
    for capability in ['"cross_runtime_reproducibility_execution_lineage": True','"execution_environment_capture": True','"execution_parent_child_lineage": True','"execution_replay_verification": True','"cross_runtime_equivalence_claim": False']: assert capability in main

def test_guardrails_are_explicit():
    mod=read('library-backend/app/execution_lineage.py')
    for token in ['"matching_output_fingerprints_prove_scientific_equivalence": False','"different_output_fingerprints_prove_one_runtime_is_wrong": False','"runtime_replay_proves_source_truth": False','"execution_lineage_automatically_promotes_core_objects": False','"cross_runtime_equivalence_proven": False']: assert token in mod

def test_wordpress_console_proxy_and_publication_surface():
    proxy=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    for route in ['/backend/runtime-reproducibility-status','/backend/runtime-reproducibility-record','/backend/runtime-reproducibility-verify']: assert route in proxy
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'class-sc-library-execution-lineage.php' in plugin and 'SC_Library_Execution_Lineage' in plugin
    console=read('sustainable-catalyst-library/includes/class-sc-library-execution-lineage.php')
    assert "SHORTCODE = 'sc_library_execution_lineage'" in console
    corpus=read('library-backend/app/publication_corpus_maps.py')
    assert '"key": "runtime-execution-lineage"' in corpus

def test_schema_files_parse_and_sources_remain_clean():
    for name in ['execution-environment.json','execution-lineage.json','reproducibility-record.json','runtime-verification.json']: json.loads(read('docs/schemas/'+name))
    assert not (BACKEND/'native-graph-runtime/target').exists()
    assert not (BACKEND/'go-ingestion-runtime/sc-library-ingestion-runtime').exists()
