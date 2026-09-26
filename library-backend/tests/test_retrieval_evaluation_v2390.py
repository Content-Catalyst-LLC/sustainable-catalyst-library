from app.retrieval_evaluation import (
    EVALUATION_SCHEMA, PROFILE_SCHEMA, RERANK_SCHEMA,
    adaptive_rerank, build_adaptive_ranking_profile, evaluate_case, evaluate_retrieval,
)


def result(rid, rank, grade, *, source="a", obj="publication", lr=None, sr=None, core=False):
    return {
        "record_id": rid,
        "title": rid,
        "source_key": source,
        "object_type": obj,
        "relevance_grade": grade,
        "retrieval_signals": {"lexical_rank": lr, "semantic_rank": sr},
        "platform_core": {"bound": core},
        "rank": rank,
    }


def test_evaluation_metrics_are_deterministic_and_do_not_claim_truth():
    case = {
        "case_id": "c1", "query": "heat health", "known_relevant_ids": ["a", "c"],
        "results": [result("a", 1, 3, lr=1, sr=2, core=True), result("b", 2, 0, lr=2, sr=1), result("c", 3, 2, lr=3, sr=3)],
    }
    out = evaluate_case(case)
    assert out["schema"] == EVALUATION_SCHEMA
    assert out["metrics"]["at_k"]["3"]["precision"] == 0.666667
    assert out["metrics"]["at_k"]["3"]["recall"] == 1.0
    assert out["metrics"]["mean_reciprocal_rank"] == 1.0
    assert out["judgment_policy"]["acceptance_is_truth"] is False
    assert out["judgment_policy"]["rejection_is_falsehood"] is False


def test_unjudged_results_are_not_silently_counted_as_negative_judgments():
    out = evaluate_case({"query":"q","results":[{"record_id":"a"},{"record_id":"b","relevance_grade":3}]})
    assert out["judged_result_count"] == 1
    assert out["relevant_result_count"] == 1


def test_benchmark_aggregates_cases():
    out = evaluate_retrieval({"cases":[
        {"query":"q1","results":[result("a",1,3,lr=1,sr=2)]},
        {"query":"q2","results":[result("b",1,0,lr=1,sr=1),result("c",2,3,lr=2,sr=2)]},
    ]})
    assert out["aggregate"]["case_count"] == 2
    assert out["reproducibility"]["unjudged_results_are_not_silently_negative"] is True


def test_profile_requires_minimum_judgments_and_is_bounded():
    inactive = build_adaptive_ranking_profile({"results":[result("a",1,3,lr=1,sr=2),result("b",2,0,lr=2,sr=1)]})
    assert inactive["schema"] == PROFILE_SCHEMA
    assert inactive["active"] is False
    active = build_adaptive_ranking_profile({"results":[
        result("a",1,3,source="s1",lr=1,sr=3),
        result("b",2,3,source="s1",lr=2,sr=4),
        result("c",3,0,source="s2",lr=5,sr=1),
        result("d",4,0,source="s2",lr=6,sr=2),
    ]})
    assert active["active"] is True
    assert 0.75 <= active["weights"]["lexical"] <= 1.25
    assert 0.75 <= active["weights"]["semantic"] <= 1.25
    assert active["guardrails"]["automatic_record_filtering"] is False
    assert active["guardrails"]["automatic_truth_promotion"] is False
    assert all(0.9 <= x <= 1.1 for x in active["source_boosts"].values())


def test_rerank_preserves_result_set_and_original_rank():
    rows=[result("a",1,3,lr=5,sr=1),result("b",2,3,lr=1,sr=5),result("c",3,0,lr=3,sr=3)]
    profile={
        "schema":PROFILE_SCHEMA,"profile_id":"p","active":True,
        "weights":{"lexical":0.75,"semantic":1.25,"platform_core_bound":1.0},
        "source_boosts":{},"object_type_boosts":{},
    }
    out=adaptive_rerank({"results":rows,"profile":profile})
    assert out["schema"] == RERANK_SCHEMA
    assert {x["record_id"] for x in out["results"]} == {"a","b","c"}
    assert all("original_rank" in x["adaptive_ranking"] for x in out["results"])
    assert out["guardrails"]["result_set_preserved"] is True
    assert out["guardrails"]["reranking_changes_truth_status"] is False
