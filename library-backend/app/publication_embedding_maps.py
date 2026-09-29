from __future__ import annotations

from collections import Counter, defaultdict
import hashlib
import json
import math
from typing import Any, Iterable

from .db import get_pool
from .embedding_governance import current_embedding_specification
from .representation_search import similar_records

PUBLICATION_EMBEDDING_MAP_CONTRACT = "sc-library-publication-embedding-map/1.0"
SEMANTIC_KNOWLEDGE_LANDSCAPE_CONTRACT = "sc-library-semantic-knowledge-landscape/1.0"
SEMANTIC_NEIGHBORHOOD_CONTRACT = "sc-library-semantic-neighborhood/1.0"
PROJECTION_CONTRACT = "sc-library-deterministic-pca-projection/1.0"


def _clean_ids(values: Iterable[Any] | None) -> list[str]:
    return list(dict.fromkeys(str(x).strip() for x in (values or []) if str(x).strip()))[:1000]


def _dot(a: list[float], b: list[float]) -> float:
    return sum(x * y for x, y in zip(a, b))


def _norm(a: list[float]) -> float:
    return math.sqrt(max(0.0, _dot(a, a)))


def _unit(a: list[float]) -> list[float]:
    n = _norm(a)
    return [x / n for x in a] if n > 0 else [0.0 for x in a]


def _cosine(a: list[float], b: list[float]) -> float:
    na, nb = _norm(a), _norm(b)
    if na <= 0 or nb <= 0 or len(a) != len(b):
        return 0.0
    return max(-1.0, min(1.0, _dot(a, b) / (na * nb)))


def _center(vectors: list[list[float]]) -> tuple[list[list[float]], list[float]]:
    if not vectors:
        return [], []
    d = len(vectors[0])
    mean = [sum(row[j] for row in vectors) / len(vectors) for j in range(d)]
    return [[row[j] - mean[j] for j in range(d)] for row in vectors], mean


def _principal_component(
    centered: list[list[float]], *, seed_phase: int, orthogonal_to: list[list[float]] | None = None, iterations: int = 28,
) -> list[float]:
    if not centered:
        return []
    d = len(centered[0])
    # Deterministic non-random seed; no process RNG means identical data produces identical projection axes.
    v = [math.sin((j + 1) * (seed_phase + 1) * 0.6180339887498949) + math.cos((j + 1) * 0.2718281828459045) for j in range(d)]
    orthogonal_to = orthogonal_to or []
    for basis in orthogonal_to:
        proj = _dot(v, basis)
        v = [x - proj * y for x, y in zip(v, basis)]
    v = _unit(v)
    for _ in range(iterations):
        # (X^T X)v without materializing a d*d covariance matrix.
        scores = [_dot(row, v) for row in centered]
        nxt = [sum(row[j] * score for row, score in zip(centered, scores)) for j in range(d)]
        for basis in orthogonal_to:
            proj = _dot(nxt, basis)
            nxt = [x - proj * y for x, y in zip(nxt, basis)]
        nxt = _unit(nxt)
        if _norm(nxt) <= 0:
            break
        v = nxt
    # Stable sign convention for reproducible coordinates.
    for value in v:
        if abs(value) > 1e-12:
            if value < 0:
                v = [-x for x in v]
            break
    return v


def deterministic_pca_2d(vectors: list[list[float]]) -> dict[str, Any]:
    if not vectors:
        return {"coordinates": [], "explained_energy_ratio": [0.0, 0.0], "mean": []}
    if len(vectors) == 1:
        return {"coordinates": [(0.0, 0.0)], "explained_energy_ratio": [0.0, 0.0], "mean": list(vectors[0])}
    dimensions = len(vectors[0])
    if dimensions < 1 or any(len(v) != dimensions for v in vectors):
        raise ValueError("embedding vectors must have one shared positive dimensionality")
    centered, mean = _center(vectors)
    pc1 = _principal_component(centered, seed_phase=1)
    pc2 = _principal_component(centered, seed_phase=2, orthogonal_to=[pc1])
    xs = [_dot(row, pc1) for row in centered]
    ys = [_dot(row, pc2) for row in centered]
    max_x = max([abs(x) for x in xs] or [1.0]) or 1.0
    max_y = max([abs(y) for y in ys] or [1.0]) or 1.0
    coordinates = [(round(x / max_x, 7), round(y / max_y, 7)) for x, y in zip(xs, ys)]
    total_energy = sum(_dot(row, row) for row in centered)
    e1 = sum(x * x for x in xs)
    e2 = sum(y * y for y in ys)
    ratios = [round(e1 / total_energy, 7), round(e2 / total_energy, 7)] if total_energy > 0 else [0.0, 0.0]
    return {"coordinates": coordinates, "explained_energy_ratio": ratios, "mean": mean, "axes": [pc1, pc2]}


def _choose_specification(rows: list[dict[str, Any]], configured_fingerprint: str) -> tuple[str | None, str]:
    counts = Counter(str(row.get("specification_fingerprint") or "") for row in rows if row.get("specification_fingerprint"))
    if not counts:
        return None, "none"
    if counts.get(configured_fingerprint, 0) >= 2:
        return configured_fingerprint, "current-configured-specification"
    selected = sorted(counts.items(), key=lambda item: (-item[1], item[0]))[0][0]
    return selected, "dominant-stored-current-content-specification"


def _edge_key(a: str, b: str) -> tuple[str, str]:
    return (a, b) if a < b else (b, a)


def semantic_edges_and_neighborhoods(
    record_ids: list[str], vectors: list[list[float]], *, threshold: float, neighbors_per_point: int, max_edges: int,
) -> tuple[list[dict[str, Any]], dict[str, list[dict[str, Any]]]]:
    threshold = max(-1.0, min(1.0, float(threshold)))
    neighbors_per_point = max(1, min(30, int(neighbors_per_point)))
    max_edges = max(1, min(5000, int(max_edges)))
    pair_scores: dict[tuple[str, str], float] = {}
    by_record: dict[str, list[tuple[str, float]]] = defaultdict(list)
    for i in range(len(record_ids)):
        for j in range(i + 1, len(record_ids)):
            score = _cosine(vectors[i], vectors[j])
            if score < threshold:
                continue
            a, b = record_ids[i], record_ids[j]
            pair_scores[_edge_key(a, b)] = score
            by_record[a].append((b, score))
            by_record[b].append((a, score))
    chosen: set[tuple[str, str]] = set()
    neighborhoods: dict[str, list[dict[str, Any]]] = {}
    for rid in record_ids:
        ranked = sorted(by_record.get(rid, []), key=lambda item: (-item[1], item[0]))[:neighbors_per_point]
        neighborhoods[rid] = [{"record_id": other, "similarity": round(score, 7)} for other, score in ranked]
        for other, _ in ranked:
            chosen.add(_edge_key(rid, other))
    ranked_edges = sorted(chosen, key=lambda key: (-pair_scores[key], key[0], key[1]))[:max_edges]
    edges = [
        {
            "source": a,
            "target": b,
            "relationship_basis": "embedding-cosine-similarity",
            "similarity": round(pair_scores[(a, b)], 7),
            "weight": round(pair_scores[(a, b)], 7),
            "directed": False,
            "analytical": True,
            "truth_assertion": False,
        }
        for a, b in ranked_edges
    ]
    return edges, neighborhoods


def _load_current_representations(
    *, source_key: str, object_type: str, record_ids: list[str], max_publications: int,
) -> tuple[list[dict[str, Any]], int, str]:
    where = ["r.visibility='public'", "r.publication_status='published'", "e.content_hash=r.content_hash", "e.specification_fingerprint IS NOT NULL"]
    params: list[Any] = []
    if source_key:
        where.append("r.source_key=%s")
        params.append(source_key)
    if record_ids:
        where.append("r.record_id=ANY(%s)")
        params.append(record_ids)
        selection = "publication-library-manifest"
    else:
        where.append("r.object_type=%s")
        params.append(object_type or "post")
        selection = "wordpress-post-fallback"
    clause = " AND ".join(where)
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(f"SELECT count(*) AS n FROM library_records r JOIN library_record_embeddings e ON e.record_id=r.record_id WHERE {clause}", tuple(params))
        total = int(cur.fetchone()["n"])
        cur.execute(
            f"""
            SELECT r.record_id,r.title,r.object_type,r.source_key,r.canonical_url,r.abstract,r.published_at,r.topics,r.tags,
                   r.content_hash AS record_content_hash,
                   e.content_hash AS embedding_content_hash,e.input_hash,e.provider,e.model,e.dimensions,e.embedding,
                   e.specification_fingerprint,e.representation_id,e.execution_target,e.execution_id,e.provenance,e.updated_at
              FROM library_records r
              JOIN library_record_embeddings e ON e.record_id=r.record_id
             WHERE {clause}
             ORDER BY r.published_at DESC NULLS LAST,r.indexed_at DESC,r.record_id ASC
             LIMIT %s
            """,
            tuple(params + [max(2, min(1000, max_publications * 4))]),
        )
        rows = [dict(row) for row in cur.fetchall()]
    return rows, total, selection


def embedding_map_readiness() -> dict[str, Any]:
    configured = current_embedding_specification()
    result: dict[str, Any] = {
        "schema": SEMANTIC_KNOWLEDGE_LANDSCAPE_CONTRACT,
        "state": "ready",
        "projection": PROJECTION_CONTRACT,
        "configured_specification_fingerprint_sha256": configured["fingerprint_sha256"],
        "provider_required_to_render_stored_map": False,
        "same_specification_only": True,
        "current_content_only": True,
        "guardrails": {
            "spatial_proximity_is_evidence": False,
            "spatial_proximity_is_truth": False,
            "spatial_proximity_is_causality": False,
            "semantic_edge_is_evidence": False,
            "automatic_platform_core_promotion": False,
        },
    }
    try:
        pool = get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                SELECT count(*) AS n,count(DISTINCT e.specification_fingerprint) AS specs
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id
                 WHERE e.content_hash=r.content_hash
                   AND e.specification_fingerprint IS NOT NULL
                   AND r.visibility='public' AND r.publication_status='published'
                """
            )
            row = cur.fetchone()
            result["eligible_current_representations"] = int(row["n"])
            result["stored_specification_count"] = int(row["specs"])
    except Exception as exc:
        result["state"] = "unavailable"
        result["error"] = exc.__class__.__name__
    return result


def build_publication_embedding_map(
    *, source_key: str = "wordpress-main", object_type: str = "", record_ids: list[str] | tuple[str, ...] | None = None,
    similarity_threshold: float = 0.72, neighbors_per_point: int = 8, max_edges: int = 1200, max_publications: int = 250,
) -> dict[str, Any]:
    ids = _clean_ids(record_ids)
    max_publications = max(2, min(500, int(max_publications)))
    rows, total, selection = _load_current_representations(
        source_key=str(source_key or "").strip(), object_type=str(object_type or "").strip(), record_ids=ids, max_publications=max_publications,
    )
    configured = current_embedding_specification()
    selected_fp, selection_reason = _choose_specification(rows, configured["fingerprint_sha256"])
    selected = [row for row in rows if str(row.get("specification_fingerprint") or "") == str(selected_fp or "")][:max_publications]
    if not selected_fp or not selected:
        return {
            "schema": PUBLICATION_EMBEDDING_MAP_CONTRACT,
            "landscape_schema": SEMANTIC_KNOWLEDGE_LANDSCAPE_CONTRACT,
            "available": False,
            "reason": "no-current-governed-publication-representations",
            "points": [], "edges": [], "neighborhoods": {},
            "corpus": {"selection": selection, "eligible_current_representation_count": total, "mapped_publication_count": 0},
            "guardrails": embedding_map_readiness()["guardrails"],
        }
    dimensions = int(selected[0].get("dimensions") or 0)
    selected = [row for row in selected if int(row.get("dimensions") or 0) == dimensions and len(list(row.get("embedding") or [])) == dimensions]
    record_ids_selected = [str(row["record_id"]) for row in selected]
    vectors = [[float(x) for x in (row.get("embedding") or [])] for row in selected]
    projection = deterministic_pca_2d(vectors)
    edges, neighborhoods = semantic_edges_and_neighborhoods(
        record_ids_selected, vectors, threshold=similarity_threshold, neighbors_per_point=neighbors_per_point, max_edges=max_edges,
    )
    points: list[dict[str, Any]] = []
    for row, (x, y) in zip(selected, projection["coordinates"]):
        rid = str(row["record_id"])
        points.append({
            "record_id": rid,
            "title": row.get("title"),
            "object_type": row.get("object_type"),
            "canonical_url": row.get("canonical_url"),
            "published_at": str(row.get("published_at") or "") or None,
            "topics": list(row.get("topics") or []),
            "tags": list(row.get("tags") or []),
            "x": x, "y": y,
            "semantic_neighbor_count": len(neighborhoods.get(rid, [])),
            "representation": {
                "representation_id": row.get("representation_id"),
                "specification_fingerprint_sha256": row.get("specification_fingerprint"),
                "provider": row.get("provider"),
                "model": row.get("model"),
                "dimensions": dimensions,
                "content_hash": row.get("embedding_content_hash"),
                "input_hash": row.get("input_hash"),
                "execution_target": row.get("execution_target"),
                "execution_id": row.get("execution_id"),
            },
        })
    selected_spec = selected[0]
    map_material = {
        "records": record_ids_selected,
        "specification_fingerprint": selected_fp,
        "threshold": round(float(similarity_threshold), 7),
        "neighbors_per_point": int(neighbors_per_point),
        "projection": PROJECTION_CONTRACT,
    }
    map_id = "publication-embedding-map:" + hashlib.sha256(json.dumps(map_material, sort_keys=True, separators=(",", ":")).encode("utf-8")).hexdigest()[:32]
    for edge in edges:
        edge["specification_fingerprint_sha256"] = selected_fp
        edge["source_representation_id"] = next((p["representation"]["representation_id"] for p in points if p["record_id"] == edge["source"]), None)
        edge["target_representation_id"] = next((p["representation"]["representation_id"] for p in points if p["record_id"] == edge["target"]), None)
    available = len(points) >= 2
    return {
        "schema": PUBLICATION_EMBEDDING_MAP_CONTRACT,
        "landscape_schema": SEMANTIC_KNOWLEDGE_LANDSCAPE_CONTRACT,
        "map_id": map_id,
        "available": available,
        "reason": "ready" if available else "at-least-two-compatible-representations-required",
        "projection": {
            "schema": PROJECTION_CONTRACT,
            "method": "deterministic-centered-pca-power-iteration",
            "dimensions_in": dimensions,
            "dimensions_out": 2,
            "explained_energy_ratio": projection["explained_energy_ratio"],
            "coordinate_range": [-1.0, 1.0],
            "deterministic": True,
            "projection_coordinates_are_semantic_measurements": False,
        },
        "specification": {
            "fingerprint_sha256": selected_fp,
            "selection_reason": selection_reason,
            "same_specification_only": True,
            "same_as_current_configured_specification": selected_fp == configured["fingerprint_sha256"],
            "provider": selected_spec.get("provider"),
            "model": selected_spec.get("model"),
            "dimensions": dimensions,
        },
        "corpus": {
            "source_key": source_key or None,
            "object_type": object_type or (None if selection == "publication-library-manifest" else "post"),
            "selection": selection,
            "requested_manifest_count": len(ids),
            "eligible_current_representation_count": total,
            "mapped_publication_count": len(points),
            "truncated": total > len(points),
            "max_publications": max_publications,
        },
        "similarity": {
            "metric": "cosine",
            "threshold": max(-1.0, min(1.0, float(similarity_threshold))),
            "neighbors_per_point": max(1, min(30, int(neighbors_per_point))),
            "edge_count": len(edges),
            "edge_policy": "symmetric-top-k-union-above-threshold",
        },
        "points": points,
        "edges": edges,
        "neighborhoods": neighborhoods,
        "linked_view": {
            "node_position_lookup": {p["record_id"]: {"x": p["x"], "y": p["y"]} for p in points},
            "relationship_basis": "embedding-cosine-similarity",
            "compatible_views": ["knowledge-landscape", "semantic-overlay", "relationship-matrix", "visual-query"],
            "selection_key": "record_id",
        },
        "guardrails": {
            "current_content_only": True,
            "same_specification_only": True,
            "spatial_proximity_is_evidence": False,
            "spatial_proximity_is_truth": False,
            "spatial_proximity_is_causality": False,
            "semantic_edge_is_evidence": False,
            "projection_axis_has_intrinsic_domain_meaning": False,
            "automatic_platform_core_promotion": False,
        },
        "provenance": {
            "source_product": "knowledge-library",
            "representation_authority": "library-operational-vector-index",
            "governed_contract_authority": "platform-core-compatible-contracts",
            "projection_runtime": "library-python-backend",
            "source_content_hash_bound": True,
            "representation_ids_exposed": True,
            "specification_fingerprint_exposed": True,
        },
    }


def semantic_neighborhood(record_id: str, *, limit: int = 12, min_similarity: float = 0.0) -> dict[str, Any]:
    result = similar_records(record_id, limit=max(1, min(50, int(limit))), min_similarity=min_similarity)
    return {
        "schema": SEMANTIC_NEIGHBORHOOD_CONTRACT,
        "record_id": record_id,
        "seed": result.get("seed"),
        "specification": result.get("specification"),
        "neighbors": [
            {
                "record_id": row.get("record_id"),
                "title": row.get("title"),
                "canonical_url": row.get("canonical_url"),
                "similarity": row.get("semantic_similarity"),
                "semantic_rank": row.get("semantic_rank"),
                "representation": row.get("representation"),
            }
            for row in result.get("results", [])
        ],
        "count": result.get("count", 0),
        "guardrails": {
            "semantic_neighborhood_is_evidence": False,
            "semantic_neighborhood_is_truth": False,
            "semantic_neighborhood_is_causality": False,
            "same_specification_as_seed_only": True,
            "current_content_only": True,
        },
    }
