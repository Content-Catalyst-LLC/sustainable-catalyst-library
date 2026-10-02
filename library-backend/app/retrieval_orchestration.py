from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .hybrid_retrieval import hybrid_search_records
from .neural_reranking import rerank_candidates, reranking_readiness
from .query import facets
from .retrieval_evaluation import rerank_results
from .semantic import semantic_readiness

LIBRARY_VERSION = "5.76.0"
BACKEND_VERSION = "2.87.0"
CONTRACT = "sc-library-python-retrieval-orchestration/1.0"
READINESS_CONTRACT = "sc-library-python-retrieval-orchestration-readiness/1.0"
PLAN_CONTRACT = "sc-library-retrieval-plan/1.0"
RESULT_CONTRACT = "sc-library-retrieval-result/1.0"

MODES = {"hybrid", "lexical", "semantic"}
RERANK_MODES = {"none", "neural", "adaptive"}
SORTS = {"relevance", "updated", "newest", "oldest", "title"}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _text(value: Any, limit: int) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _optional_text(value: Any, limit: int) -> str | None:
    cleaned = _text(value, limit)
    return cleaned or None


def _optional_year(value: Any) -> int | None:
    if value in (None, ""):
        return None
    year = int(value)
    if year < 1000 or year > 3000:
        raise ValueError("year must be between 1000 and 3000")
    return year


def guardrails() -> dict[str, bool]:
    return {
        "python_is_retrieval_orchestration_authority": True,
        "wordpress_php_is_retrieval_orchestration_authority": False,
        "retrieval_orchestration_changes_source_content": False,
        "ranking_score_is_truth_probability": False,
        "semantic_similarity_is_evidence_equivalence": False,
        "neural_reranking_is_optional": True,
        "neural_reranking_failure_falls_back_to_baseline": True,
        "adaptive_ranking_requires_explicit_profile": True,
        "adaptive_ranking_changes_truth_status": False,
        "empty_query_browsing_is_deterministic": True,
        "legacy_search_routes_converge_on_orchestrator": True,
        "platform_core_enrichment_is_descriptive": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "public_search_contract": "/api/library/v1/search",
        "orchestration_contract": "/api/library/v1/retrieval/search",
        "modes": sorted(MODES),
        "rerank_modes": sorted(RERANK_MODES),
        "sorts": sorted(SORTS),
        "stages": [
            "query-normalization",
            "retrieval-plan",
            "lexical-or-hybrid-or-semantic-candidate-generation",
            "optional-neural-or-adaptive-reranking",
            "pagination",
            "result-envelope",
        ],
        "components": {
            "lexical": "query.search_records",
            "hybrid": "hybrid_retrieval.hybrid_search_records",
            "semantic": "representation-search-via-hybrid-runtime",
            "neural_reranking": "neural_reranking.rerank_candidates",
            "adaptive_reranking": "retrieval_evaluation.rerank_results",
            "facets": "query.facets",
        },
        "wordpress": {
            "role": "query-ui-and-api-client",
            "required": False,
            "authoritative": False,
        },
        "guardrails": guardrails(),
    }


def normalize_request(payload: dict[str, Any] | None) -> dict[str, Any]:
    raw = dict(payload or {})
    q = _text(raw.get("q"), 500)
    mode = _text(raw.get("mode") or "hybrid", 20).lower()
    if mode not in MODES:
        mode = "hybrid"
    sort = _text(raw.get("sort") or "relevance", 20).lower()
    if sort not in SORTS:
        sort = "relevance" if q else "updated"
    if not q and sort == "relevance":
        sort = "updated"
    rerank = _text(raw.get("rerank") or "none", 20).lower()
    if rerank not in RERANK_MODES:
        rerank = "none"
    limit = max(1, min(100, int(raw.get("limit", 20))))
    offset = max(0, min(100000, int(raw.get("offset", 0))))
    candidate_limit = int(raw.get("candidate_limit") or max(limit + offset, min(100, max(40, (limit + offset) * 3))))
    candidate_limit = max(limit + offset, min(100, candidate_limit))
    profile = raw.get("profile") if isinstance(raw.get("profile"), dict) else {}
    if rerank == "adaptive" and not profile:
        rerank = "none"
    normalized = {
        "q": q,
        "object_type": _optional_text(raw.get("object_type"), 80),
        "source_key": _optional_text(raw.get("source_key"), 191),
        "topic": _optional_text(raw.get("topic"), 500),
        "year_from": _optional_year(raw.get("year_from")),
        "year_to": _optional_year(raw.get("year_to")),
        "sort": sort,
        "mode": mode,
        "rerank": rerank,
        "include_core": bool(raw.get("include_core", True)),
        "limit": limit,
        "offset": offset,
        "candidate_limit": candidate_limit,
        "profile": profile,
    }
    if normalized["year_from"] is not None and normalized["year_to"] is not None and normalized["year_from"] > normalized["year_to"]:
        raise ValueError("year_from must be less than or equal to year_to")
    return normalized


def plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    normalized = normalize_request(payload)
    effective_rerank = normalized["rerank"]
    if not normalized["q"]:
        effective_rerank = "none"
    stages = ["query-normalization", f"{normalized['mode']}-candidate-generation"]
    if effective_rerank != "none":
        stages.append(f"{effective_rerank}-reranking")
    stages.extend(["pagination", "result-envelope"])
    basis = {
        "query": normalized["q"],
        "filters": {k: normalized[k] for k in ("object_type", "source_key", "topic", "year_from", "year_to")},
        "sort": normalized["sort"],
        "mode": normalized["mode"],
        "rerank": effective_rerank,
        "include_core": normalized["include_core"],
        "limit": normalized["limit"],
        "offset": normalized["offset"],
        "candidate_limit": normalized["candidate_limit"],
        "profile": normalized["profile"],
        "stages": stages,
    }
    fp = _fp(basis)
    return {
        "schema": PLAN_CONTRACT,
        "plan_id": "retrieval-plan:" + fp[:32],
        "plan_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "normalized": normalized,
        "effective_rerank": effective_rerank,
        "stages": stages,
        "guardrails": guardrails(),
    }


def execute(payload: dict[str, Any] | None) -> dict[str, Any]:
    p = plan(payload)
    n = p["normalized"]
    candidate_limit = n["candidate_limit"] if p["effective_rerank"] != "none" else n["limit"]
    candidate_offset = 0 if p["effective_rerank"] != "none" else n["offset"]
    baseline = hybrid_search_records(
        n["q"],
        n["object_type"],
        n["source_key"],
        n["topic"],
        n["year_from"],
        n["year_to"],
        n["sort"],
        candidate_limit,
        candidate_offset,
        mode=n["mode"],
        include_core=n["include_core"],
    )
    results = [dict(x) for x in (baseline.get("results") or [])]
    rerank_meta: dict[str, Any] = {"mode": "none", "applied": False}
    if p["effective_rerank"] == "neural" and n["q"]:
        reranked = rerank_candidates(n["q"], results)
        results = [dict(x) for x in (reranked.get("results") or results)]
        rerank_meta = {
            "mode": "neural",
            "applied": bool(reranked.get("available")),
            "reason": reranked.get("reason"),
            "run_id": reranked.get("run_id"),
            "candidate_count": reranked.get("candidate_count"),
            "provider_scored_candidate_count": reranked.get("provider_scored_candidate_count"),
            "guardrails": reranked.get("guardrails"),
        }
    elif p["effective_rerank"] == "adaptive":
        results = rerank_results(results, n["profile"])
        rerank_meta = {
            "mode": "adaptive",
            "applied": bool(n["profile"].get("active")),
            "profile_id": n["profile"].get("profile_id"),
            "candidate_count": len(results),
            "guardrails": {
                "result_set_preserved": True,
                "ranking_changes_truth_status": False,
            },
        }

    if p["effective_rerank"] != "none":
        results = results[n["offset"]: n["offset"] + n["limit"]]
    else:
        results = results[: n["limit"]]

    total = baseline.get("total")
    return {
        "schema": RESULT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "query": n["q"],
        "filters": {
            "object_type": n["object_type"],
            "source_key": n["source_key"],
            "topic": n["topic"],
            "year_from": n["year_from"],
            "year_to": n["year_to"],
            "sort": n["sort"],
        },
        "mode": n["mode"],
        "limit": n["limit"],
        "offset": n["offset"],
        "total": total,
        "results": results,
        "retrieval": baseline.get("retrieval", {}),
        "reranking": rerank_meta,
        "orchestration": {
            "authority": "python-backend",
            "plan_id": p["plan_id"],
            "plan_fingerprint_sha256": p["plan_fingerprint_sha256"],
            "stages": p["stages"],
            "candidate_limit": n["candidate_limit"],
            "legacy_route_convergence": True,
        },
        "guardrails": guardrails(),
    }


def facet_snapshot() -> dict[str, Any]:
    payload = facets()
    return {
        "schema": "sc-library-retrieval-facets/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "facets": payload,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    semantic = semantic_readiness()
    neural = reranking_readiness()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready",
        "authority": "python-backend",
        "wordpress_required": False,
        "lexical_search": "ready",
        "hybrid_search": "ready",
        "semantic_search": {
            "state": semantic.get("state", "unknown") if isinstance(semantic, dict) else "unknown",
            "available": bool((semantic or {}).get("configured") or (semantic or {}).get("available")) if isinstance(semantic, dict) else False,
            "fallback": "lexical",
        },
        "neural_reranking": {
            "state": neural.get("state", "unknown") if isinstance(neural, dict) else "unknown",
            "configured": bool(neural.get("configured")) if isinstance(neural, dict) else False,
            "fallback": "baseline-ranking",
        },
        "modes": sorted(MODES),
        "rerank_modes": sorted(RERANK_MODES),
        "guardrails": guardrails(),
    }
