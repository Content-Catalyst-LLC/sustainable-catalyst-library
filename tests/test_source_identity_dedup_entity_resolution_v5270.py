from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
BACKEND = ROOT / "library-backend"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_5270_with_backend_2380():
    main = read(PLUGIN / "sustainable-catalyst-library.php")
    backend = read(BACKEND / "app" / "__init__.py")
    assert "Version: 5.27.0" in main
    assert "define('SC_LIBRARY_VERSION', '5.27.0');" in main
    assert '__version__ = "2.38.0"' in backend


def test_source_identity_runtime_has_non_destructive_boundaries():
    module = read(BACKEND / "app" / "source_identity_resolution.py")
    assert 'sc-library-source-identity-resolution/1.0' in module
    assert 'sc-library-source-identity-cluster/1.0' in module
    assert 'sc-library-entity-identity-resolution/1.0' in module
    assert '"automatic_record_merge": False' in module
    assert '"automatic_record_deletion": False' in module
    assert '"title_only_identity_merge": False' in module
    assert '"author_name_only_cross_source_merge": False' in module
    assert '"duplicate_candidate_is_identity_determination": False' in module


def test_backend_routes_and_health_capabilities_are_present():
    main = read(BACKEND / "app" / "main.py")
    assert '@app.post("/v1/source-identity/analyze")' in main
    assert '@app.post("/v1/publication-knowledge-maps/source-identity")' in main
    for capability in (
        '"source_identity_resolution": True',
        '"source_identity_exact_doi_resolution": True',
        '"source_identity_version_family_detection": True',
        '"author_orcid_resolution": True',
        '"institution_ror_resolution": True',
        '"source_identity_automatic_merge": False',
    ):
        assert capability in main


def test_corpus_integrates_source_identity_overlay_without_replacing_records():
    corpus = read(BACKEND / "app" / "publication_corpus_maps.py")
    assert "build_source_identity_resolution" in corpus
    assert '"source_identity_resolution": source_identity_resolution' in corpus
    assert '"key": "source-identity"' in corpus
    assert '"automatic_source_record_merge": False' in corpus
    assert '"automatic_duplicate_record_deletion": False' in corpus
    assert '"source_identity_is_evidence_truth": False' in corpus


def test_research_graph_has_identity_classes_and_candidate_opt_in():
    graph = read(BACKEND / "app" / "research_graph_pathfinding.py")
    assert '"member-of-source-identity": "deterministic-source-identity"' in graph
    assert '"authored-by-identity": "explicit-entity-identity"' in graph
    assert '"affiliated-institution-identity": "explicit-entity-identity"' in graph
    assert '"references-dataset-identity": "explicit-entity-identity"' in graph
    assert '"duplicate-candidate": "review-required-identity-candidate"' in graph
    assert '"duplicate-candidate"' in graph
    assert '"duplicate_candidate_requires_review": True' in graph


def test_wordpress_proxy_and_source_identity_view_are_present():
    proxy = read(PLUGIN / "includes" / "class-sc-library-python-backend.php")
    landscape = read(PLUGIN / "includes" / "class-sc-library-knowledge-landscape.php")
    assert "/backend/publication-source-identity" in proxy
    assert "/v1/publication-knowledge-maps/source-identity" in proxy
    assert "public const VERSION = '5.27.0';" in landscape
    assert 'data-sc-kl-view="source-identity"' in landscape
    for kind in ("source-identity", "author-identity", "institution-identity", "dataset-identity"):
        assert f'data-sc-kl-node-kind="{kind}"' in landscape


def test_frontend_exposes_identity_relationships_and_review_candidates():
    js = read(PLUGIN / "assets" / "js" / "sc-library-knowledge-landscape-v5270.js")
    assert "this.view==='source-identity'" in js
    assert "member-of-source-identity" in js
    assert "authored-by-identity" in js
    assert "affiliated-institution-identity" in js
    assert "references-dataset-identity" in js
    assert "duplicate-candidate" in js
    assert "No destructive merges" in js


def test_previous_major_capabilities_are_preserved():
    scientific = read(BACKEND / "app" / "scientific_document_intelligence.py")
    graph = read(BACKEND / "app" / "research_graph_pathfinding.py")
    synthesis = read(BACKEND / "app" / "evidence_synthesis.py")
    assert 'sc-library-scientific-document-intelligence/1.0' in scientific
    assert '"values_inferred_from_pixels": False' in scientific
    assert 'sc-library-research-graph-query/1.0' in graph
    assert 'sc-library-evidence-pathfinding/1.0' in graph
    assert '"analytical_relationships_opt_in": True' in graph
    assert '"consensus_inferred": False' in synthesis
    assert '"hypotheses_inferred": False' in synthesis
    assert '"durable_synthesis_authority": "platform-core"' in synthesis
