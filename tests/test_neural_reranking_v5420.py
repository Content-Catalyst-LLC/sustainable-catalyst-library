from pathlib import Path
import ast
import json

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.42.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.42.0');" in plugin
    assert '__version__ = "2.53.0"' in read("library-backend/app/__init__.py")


def test_neural_reranking_module_contracts_and_guardrails():
    source = read("library-backend/app/neural_reranking.py")
    ast.parse(source)
    for token in [
        'sc-library-neural-reranking/1.0',
        'sc-library-neural-reranker-specification/1.0',
        'sc-library-neural-reranking-evaluation/1.0',
        'provider-relevance-score-not-probability',
        '"rerank_score_is_evidence": False',
        '"rerank_score_is_truth": False',
        '"automatic_candidate_filtering": False',
        '"automatic_platform_core_promotion": False',
        '"result_set_preserved": True',
        '"baseline_rank_preserved": True',
        '"provider_score_is_probability": False',
    ]:
        assert token in source


def test_disabled_provider_preserves_baseline_and_never_fabricates_scores():
    source = read("library-backend/app/neural_reranking.py")
    assert 'SUPPORTED_PROVIDERS = {"disabled", "rerank_compatible"}' in source
    assert 'raise RerankingError("neural reranking provider is not configured")' in source
    assert '"fake_neural_scores": False' in source
    assert '"reason": failure_reason if failure_reason else (None if available else "reranking-provider-not-configured")' in source
    assert 'block["neural_rank"] = new_rank if available and score_map else None' in source
    assert 'block["rank_delta"] = (int(block["baseline_rank"]) - new_rank) if available and score_map else 0' in source


def test_reranking_preserves_baseline_lineage_and_score_components():
    source = read("library-backend/app/neural_reranking.py")
    for token in [
        '"baseline_rank": baseline_rank',
        '"baseline_score": baseline_score',
        '"candidate_text_hash_sha256"',
        '"provider_relevance_score": relevance_score',
        '"provider": client.provider',
        '"model": client.model or None',
        '"specification_fingerprint_sha256"',
        '"neural_rank"',
        '"rank_delta"',
    ]:
        assert token in source


def test_existing_retrieval_evaluation_is_reused_for_baseline_comparison():
    source = read("library-backend/app/neural_reranking.py")
    assert "from .retrieval_evaluation import evaluate_case" in source
    assert '"retrieval_mode": "baseline"' in source
    assert '"retrieval_mode": "neural-reranked"' in source
    assert '"metric_deltas_reranked_minus_baseline"' in source
    assert '"quality_claim_requires_explicit_judgments": True' in source
    assert '"positive_metric_delta_is_not_truth_validation": True' in source


def test_backend_routes_and_health_capabilities_exist():
    main = read("library-backend/app/main.py")
    for route in [
        '/v1/neural-reranking/readiness',
        '/v1/neural-reranking/rerank',
        '/v1/neural-reranking/evaluate',
        '/v1/search/neural-reranked',
    ]:
        assert route in main
    for capability in [
        '"neural_reranking": True',
        '"neural_reranking_baseline_rank_preserved": True',
        '"neural_reranking_score_components_exposed": True',
        '"neural_reranking_retrieval_evaluation": True',
        '"neural_reranking_automatic_filtering": False',
        '"neural_reranking_evidence_promotion": False',
        '"neural_reranking_truth_promotion": False',
        '"neural_reranking_score_is_probability": False',
    ]:
        assert capability in main


def test_wordpress_proxy_routes_exist():
    source = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    for route in [
        "/backend/neural-reranking/readiness",
        "/backend/neural-reranking/rerank",
        "/backend/neural-reranking/evaluate",
        "/backend/search/neural-reranked",
    ]:
        assert route in source
    for method in [
        "proxy_neural_reranking_readiness",
        "proxy_neural_reranking",
        "proxy_neural_reranking_evaluate",
        "proxy_neural_reranked_search",
    ]:
        assert method in source


def test_configuration_is_explicit_and_disabled_by_default():
    settings = read("library-backend/app/settings.py")
    env = read("library-backend/.env.example")
    assert 'SC_LIBRARY_RERANK_PROVIDER", "disabled"' in settings
    assert 'rerank_max_candidates: int = _as_int("SC_LIBRARY_RERANK_MAX_CANDIDATES", 40, 5, 100)' in settings
    for token in [
        "SC_LIBRARY_RERANK_PROVIDER=disabled",
        "SC_LIBRARY_RERANK_API_KEY=",
        "SC_LIBRARY_RERANK_MODEL=rerank-model",
        "SC_LIBRARY_RERANK_API_URL=",
        "SC_LIBRARY_RERANK_TIMEOUT_SECONDS=12",
        "SC_LIBRARY_RERANK_MAX_CANDIDATES=40",
    ]:
        assert token in env


def test_v542_contract_schemas_parse():
    for name in [
        "neural-reranker-specification.json",
        "neural-reranking-response.json",
        "neural-reranking-evaluation.json",
    ]:
        data = json.loads(read(f"docs/schemas/{name}"))
        assert data["$schema"].endswith("2020-12/schema")
        assert data["type"] == "object"


def test_v541_similarity_guardrails_remain_intact():
    source = read("library-backend/app/representation_search.py")
    assert '"semantic_similarity_is_evidence": False' in source
    assert '"semantic_similarity_is_truth": False' in source
    assert '"semantic_similarity_is_causality": False' in source
    hybrid = read("library-backend/app/hybrid_retrieval.py")
    assert "AND e.content_hash=r.content_hash" in hybrid
    assert "AND e.specification_fingerprint=%s" in hybrid
