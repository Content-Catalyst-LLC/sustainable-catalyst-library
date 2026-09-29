from pathlib import Path
import ast
import importlib.util
import json
import sys
import types

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def _load_projection_module():
    pkg = types.ModuleType("app")
    pkg.__path__ = []
    sys.modules.setdefault("app", pkg)
    db = types.ModuleType("app.db")
    db.get_pool = lambda: None
    gov = types.ModuleType("app.embedding_governance")
    gov.current_embedding_specification = lambda: {"fingerprint_sha256": "f" * 64}
    rep = types.ModuleType("app.representation_search")
    rep.similar_records = lambda *a, **k: {"seed": {}, "specification": {}, "results": [], "count": 0}
    sys.modules["app.db"] = db
    sys.modules["app.embedding_governance"] = gov
    sys.modules["app.representation_search"] = rep
    spec = importlib.util.spec_from_file_location("app.publication_embedding_maps", ROOT / "library-backend/app/publication_embedding_maps.py")
    mod = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = mod
    spec.loader.exec_module(mod)
    return mod


def test_release_identity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.43.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.43.0');" in plugin
    assert '__version__ = "2.54.0"' in read("library-backend/app/__init__.py")


def test_embedding_map_contract_and_guardrails():
    source = read("library-backend/app/publication_embedding_maps.py")
    ast.parse(source)
    for token in [
        'sc-library-publication-embedding-map/1.0',
        'sc-library-semantic-knowledge-landscape/1.0',
        'sc-library-semantic-neighborhood/1.0',
        'sc-library-deterministic-pca-projection/1.0',
        '"same_specification_only": True',
        '"current_content_only": True',
        '"spatial_proximity_is_evidence": False',
        '"spatial_proximity_is_truth": False',
        '"spatial_proximity_is_causality": False',
        '"automatic_platform_core_promotion": False',
    ]:
        assert token in source


def test_projection_is_deterministic_and_bounded():
    mod = _load_projection_module()
    vectors = [
        [1.0, 0.0, 0.2, 0.1],
        [0.9, 0.1, 0.25, 0.0],
        [0.0, 1.0, 0.1, 0.4],
        [0.1, 0.85, 0.0, 0.5],
    ]
    first = mod.deterministic_pca_2d(vectors)
    second = mod.deterministic_pca_2d(vectors)
    assert first["coordinates"] == second["coordinates"]
    assert len(first["coordinates"]) == 4
    assert all(-1.0 <= x <= 1.0 and -1.0 <= y <= 1.0 for x, y in first["coordinates"])
    assert first["explained_energy_ratio"][0] >= 0.0


def test_semantic_edges_are_thresholded_symmetric_and_not_truth_claims():
    mod = _load_projection_module()
    ids = ["a", "b", "c"]
    vectors = [[1.0, 0.0], [0.99, 0.01], [0.0, 1.0]]
    edges, neighborhoods = mod.semantic_edges_and_neighborhoods(ids, vectors, threshold=0.8, neighbors_per_point=2, max_edges=10)
    assert len(edges) == 1
    assert {edges[0]["source"], edges[0]["target"]} == {"a", "b"}
    assert edges[0]["relationship_basis"] == "embedding-cosine-similarity"
    assert edges[0]["truth_assertion"] is False
    assert neighborhoods["a"][0]["record_id"] == "b"


def test_specification_selection_never_mixes_vector_spaces():
    mod = _load_projection_module()
    rows = [
        {"specification_fingerprint": "a"},
        {"specification_fingerprint": "a"},
        {"specification_fingerprint": "b"},
    ]
    selected, reason = mod._choose_specification(rows, "b")
    assert selected == "a"
    assert reason == "dominant-stored-current-content-specification"
    selected, reason = mod._choose_specification(rows + [{"specification_fingerprint": "b"}], "b")
    assert selected == "b"
    assert reason == "current-configured-specification"


def test_backend_routes_and_health_capabilities_exist():
    main = read("library-backend/app/main.py")
    for route in [
        '/v1/publication-embedding-maps/readiness',
        '/v1/publication-embedding-maps/map',
        '/v1/publication-embedding-maps/{record_id}/neighborhood',
    ]:
        assert route in main
    for capability in [
        '"publication_embedding_maps": True',
        '"publication_embedding_map_deterministic_pca": True',
        '"publication_embedding_map_same_specification_only": True',
        '"publication_embedding_map_current_content_only": True',
        '"publication_embedding_map_proximity_is_evidence": False',
        '"publication_embedding_map_proximity_is_truth": False',
        '"publication_embedding_map_proximity_is_causality": False',
    ]:
        assert capability in main


def test_corpus_knowledge_landscape_integrates_embedding_map():
    source = read("library-backend/app/publication_corpus_maps.py")
    assert "build_publication_embedding_map" in source
    assert '"publication_embedding_map": publication_embedding_map' in source
    assert '"key": "semantic-embedding-map"' in source
    assert 'a.get("specification_fingerprint") != b.get("specification_fingerprint")' in source
    assert 'source_representation_id=a.get("representation_id")' in source


def test_wordpress_embedding_map_view_and_proxy_routes_exist():
    proxy = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    landscape = read("sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php")
    js = read("sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js")
    for route in [
        "/backend/publication-embedding-maps/readiness",
        "/backend/publication-embedding-maps/map",
        "/backend/publication-embedding-maps/neighborhood",
    ]:
        assert route in proxy
    assert 'data-sc-kl-view="semantic-embedding-map"' in landscape
    assert "this.embeddingMap=this.data.publication_embedding_map||{}" in js
    assert "embeddingLayout()" in js
    assert "this.layout==='embedding'" in js


def test_v543_contract_schemas_parse():
    for name in ["publication-embedding-map.json", "semantic-knowledge-landscape.json", "semantic-neighborhood.json"]:
        data = json.loads(read(f"docs/schemas/{name}"))
        assert data["$schema"].endswith("2020-12/schema")
        assert data["type"] == "object"


def test_v542_neural_reranking_guardrails_remain_intact():
    source = read("library-backend/app/neural_reranking.py")
    assert '"rerank_score_is_evidence": False' in source
    assert '"rerank_score_is_truth": False' in source
    assert '"automatic_candidate_filtering": False' in source
