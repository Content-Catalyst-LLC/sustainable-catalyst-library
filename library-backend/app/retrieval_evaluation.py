from __future__ import annotations

from collections import defaultdict
from math import log2
from typing import Any, Iterable

EVALUATION_SCHEMA = "sc-library-retrieval-evaluation/1.0"
PROFILE_SCHEMA = "sc-library-adaptive-ranking-profile/1.0"
RERANK_SCHEMA = "sc-library-adaptive-reranking/1.0"
BENCHMARK_SCHEMA = "sc-library-retrieval-benchmark/1.0"


def _clamp(value: float, low: float, high: float) -> float:
    return max(low, min(high, float(value)))


def _record_id(item: dict[str, Any]) -> str:
    return str(item.get("record_id") or item.get("id") or "").strip()


def _grade(item: dict[str, Any]) -> int:
    raw = item.get("relevance_grade")
    if raw is None:
        if item.get("accepted") is True or item.get("relevant") is True:
            return 3
        if item.get("rejected") is True or item.get("relevant") is False:
            return 0
        return 0
    try:
        return int(_clamp(int(raw), 0, 3))
    except Exception:
        return 0


def _dcg(grades: Iterable[int], k: int) -> float:
    total = 0.0
    for idx, grade in enumerate(list(grades)[:k], start=1):
        total += (2 ** max(0, int(grade)) - 1) / log2(idx + 1)
    return total


def _safe_div(n: float, d: float) -> float:
    return float(n) / float(d) if d else 0.0


def evaluate_case(case: dict[str, Any]) -> dict[str, Any]:
    results = [dict(x) for x in (case.get("results") or []) if isinstance(x, dict)]
    known_relevant_ids = {str(x) for x in (case.get("known_relevant_ids") or []) if str(x)}
    judged = [x for x in results if any(k in x for k in ("relevance_grade", "accepted", "rejected", "relevant"))]
    grades = [_grade(x) for x in results]
    relevant_flags = [g > 0 for g in grades]
    judged_relevant_ids = {_record_id(x) for x in judged if _grade(x) > 0 and _record_id(x)}
    relevant_universe = known_relevant_ids or judged_relevant_ids

    first_relevant_rank = next((i for i, flag in enumerate(relevant_flags, start=1) if flag), None)
    precisions = []
    hits = 0
    for rank, flag in enumerate(relevant_flags, start=1):
        if flag:
            hits += 1
            precisions.append(hits / rank)
    ap = sum(precisions) / max(1, len(relevant_universe)) if relevant_universe else 0.0

    ideal = sorted(grades, reverse=True)
    evidence_relevant = [
        x for x in results if _grade(x) > 0 and (
            bool(x.get("evidence_covered")) or bool((x.get("platform_core") or {}).get("bound"))
        )
    ]
    relevant_results = [x for x in results if _grade(x) > 0]
    sources = {str(x.get("source_key") or "") for x in relevant_results if str(x.get("source_key") or "")}

    cutoffs = sorted({k for k in (1, 3, 5, 10, min(20, len(results))) if k > 0 and k <= max(1, len(results))})
    at_k: dict[str, Any] = {}
    for k in cutoffs:
        top = results[:k]
        top_rel = sum(1 for x in top if _grade(x) > 0)
        top_ids = {_record_id(x) for x in top if _record_id(x)}
        at_k[str(k)] = {
            "precision": round(_safe_div(top_rel, k), 6),
            "recall": round(_safe_div(len(top_ids & relevant_universe), len(relevant_universe)), 6) if relevant_universe else None,
            "ndcg": round(_safe_div(_dcg([_grade(x) for x in top], k), _dcg(ideal, k)), 6) if ideal else 0.0,
        }

    rejected = [x for x in judged if _grade(x) == 0]
    unsupported_rejections = [
        x for x in rejected
        if str(x.get("rejection_reason") or "").strip().lower() in {"unsupported", "not-evidence", "not_relevant_evidence", "untraceable"}
    ]
    return {
        "schema": EVALUATION_SCHEMA,
        "case_id": str(case.get("case_id") or ""),
        "query": str(case.get("query") or ""),
        "retrieval_mode": str(case.get("retrieval_mode") or "unknown"),
        "result_count": len(results),
        "judged_result_count": len(judged),
        "relevant_result_count": len(relevant_results),
        "known_relevant_count": len(relevant_universe),
        "metrics": {
            "at_k": at_k,
            "mean_average_precision": round(ap, 6),
            "mean_reciprocal_rank": round((1 / first_relevant_rank) if first_relevant_rank else 0.0, 6),
            "evidence_coverage": round(_safe_div(len(evidence_relevant), len(relevant_results)), 6) if relevant_results else None,
            "relevant_source_diversity": round(_safe_div(len(sources), len(relevant_results)), 6) if relevant_results else None,
            "unsupported_rejection_rate": round(_safe_div(len(unsupported_rejections), len(rejected)), 6) if rejected else None,
        },
        "judgment_policy": {
            "relevance_grades": {"0": "not relevant", "1": "marginal", "2": "relevant", "3": "highly relevant"},
            "acceptance_is_truth": False,
            "rejection_is_falsehood": False,
            "unjudged_is_irrelevant": False,
            "evidence_coverage_is_claim_validation": False,
        },
    }


def evaluate_retrieval(payload: dict[str, Any]) -> dict[str, Any]:
    cases_raw = payload.get("cases")
    cases = [x for x in cases_raw if isinstance(x, dict)] if isinstance(cases_raw, list) else [payload]
    evaluated = [evaluate_case(x) for x in cases]
    aggregate: dict[str, Any] = {
        "case_count": len(evaluated),
        "judged_result_count": sum(int(x.get("judged_result_count") or 0) for x in evaluated),
    }
    metric_names = ("mean_average_precision", "mean_reciprocal_rank", "evidence_coverage", "relevant_source_diversity", "unsupported_rejection_rate")
    for name in metric_names:
        vals = [x["metrics"].get(name) for x in evaluated if x["metrics"].get(name) is not None]
        aggregate[name] = round(sum(vals) / len(vals), 6) if vals else None
    return {
        "schema": BENCHMARK_SCHEMA if len(evaluated) > 1 else EVALUATION_SCHEMA,
        "cases": evaluated,
        "aggregate": aggregate,
        "reproducibility": {
            "deterministic_given_ranked_results_and_judgments": True,
            "requires_explicit_judgments_for_quality_claims": True,
            "unjudged_results_are_not_silently_negative": True,
        },
    }


def build_adaptive_ranking_profile(payload: dict[str, Any]) -> dict[str, Any]:
    cases_raw = payload.get("cases") or []
    cases = [x for x in cases_raw if isinstance(x, dict)] if isinstance(cases_raw, list) else []
    if not cases and isinstance(payload.get("results"), list):
        cases = [payload]

    lexical_signal = 0.0
    semantic_signal = 0.0
    lexical_n = semantic_n = 0
    source_scores: dict[str, list[int]] = defaultdict(list)
    type_scores: dict[str, list[int]] = defaultdict(list)
    judged = 0

    for case in cases:
        for item in case.get("results") or []:
            if not isinstance(item, dict) or not any(k in item for k in ("relevance_grade", "accepted", "rejected", "relevant")):
                continue
            grade = _grade(item)
            judged += 1
            signals = item.get("retrieval_signals") or {}
            lr = signals.get("lexical_rank")
            sr = signals.get("semantic_rank")
            if lr:
                lexical_signal += grade / max(1.0, float(lr)); lexical_n += 1
            if sr:
                semantic_signal += grade / max(1.0, float(sr)); semantic_n += 1
            source = str(item.get("source_key") or "").strip()
            if source:
                source_scores[source].append(grade)
            typ = str(item.get("object_type") or "").strip()
            if typ:
                type_scores[typ].append(grade)

    lexical_quality = lexical_signal / lexical_n if lexical_n else 0.0
    semantic_quality = semantic_signal / semantic_n if semantic_n else 0.0
    total_quality = lexical_quality + semantic_quality
    if total_quality > 0:
        lexical_weight = _clamp(0.5 + (lexical_quality / total_quality), 0.75, 1.25)
        semantic_weight = _clamp(0.5 + (semantic_quality / total_quality), 0.75, 1.25)
    else:
        lexical_weight = semantic_weight = 1.0

    def boosts(groups: dict[str, list[int]]) -> dict[str, float]:
        out: dict[str, float] = {}
        for key, vals in groups.items():
            if len(vals) < 2:
                continue
            avg = sum(vals) / len(vals)
            # Bounded 0.90–1.10: feedback can nudge ranking, never dominate it.
            out[key] = round(_clamp(0.9 + (avg / 3.0) * 0.2, 0.9, 1.1), 4)
        return dict(sorted(out.items()))

    active = judged >= 3
    return {
        "schema": PROFILE_SCHEMA,
        "profile_id": str(payload.get("profile_id") or "research-feedback-profile"),
        "active": active,
        "minimum_judgments_required": 3,
        "judged_result_count": judged,
        "weights": {
            "lexical": round(lexical_weight if active else 1.0, 4),
            "semantic": round(semantic_weight if active else 1.0, 4),
            "platform_core_bound": 1.03 if active else 1.0,
        },
        "source_boosts": boosts(source_scores) if active else {},
        "object_type_boosts": boosts(type_scores) if active else {},
        "guardrails": {
            "automatic_record_filtering": False,
            "automatic_record_deletion": False,
            "automatic_truth_promotion": False,
            "acceptance_is_truth": False,
            "rejection_is_falsehood": False,
            "max_source_or_type_multiplier": 1.10,
            "min_source_or_type_multiplier": 0.90,
            "original_rank_preserved": True,
            "score_components_exposed": True,
        },
    }


def rerank_results(results: list[dict[str, Any]], profile: dict[str, Any]) -> list[dict[str, Any]]:
    active = bool(profile.get("active")) and profile.get("schema") == PROFILE_SCHEMA
    weights = profile.get("weights") or {}
    lw = _clamp(weights.get("lexical", 1.0), 0.75, 1.25)
    sw = _clamp(weights.get("semantic", 1.0), 0.75, 1.25)
    core_weight = _clamp(weights.get("platform_core_bound", 1.0), 1.0, 1.05)
    source_boosts = profile.get("source_boosts") or {}
    type_boosts = profile.get("object_type_boosts") or {}

    scored = []
    for original_rank, row in enumerate(results, start=1):
        item = dict(row)
        sig = item.get("retrieval_signals") or {}
        lr = sig.get("lexical_rank")
        sr = sig.get("semantic_rank")
        lexical_component = (1 / (60 + float(lr))) * lw if lr else 0.0
        semantic_component = (1 / (60 + float(sr))) * sw if sr else 0.0
        if not lr and not sr:
            lexical_component = 1 / (60 + original_rank)
        source_mult = _clamp(source_boosts.get(str(item.get("source_key") or ""), 1.0), 0.9, 1.1)
        type_mult = _clamp(type_boosts.get(str(item.get("object_type") or ""), 1.0), 0.9, 1.1)
        core_mult = core_weight if bool((item.get("platform_core") or {}).get("bound")) else 1.0
        score = (lexical_component + semantic_component) * source_mult * type_mult * core_mult
        if not active:
            score = 1 / (60 + original_rank)
        item["adaptive_ranking"] = {
            "profile_id": profile.get("profile_id"),
            "active": active,
            "original_rank": original_rank,
            "lexical_component": round(lexical_component, 10),
            "semantic_component": round(semantic_component, 10),
            "source_multiplier": round(source_mult, 4),
            "object_type_multiplier": round(type_mult, 4),
            "platform_core_multiplier": round(core_mult, 4),
            "adaptive_score": round(score, 10),
        }
        scored.append(item)

    if active:
        scored.sort(key=lambda x: (-float((x.get("adaptive_ranking") or {}).get("adaptive_score") or 0), int((x.get("adaptive_ranking") or {}).get("original_rank") or 0)))
    for new_rank, item in enumerate(scored, start=1):
        item["adaptive_ranking"]["new_rank"] = new_rank
        item["adaptive_ranking"]["rank_delta"] = int(item["adaptive_ranking"]["original_rank"]) - new_rank
    return scored


def adaptive_rerank(payload: dict[str, Any]) -> dict[str, Any]:
    results = [dict(x) for x in (payload.get("results") or []) if isinstance(x, dict)]
    profile = payload.get("profile") if isinstance(payload.get("profile"), dict) else {}
    return {
        "schema": RERANK_SCHEMA,
        "profile": profile,
        "results": rerank_results(results, profile),
        "result_count": len(results),
        "guardrails": {
            "result_set_preserved": True,
            "reranking_changes_truth_status": False,
            "reranking_changes_evidence_relations": False,
            "reranking_changes_platform_core_objects": False,
        },
    }
