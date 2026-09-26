from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
BACKEND = ROOT / "library-backend"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_is_5250_with_backend_2360():
    main = read(PLUGIN / "sustainable-catalyst-library.php")
    backend = read(BACKEND / "app" / "__init__.py")
    assert "Version: 5.25.0" in main
    assert "define('SC_LIBRARY_VERSION', '5.25.0');" in main
    assert '__version__ = "2.36.0"' in backend


def test_backend_exposes_research_graph_and_pathfinding_contracts():
    module = read(BACKEND / "app" / "research_graph_pathfinding.py")
    assert 'sc-library-research-graph-query/1.0' in module
    assert 'sc-library-evidence-pathfinding/1.0' in module
    assert "def query_research_graph" in module
    assert "def find_research_paths" in module
    assert '"analytical_relationships_opt_in": True' in module
    assert '"path_implies_truth": False' in module
    assert '"path_implies_causality": False' in module
    assert '"path_implies_consensus": False' in module


def test_backend_routes_are_registered_and_capabilities_advertised():
    main = read(BACKEND / "app" / "main.py")
    assert '@app.post("/v1/publication-knowledge-maps/research-graph-query")' in main
    assert '@app.post("/v1/publication-knowledge-maps/evidence-pathfind")' in main
    assert '"publication_research_graph_query": True' in main
    assert '"publication_evidence_pathfinding": True' in main
    assert '"publication_analytical_path_edges_opt_in": True' in main


def test_corpus_payload_advertises_graph_manifest_and_view():
    corpus = read(BACKEND / "app" / "publication_corpus_maps.py")
    assert "build_research_graph_manifest" in corpus
    assert '"research_graph": research_graph' in corpus
    assert '"key": "evidence-paths"' in corpus
    assert '"research-graph-query"' in corpus
    assert '"evidence-pathfind"' in corpus


def test_wordpress_proxy_routes_graph_operations_without_core_truth_promotion():
    proxy = read(PLUGIN / "includes" / "class-sc-library-python-backend.php")
    assert "/backend/publication-research-graph-query" in proxy
    assert "/backend/publication-evidence-pathfind" in proxy
    assert "/v1/publication-knowledge-maps/research-graph-query" in proxy
    assert "/v1/publication-knowledge-maps/evidence-pathfind" in proxy


def test_research_library_ui_exposes_graph_query_and_evidence_pathfinder():
    landscape = read(PLUGIN / "includes" / "class-sc-library-knowledge-landscape.php")
    assert "public const VERSION = '5.25.0';" in landscape
    assert 'data-sc-kl-view="evidence-paths"' in landscape
    assert 'data-sc-kl-graph-query-run' in landscape
    assert 'data-sc-kl-pathfind' in landscape
    assert 'data-sc-kl-path-analytical' in landscape
    assert "research_graph_query_endpoint" in landscape
    assert "evidence_pathfind_endpoint" in landscape


def test_frontend_keeps_analytical_path_edges_explicitly_opt_in():
    js = read(PLUGIN / "assets" / "js" / "sc-library-knowledge-landscape-v5250.js")
    assert "include_analytical:includeAnalytical" in js
    assert "data-sc-kl-path-analytical" in js
    assert "No path was found within the selected hop limit" in js
    assert "Evidence pathfinder endpoint is unavailable." in js


def test_v524_synthesis_integrity_contract_is_retained():
    synthesis = read(BACKEND / "app" / "evidence_synthesis.py")
    assert '"consensus_inferred": False' in synthesis
    assert '"hypotheses_inferred": False' in synthesis
    assert '"competing_hypotheses_require_explicit_metadata": True' in synthesis
    assert '"evidence_balance_is_truth_score": False' in synthesis
    assert '"synthesis_creates_new_claims": False' in synthesis
    assert '"durable_synthesis_authority": "platform-core"' in synthesis
