from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKEND = ROOT / "library-backend"


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity_and_runtime_continuity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.37.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.37.0');" in plugin
    assert '__version__ = "2.48.0"' in read("library-backend/app/__init__.py")
    assert 'version  = "0.1.0"' in read("library-backend/go-ingestion-runtime/main.go")
    assert 'version = "0.2.0"' in read("library-backend/native-graph-runtime/Cargo.toml")


def test_backend_routes_capabilities_and_contracts():
    main = read("library-backend/app/main.py")
    module = read("library-backend/app/research_corpus_builder.py")
    for route in [
        '/v1/research-corpora/build',
        '/v1/research-corpora/export',
        '/v1/publication-knowledge-maps/research-corpus',
    ]:
        assert route in main
    for token in [
        'sc-library-research-corpus/1.0',
        'sc-library-corpus-manifest/1.0',
        'sc-library-dataset-export/1.0',
        'sc-library-dataset-row-provenance/1.0',
    ]:
        assert token in module
    for capability in [
        '"research_corpus_builder": True',
        '"research_corpus_row_level_provenance": True',
        '"research_corpus_json_export": True',
        '"research_corpus_jsonl_export": True',
        '"research_corpus_csv_export": True',
    ]:
        assert capability in main


def test_guardrails_preserve_library_core_boundary():
    module = read("library-backend/app/research_corpus_builder.py")
    for boundary in [
        '"corpus_membership_implies_evidence_quality": False',
        '"corpus_membership_implies_truth": False',
        '"corpus_membership_implies_consensus": False',
        '"corpus_exclusion_implies_falsehood": False',
        '"dataset_row_is_governed_core_object": False',
        '"automatic_platform_core_promotion": False',
        '"durable_authority": "platform-core"',
    ]:
        assert boundary in module


def test_wordpress_proxy_console_and_publication_surface():
    proxy = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    for route in ['/backend/research-corpus-build', '/backend/research-corpus-export', '/backend/publication-research-corpus']:
        assert route in proxy
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert 'class-sc-library-research-corpus-builder.php' in plugin
    assert 'SC_Library_Research_Corpus_Builder' in plugin
    console = read("sustainable-catalyst-library/includes/class-sc-library-research-corpus-builder.php")
    assert "SHORTCODE = 'sc_library_research_corpus_builder'" in console
    corpus = read("library-backend/app/publication_corpus_maps.py")
    assert '"key": "research-corpus-builder"' in corpus
    assert 'build-research-corpus' in corpus
    assert 'export-corpus-dataset' in corpus


def test_schema_files_and_build_artifact_cleanup():
    assert (ROOT / "docs/schemas/research-corpus.json").exists()
    assert (ROOT / "docs/schemas/dataset-export.json").exists()
    assert not (BACKEND / "native-graph-runtime/target").exists()
    assert not (BACKEND / "go-ingestion-runtime/sc-library-ingestion-runtime").exists()
