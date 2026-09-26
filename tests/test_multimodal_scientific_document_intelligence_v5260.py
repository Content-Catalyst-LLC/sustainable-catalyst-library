from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
BACKEND = ROOT / "library-backend"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_5260_with_backend_2370():
    main = read(PLUGIN / "sustainable-catalyst-library.php")
    backend = read(BACKEND / "app" / "__init__.py")
    assert "Version: 5.26.0" in main
    assert "define('SC_LIBRARY_VERSION', '5.26.0');" in main
    assert '__version__ = "2.37.0"' in backend


def test_scientific_document_runtime_has_required_contracts_and_boundaries():
    module = read(BACKEND / "app" / "scientific_document_intelligence.py")
    assert 'sc-library-scientific-document-intelligence/1.0' in module
    assert 'sc-library-scientific-object/1.0' in module
    assert 'sc-library-scientific-object-graph-overlay/1.0' in module
    for kind in ("figure", "chart", "table", "equation", "caption", "appendix", "supplement", "dataset"):
        assert f'"{kind}"' in module
    assert '"values_inferred_from_pixels": False' in module
    assert '"claims_inferred_from_object": False' in module
    assert '"equation_solved": False' in module
    assert '"ocr_text_automatically_treated_as_verified": False' in module


def test_backend_routes_and_health_capabilities_are_present():
    main = read(BACKEND / "app" / "main.py")
    assert '@app.post("/v1/scientific-document-intelligence/analyze")' in main
    assert '@app.get("/v1/scientific-document-intelligence/record/{record_id}")' in main
    assert '"scientific_document_intelligence": True' in main
    assert '"scientific_object_graph_overlay": True' in main
    assert '"scientific_visual_values_inferred_from_pixels": False' in main


def test_corpus_integrates_scientific_document_graph_overlay():
    corpus = read(BACKEND / "app" / "publication_corpus_maps.py")
    assert "build_scientific_corpus_overlay" in corpus
    assert '"scientific_document_intelligence": scientific_document_intelligence' in corpus
    assert '"key": "scientific-objects"' in corpus
    assert '"structured-scientific-object-extraction"' in corpus
    assert '"scientific_values_inferred_from_pixels": False' in corpus


def test_research_graph_treats_scientific_relations_as_source_grounded():
    graph = read(BACKEND / "app" / "research_graph_pathfinding.py")
    for relation in (
        "contains-scientific-object",
        "contains-source-span",
        "explicit-scientific-cross-reference",
        "caption-describes",
        "dataset-link",
        "supplementary-material-link",
    ):
        assert f'"{relation}"' in graph
    assert '"contains-scientific-object": "explicit-source-lineage"' in graph
    assert '"explicit-scientific-cross-reference": "explicit-source-lineage"' in graph


def test_wordpress_proxy_and_scientific_objects_view_are_present():
    proxy = read(PLUGIN / "includes" / "class-sc-library-python-backend.php")
    landscape = read(PLUGIN / "includes" / "class-sc-library-knowledge-landscape.php")
    assert "/backend/scientific-document-intelligence" in proxy
    assert "/v1/scientific-document-intelligence/analyze" in proxy
    assert "/v1/scientific-document-intelligence/record/" in proxy
    assert "public const VERSION = '5.26.0';" in landscape
    assert 'data-sc-kl-view="scientific-objects"' in landscape
    for kind in ("figure", "chart", "table", "equation", "caption", "appendix", "supplement", "dataset"):
        assert f'data-sc-kl-node-kind="{kind}"' in landscape


def test_frontend_can_filter_and_inspect_scientific_objects():
    js = read(PLUGIN / "assets" / "js" / "sc-library-knowledge-landscape-v5260.js")
    assert "this.view==='scientific-objects'" in js
    assert "contains-scientific-object" in js
    assert "explicit-scientific-cross-reference" in js
    assert "No values inferred from pixels" in js
    assert "table_row_count" in js
    assert "equation_latex" in js


def test_v525_pathfinding_and_v524_synthesis_boundaries_are_retained():
    graph = read(BACKEND / "app" / "research_graph_pathfinding.py")
    synthesis = read(BACKEND / "app" / "evidence_synthesis.py")
    assert 'sc-library-research-graph-query/1.0' in graph
    assert 'sc-library-evidence-pathfinding/1.0' in graph
    assert '"analytical_relationships_opt_in": True' in graph
    assert '"path_implies_truth": False' in graph
    assert '"consensus_inferred": False' in synthesis
    assert '"hypotheses_inferred": False' in synthesis
    assert '"evidence_balance_is_truth_score": False' in synthesis
    assert '"durable_synthesis_authority": "platform-core"' in synthesis
