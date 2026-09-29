import json

import httpx

from app.neural_reranking import NeuralRerankerClient, evaluate_reranking, rerank_candidates


def _results():
    return [
        {"record_id": "a", "title": "Carbon accounting", "abstract": "emissions inventories", "hybrid_score": 0.4},
        {"record_id": "b", "title": "Climate finance", "abstract": "investment", "hybrid_score": 0.3},
        {"record_id": "c", "title": "Grid emissions", "abstract": "electricity carbon factors", "hybrid_score": 0.2},
    ]


def test_disabled_reranker_preserves_order_without_fake_scores():
    client = NeuralRerankerClient(provider="disabled")
    out = rerank_candidates("carbon emissions", _results(), client=client)
    assert out["available"] is False
    assert [x["record_id"] for x in out["results"]] == ["a", "b", "c"]
    assert all(x["neural_reranking"]["provider_relevance_score"] is None for x in out["results"])
    assert all(x["neural_reranking"]["rank_delta"] == 0 for x in out["results"])


def test_rerank_compatible_scores_reorder_but_preserve_baseline_rank():
    def handler(request: httpx.Request) -> httpx.Response:
        body = json.loads(request.content.decode())
        assert body["query"] == "carbon emissions"
        assert len(body["documents"]) == 3
        return httpx.Response(200, json={"results": [
            {"index": 2, "relevance_score": 0.99},
            {"index": 0, "relevance_score": 0.75},
            {"index": 1, "relevance_score": 0.25},
        ]})
    client = NeuralRerankerClient(
        provider="rerank_compatible", api_key="x", model="test-reranker",
        api_url="https://rerank.example.test/v1/rerank", transport=httpx.MockTransport(handler),
    )
    out = rerank_candidates("carbon emissions", _results(), client=client)
    assert out["available"] is True
    assert [x["record_id"] for x in out["results"]] == ["c", "a", "b"]
    by_id = {x["record_id"]: x["neural_reranking"] for x in out["results"]}
    assert by_id["a"]["baseline_rank"] == 1 and by_id["a"]["neural_rank"] == 2
    assert by_id["c"]["baseline_rank"] == 3 and by_id["c"]["neural_rank"] == 1
    assert by_id["c"]["provider_score_is_probability"] is False


def test_reranking_evaluation_requires_explicit_judgments_for_quality_claims():
    baseline = _results()
    reranked = [baseline[2], baseline[0], baseline[1]]
    out = evaluate_reranking({
        "query": "carbon emissions",
        "baseline_results": baseline,
        "reranked_results": reranked,
        "judgments": [
            {"record_id": "a", "relevance_grade": 2},
            {"record_id": "b", "relevance_grade": 0},
            {"record_id": "c", "relevance_grade": 3},
        ],
        "known_relevant_ids": ["a", "c"],
    })
    assert out["schema"] == "sc-library-neural-reranking-evaluation/1.0"
    assert out["judgment_count"] == 3
    assert out["interpretation"]["quality_claim_requires_explicit_judgments"] is True
    assert out["interpretation"]["positive_metric_delta_is_not_truth_validation"] is True
