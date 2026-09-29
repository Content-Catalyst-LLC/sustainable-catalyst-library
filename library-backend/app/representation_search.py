from __future__ import annotations

import hashlib
from typing import Any

from .db import get_pool
from .embedding_governance import current_embedding_specification
from .semantic import EmbeddingClient, default_embedding_client
from .settings import settings

SEMANTIC_SIMILARITY_CONTRACT = "sc-library-semantic-similarity/1.0"
REPRESENTATION_SEARCH_CONTRACT = "sc-library-representation-search/1.0"
REPRESENTATION_DESCRIPTOR_CONTRACT = "sc-library-semantic-representation/1.0"


def _clean_text(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def _filters(
    object_type: str | None,
    source_key: str | None,
    topic: str | None,
    year_from: int | None,
    year_to: int | None,
) -> tuple[str, list[Any]]:
    parts = ["r.visibility='public'", "r.publication_status='published'"]
    params: list[Any] = []
    if object_type:
        parts.append("r.object_type=%s")
        params.append(object_type)
    if source_key:
        parts.append("r.source_key=%s")
        params.append(source_key)
    if topic:
        parts.append(
            "EXISTS (SELECT 1 FROM jsonb_array_elements_text(r.topics) AS topic_value(value) "
            "WHERE lower(topic_value.value)=lower(%s))"
        )
        params.append(topic)
    if year_from is not None:
        parts.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) >= %s")
        params.append(year_from)
    if year_to is not None:
        parts.append("EXTRACT(YEAR FROM COALESCE(r.published_at,r.source_updated_at,r.indexed_at)) <= %s")
        params.append(year_to)
    return " AND ".join(parts), params


def _representation_block(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "representation_id": row.get("representation_id"),
        "specification_fingerprint_sha256": row.get("specification_fingerprint"),
        "provider": row.get("embedding_provider"),
        "model": row.get("embedding_model"),
        "dimensions": row.get("embedding_dimensions"),
        "execution_target": row.get("embedding_execution_target"),
        "execution_id": row.get("embedding_execution_id"),
        "content_hash": row.get("embedding_content_hash"),
        "current_content": bool(row.get("embedding_content_hash") and row.get("embedding_content_hash") == row.get("record_content_hash")),
    }


def representation_descriptor(record_id: str) -> dict[str, Any]:
    specification = current_embedding_specification()
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.content_hash AS record_content_hash,
                   e.content_hash AS embedding_content_hash,e.input_hash,e.provider AS embedding_provider,
                   e.model AS embedding_model,e.dimensions AS embedding_dimensions,e.specification_fingerprint,
                   e.representation_id,e.execution_target AS embedding_execution_target,
                   e.execution_id AS embedding_execution_id,e.provenance,e.created_at,e.updated_at
              FROM library_records r
              LEFT JOIN library_record_embeddings e ON e.record_id=r.record_id
             WHERE r.record_id=%s
            """,
            (record_id,),
        )
        row = cur.fetchone()
    if not row:
        raise ValueError("library record not found")
    item = dict(row)
    stored = bool(item.get("representation_id") or item.get("embedding_content_hash"))
    current_content = stored and str(item.get("embedding_content_hash") or "") == str(item.get("record_content_hash") or "")
    current_specification = stored and str(item.get("specification_fingerprint") or "") == specification["fingerprint_sha256"]
    return {
        "schema": REPRESENTATION_DESCRIPTOR_CONTRACT,
        "record": {
            "record_id": item["record_id"],
            "object_type": item.get("object_type"),
            "title": item.get("title"),
            "canonical_url": item.get("canonical_url"),
            "content_hash": item.get("record_content_hash"),
        },
        "stored": stored,
        "current_content": current_content,
        "current_specification": current_specification,
        "search_eligible": bool(stored and current_content and item.get("specification_fingerprint")),
        "representation": _representation_block(item) if stored else None,
        "current_embedding_specification": {
            "specification_id": specification["specification_id"],
            "fingerprint_sha256": specification["fingerprint_sha256"],
            "provider": specification["provider"],
            "model": specification["model"],
            "dimensions": specification["dimensions"],
        },
        "guardrails": {
            "embedding_is_evidence": False,
            "embedding_is_truth": False,
            "semantic_similarity_is_truth": False,
            "semantic_similarity_is_causality": False,
        },
    }


def representation_search_readiness() -> dict[str, Any]:
    specification = current_embedding_specification()
    result: dict[str, Any] = {
        "schema": REPRESENTATION_SEARCH_CONTRACT,
        "state": "ready",
        "query_text_semantic_search_available": bool(specification.get("configured")),
        "record_to_record_similarity_requires_provider": False,
        "specification_fingerprint_sha256": specification["fingerprint_sha256"],
        "query_text_current_specification_only": True,
        "record_similarity_same_specification_as_seed": True,
        "current_content_only_by_default": True,
        "minimum_similarity": settings.semantic_min_similarity,
        "guardrails": {
            "semantic_similarity_is_evidence": False,
            "semantic_similarity_is_truth": False,
            "semantic_similarity_is_causality": False,
            "automatic_core_promotion": False,
        },
    }
    try:
        pool = get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS count FROM library_record_embeddings")
            result["stored_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                """
                SELECT count(*) AS count
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id
                 WHERE e.content_hash=r.content_hash
                   AND e.specification_fingerprint IS NOT NULL
                """
            )
            result["record_similarity_eligible_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                """
                SELECT count(*) AS count
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id
                 WHERE e.content_hash=r.content_hash
                   AND e.specification_fingerprint=%s
                """,
                (specification["fingerprint_sha256"],),
            )
            result["current_configured_specification_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                """
                SELECT count(*) AS count
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id
                 WHERE e.content_hash<>r.content_hash
                """
            )
            result["stale_content_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                """
                SELECT count(*) AS count
                  FROM library_record_embeddings e
                 WHERE e.specification_fingerprint IS DISTINCT FROM %s
                """,
                (specification["fingerprint_sha256"],),
            )
            result["other_specification_representations"] = int(cur.fetchone()["count"])
    except Exception as exc:
        result["state"] = "unavailable"
        result["error"] = exc.__class__.__name__
    return result


def semantic_text_search(
    q: str,
    *,
    object_type: str | None = None,
    source_key: str | None = None,
    topic: str | None = None,
    year_from: int | None = None,
    year_to: int | None = None,
    min_similarity: float | None = None,
    limit: int = 20,
    offset: int = 0,
    client: EmbeddingClient | None = None,
) -> dict[str, Any]:
    query = _clean_text(q)
    if not query:
        raise ValueError("semantic query cannot be empty")
    client = client or default_embedding_client()
    specification = current_embedding_specification(client)
    threshold = settings.semantic_min_similarity if min_similarity is None else max(-1.0, min(1.0, float(min_similarity)))
    limit = max(1, min(100, int(limit)))
    offset = max(0, min(100000, int(offset)))
    if not client.configured:
        return {
            "schema": REPRESENTATION_SEARCH_CONTRACT,
            "query": query,
            "query_hash_sha256": hashlib.sha256(query.encode("utf-8")).hexdigest(),
            "available": False,
            "reason": "embedding-provider-not-configured",
            "results": [],
            "count": 0,
            "guardrails": {
                "semantic_similarity_is_evidence": False,
                "semantic_similarity_is_truth": False,
                "semantic_similarity_is_causality": False,
            },
        }

    from .hybrid_retrieval import semantic_candidates

    query_vector = client.embed(query).values
    candidate_limit = max(limit + offset, min(500, max(40, (limit + offset) * settings.hybrid_candidate_multiplier)))
    rows = semantic_candidates(
        query_vector,
        object_type=object_type,
        source_key=source_key,
        topic=topic,
        year_from=year_from,
        year_to=year_to,
        limit=candidate_limit,
        specification_fingerprint=specification["fingerprint_sha256"],
        min_similarity=threshold,
    )
    page = rows[offset: offset + limit]
    for rank, row in enumerate(page, start=offset + 1):
        row["semantic_rank"] = rank
        row["representation"] = _representation_block(row)
    return {
        "schema": REPRESENTATION_SEARCH_CONTRACT,
        "query": query,
        "query_hash_sha256": hashlib.sha256(query.encode("utf-8")).hexdigest(),
        "available": True,
        "specification": {
            "specification_id": specification["specification_id"],
            "fingerprint_sha256": specification["fingerprint_sha256"],
            "provider": specification["provider"],
            "model": specification["model"],
            "dimensions": specification["dimensions"],
        },
        "filters": {
            "object_type": object_type,
            "source_key": source_key,
            "topic": topic,
            "year_from": year_from,
            "year_to": year_to,
            "min_similarity": threshold,
        },
        "candidate_count": len(rows),
        "count": len(page),
        "limit": limit,
        "offset": offset,
        "results": page,
        "guardrails": {
            "current_content_only": True,
            "current_specification_only": True,
            "semantic_similarity_is_evidence": False,
            "semantic_similarity_is_truth": False,
            "semantic_similarity_is_causality": False,
            "automatic_core_promotion": False,
        },
    }


def similar_records(
    record_id: str,
    *,
    object_type: str | None = None,
    source_key: str | None = None,
    topic: str | None = None,
    year_from: int | None = None,
    year_to: int | None = None,
    min_similarity: float | None = None,
    limit: int = 20,
    offset: int = 0,
) -> dict[str, Any]:
    configured_specification = current_embedding_specification()
    threshold = settings.semantic_min_similarity if min_similarity is None else max(-1.0, min(1.0, float(min_similarity)))
    limit = max(1, min(100, int(limit)))
    offset = max(0, min(100000, int(offset)))
    where, filter_params = _filters(object_type, source_key, topic, year_from, year_to)
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.record_id,r.title,r.object_type,r.canonical_url,r.content_hash AS record_content_hash,
                   e.content_hash AS embedding_content_hash,e.embedding,e.provider AS embedding_provider,
                   e.model AS embedding_model,e.dimensions AS embedding_dimensions,e.specification_fingerprint,
                   e.representation_id,e.execution_target AS embedding_execution_target,e.execution_id AS embedding_execution_id
              FROM library_records r
              JOIN library_record_embeddings e ON e.record_id=r.record_id
             WHERE r.record_id=%s
               AND e.content_hash=r.content_hash
               AND e.specification_fingerprint IS NOT NULL
            """,
            (record_id,),
        )
        seed = cur.fetchone()
        if not seed:
            raise ValueError("record does not have a current governed semantic representation")
        seed = dict(seed)
        vector = seed.pop("embedding")
        dimensions = int(seed.get("embedding_dimensions") or 0)
        seed_specification_fingerprint = str(seed.get("specification_fingerprint") or "")
        candidate_limit = max(limit + offset, min(500, max(40, (limit + offset) * settings.hybrid_candidate_multiplier)))
        cur.execute(
            f"""
            SELECT r.record_id,r.object_type,r.title,r.canonical_url,r.abstract,r.source_key,
                   r.published_at,r.source_updated_at,r.indexed_at,r.authors,r.topics,r.tags,
                   r.identifiers,r.metadata,r.content_hash AS record_content_hash,
                   e.content_hash AS embedding_content_hash,e.provider AS embedding_provider,
                   e.model AS embedding_model,e.dimensions AS embedding_dimensions,e.specification_fingerprint,
                   e.representation_id,e.execution_target AS embedding_execution_target,
                   e.execution_id AS embedding_execution_id,
                   sc_cosine_similarity(e.embedding,%s::double precision[]) AS semantic_similarity,
                   left(coalesce(nullif(r.abstract,''),r.body_text),420) AS snippet
              FROM library_record_embeddings e
              JOIN library_records r ON r.record_id=e.record_id
             WHERE {where}
               AND e.record_id<>%s
               AND e.content_hash=r.content_hash
               AND e.specification_fingerprint=%s
               AND cardinality(e.embedding)=%s
             ORDER BY semantic_similarity DESC NULLS LAST,r.record_id ASC
             LIMIT %s
            """,
            [vector, *filter_params, record_id, seed_specification_fingerprint, dimensions, candidate_limit],
        )
        rows = [dict(row) for row in cur.fetchall()]

    rows = [row for row in rows if row.get("semantic_similarity") is not None and float(row["semantic_similarity"]) >= threshold]
    page = rows[offset: offset + limit]
    for rank, row in enumerate(page, start=offset + 1):
        row["semantic_rank"] = rank
        row["representation"] = _representation_block(row)
    return {
        "schema": SEMANTIC_SIMILARITY_CONTRACT,
        "seed": {
            "record_id": seed["record_id"],
            "title": seed.get("title"),
            "object_type": seed.get("object_type"),
            "canonical_url": seed.get("canonical_url"),
            "representation": _representation_block(seed),
        },
        "specification": {
            "fingerprint_sha256": seed_specification_fingerprint,
            "provider": seed.get("embedding_provider"),
            "model": seed.get("embedding_model"),
            "dimensions": dimensions,
            "same_as_current_configured_specification": seed_specification_fingerprint == configured_specification["fingerprint_sha256"],
        },
        "filters": {
            "object_type": object_type,
            "source_key": source_key,
            "topic": topic,
            "year_from": year_from,
            "year_to": year_to,
            "min_similarity": threshold,
        },
        "candidate_count": len(rows),
        "count": len(page),
        "limit": limit,
        "offset": offset,
        "results": page,
        "guardrails": {
            "current_content_only": True,
            "same_specification_as_seed_only": True,
            "provider_required_for_seed_search": False,
            "semantic_similarity_is_evidence": False,
            "semantic_similarity_is_truth": False,
            "semantic_similarity_is_causality": False,
            "automatic_core_promotion": False,
        },
    }
