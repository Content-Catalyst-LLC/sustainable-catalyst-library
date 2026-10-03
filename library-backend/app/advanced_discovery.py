from __future__ import annotations

from hashlib import sha256
import json
import unicodedata
from typing import Any, Callable

from .retrieval_orchestration import execute as execute_retrieval, readiness as retrieval_readiness
from .language_document_service import readiness as language_readiness

LIBRARY_VERSION = "6.4.0"
BACKEND_VERSION = "3.4.0"
WEB_VERSION = "2.4.0"
SDK_VERSION = "1.4.0"

CONTRACT = "sc-library-advanced-semantic-cross-language-discovery/1.0"
READINESS_CONTRACT = "sc-library-advanced-semantic-cross-language-discovery-readiness/1.0"
PLAN_CONTRACT = "sc-library-advanced-discovery-plan/1.0"
RESULT_CONTRACT = "sc-library-advanced-discovery-result/1.0"
QUERY_REPRESENTATION_CONTRACT = "sc-library-query-representation/1.0"

MAX_QUERY_LENGTH = 500
MAX_REPRESENTATIONS = 12
MAX_CANDIDATES_PER_REPRESENTATION = 100
RRF_K = 60.0

REPRESENTATION_WEIGHTS = {
    "original": 1.0,
    "translation": 0.88,
    "transliteration": 0.78,
    "alignment": 0.84,
    "synonym": 0.72,
    "user-supplied": 0.82,
    "derived": 0.80,
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _text(value: Any, limit: int = MAX_QUERY_LENGTH) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _optional_text(value: Any, limit: int) -> str | None:
    cleaned = _text(value, limit)
    return cleaned or None


def _script_hint(text: str) -> str:
    counts: dict[str, int] = {}
    for ch in text:
        if ch.isspace() or ch.isdigit() or unicodedata.category(ch).startswith(("P", "S")):
            continue
        cp = ord(ch)
        if 0x0600 <= cp <= 0x06FF or 0x0750 <= cp <= 0x077F or 0x08A0 <= cp <= 0x08FF:
            script = "Arabic"
        elif 0x0400 <= cp <= 0x052F:
            script = "Cyrillic"
        elif 0x0370 <= cp <= 0x03FF:
            script = "Greek"
        elif 0x0590 <= cp <= 0x05FF:
            script = "Hebrew"
        elif 0x3040 <= cp <= 0x30FF:
            script = "Japanese-Kana"
        elif 0x4E00 <= cp <= 0x9FFF:
            script = "Han"
        elif 0xAC00 <= cp <= 0xD7AF:
            script = "Hangul"
        elif 0x0900 <= cp <= 0x097F:
            script = "Devanagari"
        elif 0x0E00 <= cp <= 0x0E7F:
            script = "Thai"
        elif 0x0530 <= cp <= 0x058F:
            script = "Armenian"
        elif 0x10A0 <= cp <= 0x10FF:
            script = "Georgian"
        elif "LATIN" in unicodedata.name(ch, ""):
            script = "Latin"
        else:
            script = "Other"
        counts[script] = counts.get(script, 0) + 1
    if not counts:
        return "Unknown"
    return sorted(counts.items(), key=lambda item: (-item[1], item[0]))[0][0]


def guardrails() -> dict[str, bool]:
    return {
        "python_is_advanced_discovery_authority": True,
        "wordpress_php_is_discovery_authority": False,
        "original_query_remains_canonical": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "derived_representation_replaces_original": False,
        "query_script_hint_is_language_identification": False,
        "semantic_similarity_is_truth_probability": False,
        "ranking_score_is_truth_probability": False,
        "project_membership_implies_evidence_quality": False,
        "project_context_changes_truth_status": False,
        "neural_reranking_is_optional": True,
        "semantic_failure_falls_back_to_existing_retrieval": True,
        "cross_language_expansion_failure_falls_back_to_original_query": True,
        "automatic_machine_translation_required": False,
        "multilingual_embedding_provider_required_for_cross_language_semantic_matching": False,
        "wordpress_required": False,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "representation_types": sorted(REPRESENTATION_WEIGHTS),
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "discovery_service_id": "advanced-discovery:" + _fp(basis)[:32],
        "discovery_service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "api": {
            "contract": "/api/library/v1/discovery",
            "readiness": "/api/library/v1/discovery/readiness",
            "plan": "/api/library/v1/discovery/plan",
            "search": "/api/library/v1/discovery/search",
            "project_search": "/api/library/v1/discovery/projects/{project_id}/search",
        },
        "stages": [
            "query-normalization",
            "script-hinting",
            "representation-lineage",
            "per-representation-existing-retrieval",
            "cross-representation-rank-fusion",
            "optional-project-context-ranking",
            "ranking-explanation",
            "pagination",
        ],
        "components": {
            "retrieval": "python-retrieval-orchestration",
            "semantic": "existing-embedding-runtime",
            "reranking": "existing-neural-reranking-runtime",
            "language_provenance": "python-language-document-service",
            "project_context": "python-research-state-service-via-saved-workspaces",
        },
        "principles": {
            "analyze_original_language_first": True,
            "translation_is_derived": True,
            "preserve_representation_provenance": True,
            "cross_language_semantic_search_may_use_multilingual_embeddings": True,
            "lexical_cross_language_expansion_requires_explicit_derived_representations": True,
        },
        "next_release": "6.5.0",
        "next_release_name": "Global Knowledge Federation II",
        "guardrails": guardrails(),
    }


def _normalize_representation(raw: dict[str, Any], *, ordinal: int) -> dict[str, Any] | None:
    text = _text(raw.get("text") or raw.get("query"), MAX_QUERY_LENGTH)
    if not text:
        return None
    kind = _text(raw.get("representation_type") or raw.get("type") or "derived", 40).lower()
    if kind not in REPRESENTATION_WEIGHTS:
        kind = "derived"
    language = _optional_text(raw.get("language") or raw.get("language_tag"), 80)
    script = _optional_text(raw.get("script"), 80) or _script_hint(text)
    provenance = raw.get("provenance") if isinstance(raw.get("provenance"), dict) else {}
    weight = raw.get("weight")
    try:
        numeric_weight = float(weight) if weight is not None else REPRESENTATION_WEIGHTS[kind]
    except (TypeError, ValueError):
        numeric_weight = REPRESENTATION_WEIGHTS[kind]
    numeric_weight = max(0.05, min(1.0, numeric_weight))
    basis = {
        "text": text,
        "kind": kind,
        "language": language,
        "script": script,
        "provenance": provenance,
        "ordinal": ordinal,
    }
    return {
        "schema": QUERY_REPRESENTATION_CONTRACT,
        "representation_id": "query-representation:" + _fp(basis)[:32],
        "text": text,
        "representation_type": kind,
        "language": language,
        "script": script,
        "weight": numeric_weight,
        "canonical": False,
        "derived": True,
        "provenance": provenance,
    }


def normalize_request(payload: dict[str, Any] | None) -> dict[str, Any]:
    raw = dict(payload or {})
    q = _text(raw.get("q"), MAX_QUERY_LENGTH)
    if not q:
        raise ValueError("q is required for advanced discovery")
    original_language = _optional_text(raw.get("language") or raw.get("language_tag"), 80)
    original_script = _optional_text(raw.get("script"), 80) or _script_hint(q)
    original_basis = {"text": q, "language": original_language, "script": original_script, "kind": "original"}
    original = {
        "schema": QUERY_REPRESENTATION_CONTRACT,
        "representation_id": "query-representation:" + _fp(original_basis)[:32],
        "text": q,
        "representation_type": "original",
        "language": original_language,
        "script": original_script,
        "weight": 1.0,
        "canonical": True,
        "derived": False,
        "provenance": {"source": "request-original-query"},
    }
    reps: list[dict[str, Any]] = [original]
    seen = {q.casefold()}
    source_reps = raw.get("query_representations") or raw.get("representations") or []
    if isinstance(source_reps, list):
        for idx, item in enumerate(source_reps[: MAX_REPRESENTATIONS - 1], start=1):
            if not isinstance(item, dict):
                continue
            rep = _normalize_representation(item, ordinal=idx)
            if not rep:
                continue
            key = rep["text"].casefold()
            if key in seen:
                continue
            seen.add(key)
            reps.append(rep)
    mode = _text(raw.get("mode") or "hybrid", 20).lower()
    if mode not in {"hybrid", "lexical", "semantic"}:
        mode = "hybrid"
    rerank = _text(raw.get("rerank") or "neural", 20).lower()
    if rerank not in {"none", "neural", "adaptive"}:
        rerank = "none"
    limit = max(1, min(100, int(raw.get("limit", 20))))
    offset = max(0, min(100000, int(raw.get("offset", 0))))
    candidate_limit = int(raw.get("candidate_limit") or max(40, min(MAX_CANDIDATES_PER_REPRESENTATION, (limit + offset) * 3)))
    candidate_limit = max(limit + offset, min(MAX_CANDIDATES_PER_REPRESENTATION, candidate_limit))
    normalized = {
        "q": q,
        "language": original_language,
        "script": original_script,
        "mode": mode,
        "rerank": rerank,
        "sort": _text(raw.get("sort") or "relevance", 20).lower(),
        "object_type": _optional_text(raw.get("object_type"), 80),
        "source_key": _optional_text(raw.get("source_key"), 191),
        "topic": _optional_text(raw.get("topic"), 500),
        "year_from": raw.get("year_from"),
        "year_to": raw.get("year_to"),
        "include_core": bool(raw.get("include_core", True)),
        "limit": limit,
        "offset": offset,
        "candidate_limit": candidate_limit,
        "profile": raw.get("profile") if isinstance(raw.get("profile"), dict) else {},
        "cross_language": bool(raw.get("cross_language", True)),
        "query_representations": reps,
    }
    if not normalized["cross_language"]:
        normalized["query_representations"] = [original]
    return normalized


def plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    n = normalize_request(payload)
    reps = n["query_representations"]
    expansion_state = "original-only"
    if len(reps) > 1:
        expansion_state = "explicit-derived-representations"
    elif n["cross_language"] and n["mode"] in {"hybrid", "semantic"}:
        expansion_state = "multilingual-semantic-capable-original-query"
    stages = [
        "query-normalization",
        "script-hinting",
        "representation-lineage",
        "per-representation-existing-retrieval",
        "cross-representation-rank-fusion",
        "ranking-explanation",
        "pagination",
    ]
    basis = {
        "query": n["q"],
        "representations": [{k: r.get(k) for k in ("representation_id", "representation_type", "language", "script", "weight")} for r in reps],
        "mode": n["mode"],
        "rerank": n["rerank"],
        "filters": {k: n.get(k) for k in ("object_type", "source_key", "topic", "year_from", "year_to")},
        "candidate_limit": n["candidate_limit"],
        "cross_language": n["cross_language"],
    }
    fp = _fp(basis)
    return {
        "schema": PLAN_CONTRACT,
        "plan_id": "advanced-discovery-plan:" + fp[:32],
        "plan_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "normalized": n,
        "cross_language_expansion_state": expansion_state,
        "stages": stages,
        "guardrails": guardrails(),
    }


def _record_id(item: dict[str, Any]) -> str:
    return str(item.get("record_id") or item.get("id") or item.get("research_object_id") or "").strip()


def _project_reference_ids(project_context: dict[str, Any] | None) -> set[str]:
    if not isinstance(project_context, dict):
        return set()
    ids: set[str] = set()
    for ref in project_context.get("references") or []:
        if not isinstance(ref, dict):
            continue
        for key in ("record_id", "research_object_id", "target_id", "reference_record_id"):
            value = str(ref.get(key) or "").strip()
            if value:
                ids.add(value)
                break
    return ids


def _result_language(item: dict[str, Any]) -> dict[str, Any]:
    meta = item.get("metadata") if isinstance(item.get("metadata"), dict) else {}
    language = item.get("language") or item.get("language_tag") or meta.get("language") or meta.get("language_tag") or meta.get("original_language")
    script = item.get("script") or meta.get("script") or meta.get("original_script")
    return {"language": language, "script": script}


def _execute(
    payload: dict[str, Any] | None,
    *,
    project_context: dict[str, Any] | None = None,
    retrieval_executor: Callable[[dict[str, Any] | None], dict[str, Any]] = execute_retrieval,
) -> dict[str, Any]:
    p = plan(payload)
    n = p["normalized"]
    reps = n["query_representations"]
    project_ids = _project_reference_ids(project_context)
    aggregate: dict[str, dict[str, Any]] = {}
    retrieval_runs: list[dict[str, Any]] = []
    max_total = 0

    for rep in reps:
        request = {
            "q": rep["text"],
            "object_type": n["object_type"],
            "source_key": n["source_key"],
            "topic": n["topic"],
            "year_from": n["year_from"],
            "year_to": n["year_to"],
            "sort": n["sort"],
            "mode": n["mode"],
            "rerank": n["rerank"],
            "include_core": n["include_core"],
            "limit": n["candidate_limit"],
            "offset": 0,
            "candidate_limit": n["candidate_limit"],
            "profile": n["profile"],
        }
        result = retrieval_executor(request)
        rows = [dict(x) for x in (result.get("results") or [])]
        max_total = max(max_total, int(result.get("total") or 0), len(rows))
        retrieval_runs.append({
            "representation_id": rep["representation_id"],
            "representation_type": rep["representation_type"],
            "language": rep.get("language"),
            "script": rep.get("script"),
            "weight": rep["weight"],
            "result_count": len(rows),
            "retrieval": result.get("retrieval", {}),
            "reranking": result.get("reranking", {}),
            "orchestration": result.get("orchestration", {}),
        })
        for rank, row in enumerate(rows, start=1):
            rid = _record_id(row)
            if not rid:
                continue
            contribution = float(rep["weight"]) / (RRF_K + rank)
            entry = aggregate.get(rid)
            if entry is None:
                entry = {
                    "record": row,
                    "fusion_score": 0.0,
                    "matches": [],
                    "best_rank": rank,
                }
                aggregate[rid] = entry
            entry["fusion_score"] += contribution
            entry["best_rank"] = min(int(entry["best_rank"]), rank)
            entry["matches"].append({
                "representation_id": rep["representation_id"],
                "representation_type": rep["representation_type"],
                "language": rep.get("language"),
                "script": rep.get("script"),
                "rank": rank,
                "weight": rep["weight"],
                "rrf_contribution": contribution,
            })

    fused: list[dict[str, Any]] = []
    for rid, entry in aggregate.items():
        row = dict(entry["record"])
        project_match = rid in project_ids
        project_boost = 0.0005 if project_match else 0.0
        score = float(entry["fusion_score"]) + project_boost
        row["advanced_discovery_score"] = score
        row["discovery_signals"] = {
            "cross_representation_rrf_score": float(entry["fusion_score"]),
            "project_context_boost": project_boost,
            "project_reference_match": project_match,
            "matched_representation_count": len(entry["matches"]),
            "matched_representations": entry["matches"],
            "best_representation_rank": entry["best_rank"],
            "result_language": _result_language(row),
            "ranking_score_is_truth_probability": False,
            "project_membership_implies_evidence_quality": False,
        }
        fused.append(row)

    fused.sort(key=lambda item: (-float(item.get("advanced_discovery_score") or 0.0), str(item.get("record_id") or item.get("id") or "")))
    page = fused[n["offset"]: n["offset"] + n["limit"]]
    basis = {
        "plan": p["plan_fingerprint_sha256"],
        "result_ids": [_record_id(x) for x in page],
        "project_context": (project_context or {}).get("context_id") if isinstance(project_context, dict) else None,
    }
    return {
        "schema": RESULT_CONTRACT,
        "result_id": "advanced-discovery-result:" + _fp(basis)[:32],
        "result_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "authority": "python-backend",
        "query": n["q"],
        "query_language": n["language"],
        "query_script": n["script"],
        "mode": n["mode"],
        "rerank": n["rerank"],
        "limit": n["limit"],
        "offset": n["offset"],
        "total": len(fused) if fused else max_total,
        "total_is_exact": bool(fused),
        "results": page,
        "query_representations": reps,
        "cross_language_expansion_state": p["cross_language_expansion_state"],
        "retrieval_runs": retrieval_runs,
        "project_context": {
            "applied": bool(project_context),
            "project_id": ((project_context or {}).get("project") or {}).get("project_id") if isinstance(project_context, dict) else None,
            "reference_count": len(project_ids),
            "ranking_boost_is_truth_signal": False,
        },
        "orchestration": {
            "plan_id": p["plan_id"],
            "plan_fingerprint_sha256": p["plan_fingerprint_sha256"],
            "fusion": "weighted-reciprocal-rank-fusion",
            "rrf_k": RRF_K,
            "representation_count": len(reps),
            "stages": p["stages"],
            "existing_retrieval_orchestrator_reused": True,
        },
        "guardrails": guardrails(),
    }


def execute(payload: dict[str, Any] | None) -> dict[str, Any]:
    return _execute(payload)


def execute_project(payload: dict[str, Any] | None, project_context: dict[str, Any]) -> dict[str, Any]:
    return _execute(payload, project_context=project_context)


def readiness() -> dict[str, Any]:
    retrieval = retrieval_readiness()
    language = language_readiness()
    retrieval_ready = str(retrieval.get("state") or "") in {"ready", "degraded"}
    language_ready = str(language.get("state") or "") in {"ready", "degraded"}
    semantic = retrieval.get("semantic_search") if isinstance(retrieval.get("semantic_search"), dict) else {}
    semantic_available = bool(semantic.get("available"))
    state = "ready" if retrieval_ready and language_ready else "blocked"
    if state == "ready" and not semantic_available:
        state = "degraded"
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": state,
        "ready": state in {"ready", "degraded"},
        "authority": "python-backend",
        "wordpress_required": False,
        "cross_language_semantic_matching": {
            "available": semantic_available,
            "dependency": "configured embedding provider/model with suitable multilingual behavior",
            "fallback": "explicit-derived-representations-or-original-query-existing-retrieval",
        },
        "explicit_translation_or_transliteration_expansion": True,
        "automatic_machine_translation_required": False,
        "project_aware_discovery": True,
        "components": {"retrieval": retrieval, "language": language},
        "next_release": "6.5.0",
        "guardrails": guardrails(),
    }
