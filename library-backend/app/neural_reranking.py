from __future__ import annotations

from dataclasses import dataclass
import hashlib
import json
from typing import Any

import httpx

from .retrieval_evaluation import evaluate_case
from .settings import settings

NEURAL_RERANK_SCHEMA = "sc-library-neural-reranking/1.0"
RERANKER_SPEC_SCHEMA = "sc-library-neural-reranker-specification/1.0"
RERANK_EVALUATION_SCHEMA = "sc-library-neural-reranking-evaluation/1.0"
SUPPORTED_PROVIDERS = {"disabled", "rerank_compatible"}


class RerankingError(RuntimeError):
    pass


def _clean_text(value: Any, limit: int = 5000) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _sha256_json(value: Any) -> str:
    encoded = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()


def candidate_text(item: dict[str, Any]) -> str:
    parts: list[str] = []
    title = _clean_text(item.get("title"), 1000)
    abstract = _clean_text(item.get("abstract"), 2800)
    snippet = _clean_text(item.get("snippet"), 1600)
    topics = item.get("topics") or []
    if title:
        parts.append(f"Title: {title}")
    if abstract:
        parts.append(f"Abstract: {abstract}")
    elif snippet:
        parts.append(f"Text: {snippet}")
    if isinstance(topics, list) and topics:
        parts.append("Topics: " + "; ".join(_clean_text(v, 180) for v in topics[:24] if str(v).strip()))
    return "\n".join(parts).strip()[:5000]


@dataclass(frozen=True)
class RerankScore:
    index: int
    relevance_score: float


class NeuralRerankerClient:
    """Dependency-light client for a standard query/documents neural rerank API.

    The `rerank_compatible` contract intentionally matches the common shape:
    request {model, query, documents, top_n, return_documents}; response
    {results:[{index,relevance_score}]}. The Library never fabricates neural
    scores when no provider is configured.
    """

    def __init__(
        self,
        *,
        provider: str,
        api_key: str = "",
        model: str = "",
        api_url: str = "",
        timeout_seconds: int = 12,
        transport: httpx.BaseTransport | None = None,
    ) -> None:
        provider = str(provider or "disabled").strip().lower()
        if provider not in SUPPORTED_PROVIDERS:
            raise ValueError(f"unsupported reranking provider: {provider}")
        self.provider = provider
        self.api_key = str(api_key or "").strip()
        self.model = str(model or "").strip()
        self.api_url = str(api_url or "").strip()
        self.timeout_seconds = max(2, min(60, int(timeout_seconds)))
        self.transport = transport

    @property
    def configured(self) -> bool:
        return self.provider == "rerank_compatible" and bool(self.api_key and self.model and self.api_url)

    def specification(self) -> dict[str, Any]:
        body = {
            "schema": RERANKER_SPEC_SCHEMA,
            "provider": self.provider,
            "model": self.model or None,
            "api_contract": "query-documents-index-score/1.0",
            "candidate_text_contract": "title+abstract-or-snippet+topics/1.0",
            "max_candidates": settings.rerank_max_candidates,
            "configured": self.configured,
            "score_semantics": "provider-relevance-score-not-probability",
            "guardrails": {
                "rerank_score_is_probability": False,
                "rerank_score_is_evidence": False,
                "rerank_score_is_truth": False,
                "rerank_changes_evidence_relations": False,
                "automatic_candidate_filtering": False,
                "automatic_platform_core_promotion": False,
            },
        }
        fingerprint_body = {k: v for k, v in body.items() if k not in {"configured"}}
        body["fingerprint_sha256"] = _sha256_json(fingerprint_body)
        body["specification_id"] = f"reranker-specification:{body['fingerprint_sha256'][:32]}"
        return body

    def rerank(self, query: str, documents: list[str]) -> list[RerankScore]:
        query = _clean_text(query, 4000)
        documents = [_clean_text(document, 5000) for document in documents]
        if not query:
            raise RerankingError("reranking query cannot be empty")
        if not documents:
            return []
        if not self.configured:
            raise RerankingError("neural reranking provider is not configured")
        payload = {
            "model": self.model,
            "query": query,
            "documents": documents,
            "top_n": len(documents),
            "return_documents": False,
        }
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.api_key}",
        }
        with httpx.Client(timeout=self.timeout_seconds, transport=self.transport, follow_redirects=False) as client:
            response = client.post(self.api_url, headers=headers, json=payload)
        if response.status_code < 200 or response.status_code >= 300:
            raise RerankingError(f"reranking provider returned HTTP {response.status_code}")
        try:
            data = response.json()
            raw_results = data.get("results") if isinstance(data, dict) else None
            if not isinstance(raw_results, list):
                raise TypeError("results must be a list")
            parsed: list[RerankScore] = []
            seen: set[int] = set()
            for row in raw_results:
                if not isinstance(row, dict):
                    continue
                idx = int(row["index"])
                score = float(row.get("relevance_score", row.get("score")))
                if idx < 0 or idx >= len(documents) or idx in seen:
                    continue
                seen.add(idx)
                parsed.append(RerankScore(index=idx, relevance_score=score))
        except (ValueError, TypeError, KeyError) as exc:
            raise RerankingError("reranking response did not contain valid indexed relevance scores") from exc
        if not parsed:
            raise RerankingError("reranking provider returned no usable scores")
        return parsed


def default_reranker_client() -> NeuralRerankerClient:
    return NeuralRerankerClient(
        provider=settings.rerank_provider,
        api_key=settings.rerank_api_key,
        model=settings.rerank_model,
        api_url=settings.rerank_api_url,
        timeout_seconds=settings.rerank_timeout_seconds,
    )


def reranking_readiness(client: NeuralRerankerClient | None = None) -> dict[str, Any]:
    client = client or default_reranker_client()
    specification = client.specification()
    return {
        "schema": NEURAL_RERANK_SCHEMA,
        "state": "ready",
        "configured": client.configured,
        "provider": client.provider,
        "model": client.model or None,
        "specification": specification,
        "max_candidates": settings.rerank_max_candidates,
        "baseline_fallback_when_unconfigured": True,
        "retrieval_evaluation_integration": True,
        "fake_neural_scores": False,
        "guardrails": specification["guardrails"],
    }


def rerank_candidates(
    query: str,
    results: list[dict[str, Any]],
    *,
    client: NeuralRerankerClient | None = None,
) -> dict[str, Any]:
    query = _clean_text(query, 4000)
    items = [dict(row) for row in results if isinstance(row, dict)]
    client = client or default_reranker_client()
    specification = client.specification()
    cap = min(len(items), settings.rerank_max_candidates)
    candidate_items = items[:cap]
    documents = [candidate_text(item) for item in candidate_items]
    candidate_manifest = [
        {
            "record_id": str(item.get("record_id") or item.get("id") or ""),
            "baseline_rank": index + 1,
            "text_hash_sha256": hashlib.sha256(documents[index].encode("utf-8")).hexdigest(),
        }
        for index, item in enumerate(candidate_items)
    ]
    run_fingerprint = _sha256_json({
        "query": query,
        "specification": specification["fingerprint_sha256"],
        "candidates": candidate_manifest,
    })
    available = client.configured and bool(query) and bool(candidate_items)
    failure_reason: str | None = None
    score_map: dict[int, float] = {}
    if available:
        try:
            score_map = {score.index: score.relevance_score for score in client.rerank(query, documents)}
        except Exception as exc:
            available = False
            failure_reason = exc.__class__.__name__

    scored: list[dict[str, Any]] = []
    for baseline_rank, item in enumerate(items, start=1):
        result = dict(item)
        baseline_score = result.get("hybrid_score", result.get("score"))
        relevance_score = score_map.get(baseline_rank - 1) if baseline_rank <= cap else None
        result["neural_reranking"] = {
            "run_id": f"rerank:{run_fingerprint[:32]}",
            "specification_fingerprint_sha256": specification["fingerprint_sha256"],
            "provider": client.provider,
            "model": client.model or None,
            "baseline_rank": baseline_rank,
            "baseline_score": baseline_score,
            "candidate_text_hash_sha256": candidate_manifest[baseline_rank - 1]["text_hash_sha256"] if baseline_rank <= cap else None,
            "provider_relevance_score": relevance_score,
            "provider_score_is_probability": False,
            "scored_by_neural_provider": relevance_score is not None,
        }
        scored.append(result)

    if available and score_map:
        scored.sort(key=lambda row: (
            0 if (row.get("neural_reranking") or {}).get("provider_relevance_score") is not None else 1,
            -float((row.get("neural_reranking") or {}).get("provider_relevance_score") or 0.0),
            int((row.get("neural_reranking") or {}).get("baseline_rank") or 0),
        ))
    for new_rank, item in enumerate(scored, start=1):
        block = item["neural_reranking"]
        block["neural_rank"] = new_rank if available and score_map else None
        block["rank_delta"] = (int(block["baseline_rank"]) - new_rank) if available and score_map else 0

    return {
        "schema": NEURAL_RERANK_SCHEMA,
        "query": query,
        "query_hash_sha256": hashlib.sha256(query.encode("utf-8")).hexdigest(),
        "run_id": f"rerank:{run_fingerprint[:32]}",
        "available": available,
        "reason": failure_reason if failure_reason else (None if available else "reranking-provider-not-configured"),
        "specification": specification,
        "candidate_count": len(items),
        "provider_scored_candidate_count": len(score_map),
        "max_provider_candidates": settings.rerank_max_candidates,
        "results": scored,
        "guardrails": {
            "result_set_preserved": True,
            "baseline_rank_preserved": True,
            "provider_score_is_probability": False,
            "reranking_changes_truth_status": False,
            "reranking_changes_evidence_relations": False,
            "reranking_changes_platform_core_objects": False,
            "automatic_candidate_filtering": False,
        },
    }


def _judgment_map(payload: dict[str, Any]) -> dict[str, dict[str, Any]]:
    judgments = payload.get("judgments") or []
    out: dict[str, dict[str, Any]] = {}
    if isinstance(judgments, dict):
        for key, value in judgments.items():
            if isinstance(value, dict):
                out[str(key)] = dict(value)
            else:
                out[str(key)] = {"relevance_grade": value}
    elif isinstance(judgments, list):
        for row in judgments:
            if isinstance(row, dict):
                rid = str(row.get("record_id") or row.get("id") or "").strip()
                if rid:
                    out[rid] = dict(row)
    return out


def _with_judgments(results: list[dict[str, Any]], judgments: dict[str, dict[str, Any]]) -> list[dict[str, Any]]:
    enriched: list[dict[str, Any]] = []
    for row in results:
        item = dict(row)
        rid = str(item.get("record_id") or item.get("id") or "")
        judgment = judgments.get(rid)
        if judgment:
            for key in ("relevance_grade", "accepted", "rejected", "relevant", "rejection_reason", "evidence_covered"):
                if key in judgment:
                    item[key] = judgment[key]
        enriched.append(item)
    return enriched


def evaluate_reranking(payload: dict[str, Any]) -> dict[str, Any]:
    query = _clean_text(payload.get("query"), 4000)
    baseline = [dict(x) for x in (payload.get("baseline_results") or []) if isinstance(x, dict)]
    reranked = [dict(x) for x in (payload.get("reranked_results") or []) if isinstance(x, dict)]
    judgments = _judgment_map(payload)
    known = [str(x) for x in (payload.get("known_relevant_ids") or []) if str(x)]
    baseline_eval = evaluate_case({
        "case_id": str(payload.get("case_id") or "reranking-baseline"),
        "query": query,
        "retrieval_mode": "baseline",
        "results": _with_judgments(baseline, judgments),
        "known_relevant_ids": known,
    })
    reranked_eval = evaluate_case({
        "case_id": str(payload.get("case_id") or "reranking-neural"),
        "query": query,
        "retrieval_mode": "neural-reranked",
        "results": _with_judgments(reranked, judgments),
        "known_relevant_ids": known,
    })
    metric_names = ("mean_average_precision", "mean_reciprocal_rank", "evidence_coverage", "relevant_source_diversity", "unsupported_rejection_rate")
    deltas: dict[str, float | None] = {}
    for name in metric_names:
        before = baseline_eval["metrics"].get(name)
        after = reranked_eval["metrics"].get(name)
        deltas[name] = round(float(after) - float(before), 6) if before is not None and after is not None else None
    return {
        "schema": RERANK_EVALUATION_SCHEMA,
        "query": query,
        "judgment_count": len(judgments),
        "baseline": baseline_eval,
        "reranked": reranked_eval,
        "metric_deltas_reranked_minus_baseline": deltas,
        "interpretation": {
            "quality_claim_requires_explicit_judgments": True,
            "positive_metric_delta_is_not_truth_validation": True,
            "evaluation_does_not_change_evidence_status": True,
            "evaluation_does_not_change_platform_core_objects": True,
        },
    }
