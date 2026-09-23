from __future__ import annotations

from collections import defaultdict
from typing import Any

from .db import get_pool
from .query import search_records
from .semantic import EmbeddingClient, EmbeddingError, default_embedding_client
from .settings import settings
from .retrieval_fusion import as_float_or_none, reciprocal_rank_fusion

HYBRID_CONTRACT = "sc-library-hybrid-retrieval/1.0"
MODES = {"hybrid", "lexical", "semantic"}


def _clean_mode(mode: str) -> str:
    value = str(mode or "hybrid").strip().lower()
    return value if value in MODES else "hybrid"


def _filter_sql(
    object_type: str | None,
    source_key: str | None,
    topic: str | None,
    year_from: int | None,
    year_to: int | None,
) -> tuple[str, list[Any]]:
    filters = ["r.visibility='public'", "r.publication_status='published'"]
    params: list[Any] = []
    if object_type:
        filters.append("r.object_type=%s")
        params.append(object_type)
    if source_key:
        filters.append("r.source_key=%s")
        params.append(source_key)
    if topic:
        filters.append(
            "EXISTS (SELECT 1 FROM jsonb_array_elements_text(r.topics) AS topic_value(value) WHERE lower(topic_value.value)=lower(%s))"
        )
        params.append(topic)
    if year_from is not None:
        filters.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) >= %s")
        params.append(year_from)
    if year_to is not None:
        filters.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) <= %s")
        params.append(year_to)
    return " AND ".join(filters), params


def semantic_candidates(
    query_embedding: list[float],
    *,
    object_type: str | None = None,
    source_key: str | None = None,
    topic: str | None = None,
    year_from: int | None = None,
    year_to: int | None = None,
    limit: int = 80,
) -> list[dict[str, Any]]:
    where, params = _filter_sql(object_type, source_key, topic, year_from, year_to)
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.abstract,r.source_key,
                   r.published_at,r.source_updated_at,r.indexed_at,r.authors,r.topics,r.tags,
                   r.identifiers,r.metadata,
                   sc_cosine_similarity(e.embedding,%s::double precision[]) AS semantic_score,
                   left(coalesce(nullif(r.abstract,''),r.body_text),420) AS snippet
              FROM library_record_embeddings e
              JOIN library_records r ON r.record_id=e.record_id
             WHERE {where}
               AND e.content_hash=r.content_hash
               AND cardinality(e.embedding)=cardinality(%s::double precision[])
             ORDER BY semantic_score DESC NULLS LAST,r.source_updated_at DESC NULLS LAST
             LIMIT %s
            """,
            [query_embedding, *params, query_embedding, max(1, min(500, int(limit)))],
        )
        return [dict(row) for row in cur.fetchall() if row.get("semantic_score") is not None]


def _core_bindings(record_ids: list[str]) -> dict[str, list[dict[str, Any]]]:
    if not record_ids:
        return {}
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT library_record_id,core_object_id,core_object_type,core_canonical_uri,
                   content_hash,metadata,sync_status,last_synced_at
              FROM library_core_bindings
             WHERE library_record_id = ANY(%s)
               AND sync_status='synced'
             ORDER BY library_record_id,updated_at DESC
            """,
            (record_ids,),
        )
        rows = [dict(row) for row in cur.fetchall()]
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        grouped[str(row.pop("library_record_id"))].append(row)
    return dict(grouped)


def enrich_with_core_bindings(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    record_ids = [str(row.get("record_id") or "") for row in rows if row.get("record_id")]
    bindings = _core_bindings(record_ids)
    enriched: list[dict[str, Any]] = []
    for row in rows:
        item = dict(row)
        linked = bindings.get(str(row.get("record_id") or ""), [])
        item["platform_core"] = {
            "bound": bool(linked),
            "binding_count": len(linked),
            "objects": linked,
            "authority": "platform-core" if linked else None,
        }
        enriched.append(item)
    return enriched


def hybrid_search_records(
    q: str = "",
    object_type: str | None = None,
    source_key: str | None = None,
    topic: str | None = None,
    year_from: int | None = None,
    year_to: int | None = None,
    sort: str = "relevance",
    limit: int = 20,
    offset: int = 0,
    mode: str = "hybrid",
    include_core: bool = True,
    embedding_client: EmbeddingClient | None = None,
) -> dict[str, Any]:
    mode = _clean_mode(mode)
    q = " ".join(str(q or "").split()).strip()
    limit = max(1, min(100, int(limit)))
    offset = max(0, min(100000, int(offset)))

    # Empty-query browsing and non-relevance sorts remain deterministic lexical/database reads.
    if not q or sort != "relevance" or mode == "lexical":
        result = search_records(q, object_type, source_key, topic, year_from, year_to, sort, limit, offset)
        rows = [dict(row) for row in result["results"]]
        if include_core:
            rows = enrich_with_core_bindings(rows)
        result["schema"] = HYBRID_CONTRACT
        result["results"] = rows
        result["retrieval"] = {
            "requested_mode": mode,
            "effective_mode": "lexical",
            "degraded": False,
            "reason": "empty-query-or-explicit-sort" if mode != "lexical" else None,
            "fusion": None,
            "platform_core_enrichment": include_core,
        }
        return result

    candidate_limit = max(limit + offset, min(400, max(40, (limit + offset) * settings.hybrid_candidate_multiplier)))
    lexical_payload = search_records(q, object_type, source_key, topic, year_from, year_to, "relevance", candidate_limit, 0)
    lexical = [dict(row) for row in lexical_payload["results"]]
    semantic: list[dict[str, Any]] = []
    semantic_error: str | None = None
    client = embedding_client or default_embedding_client()

    if mode in {"hybrid", "semantic"}:
        if client.configured:
            try:
                query_vector = client.embed(q).values
                semantic = semantic_candidates(
                    query_vector,
                    object_type=object_type,
                    source_key=source_key,
                    topic=topic,
                    year_from=year_from,
                    year_to=year_to,
                    limit=candidate_limit,
                )
            except Exception as exc:
                semantic_error = exc.__class__.__name__
            if not semantic and semantic_error is None:
                semantic_error = "semantic-index-empty-or-no-matches"
        else:
            semantic_error = "embedding-provider-not-configured"

    if mode == "semantic" and semantic:
        combined = []
        for rank, row in enumerate(semantic, start=1):
            item = dict(row)
            item["hybrid_rank"] = rank
            item["hybrid_score"] = item.get("semantic_score")
            item["retrieval_signals"] = {
                "lexical_rank": None,
                "lexical_score": None,
                "semantic_rank": rank,
                "semantic_score": as_float_or_none(item.get("semantic_score")),
            }
            combined.append(item)
        effective_mode = "semantic"
        degraded = False
    elif semantic:
        combined = reciprocal_rank_fusion(
            lexical,
            semantic,
            lexical_weight=settings.hybrid_lexical_weight,
            semantic_weight=settings.hybrid_semantic_weight,
            rrf_k=settings.hybrid_rrf_k,
        )
        effective_mode = "hybrid"
        degraded = False
    else:
        combined = []
        for rank, row in enumerate(lexical, start=1):
            item = dict(row)
            item["hybrid_rank"] = rank
            item["hybrid_score"] = as_float_or_none(item.get("score")) or 0.0
            item["retrieval_signals"] = {
                "lexical_rank": rank,
                "lexical_score": as_float_or_none(item.get("score")),
                "semantic_rank": None,
                "semantic_score": None,
            }
            combined.append(item)
        effective_mode = "lexical-fallback" if mode in {"hybrid", "semantic"} else "lexical"
        degraded = mode in {"hybrid", "semantic"}

    page = combined[offset: offset + limit]
    if include_core:
        page = enrich_with_core_bindings(page)
    return {
        "schema": HYBRID_CONTRACT,
        "query": q,
        "filters": {
            "object_type": object_type,
            "source_key": source_key,
            "topic": topic,
            "year_from": year_from,
            "year_to": year_to,
            "sort": sort,
            "mode": mode,
        },
        "total": max(int(lexical_payload.get("total", 0)), len(combined)),
        "total_is_exact": not bool(semantic),
        "candidate_count": len(combined),
        "limit": limit,
        "offset": offset,
        "results": page,
        "retrieval": {
            "requested_mode": mode,
            "effective_mode": effective_mode,
            "degraded": degraded,
            "reason": semantic_error,
            "fusion": "weighted-reciprocal-rank-fusion" if effective_mode == "hybrid" else None,
            "rrf_k": settings.hybrid_rrf_k if effective_mode == "hybrid" else None,
            "lexical_weight": settings.hybrid_lexical_weight if effective_mode == "hybrid" else None,
            "semantic_weight": settings.hybrid_semantic_weight if effective_mode == "hybrid" else None,
            "platform_core_enrichment": include_core,
            "semantic_candidate_count": len(semantic),
            "lexical_candidate_count": len(lexical),
        },
    }
