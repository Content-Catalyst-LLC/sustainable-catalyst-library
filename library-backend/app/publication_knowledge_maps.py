from __future__ import annotations

from collections import defaultdict
import hashlib
import math
from typing import Any

from .db import get_pool

KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-knowledge-map/1.0"
KNOWLEDGE_MAP_READINESS_CONTRACT = "sc-library-publication-knowledge-map-readiness/1.0"


def _topic_id(label: str) -> str:
    normalized = " ".join(str(label or "").strip().lower().split())
    return "topic:" + hashlib.sha256(normalized.encode("utf-8")).hexdigest()[:20]


def _cosine(a: list[float], b: list[float]) -> float:
    if not a or len(a) != len(b):
        return 0.0
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(y * y for y in b))
    if na <= 0.0 or nb <= 0.0:
        return 0.0
    return max(-1.0, min(1.0, dot / (na * nb)))


def _record(cur: Any, record_id: str) -> dict[str, Any]:
    cur.execute(
        """
        SELECT record_id,title,canonical_url,object_type,content_hash,authors,topics,tags,identifiers,
               visibility,publication_status,published_at,indexed_at,metadata
          FROM library_records
         WHERE record_id=%s
        """,
        (record_id,),
    )
    row = cur.fetchone()
    if not row:
        raise ValueError("record_id does not exist")
    record = dict(row)
    if record.get("visibility") != "public" or record.get("publication_status") != "published":
        raise ValueError("knowledge maps require a public, published Library record")
    return record


def _as_list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _add_node(nodes: dict[str, dict[str, Any]], node_id: str, kind: str, label: str, **extra: Any) -> None:
    if node_id in nodes:
        # Preserve the first canonical label but merge useful source metadata.
        nodes[node_id].update({k: v for k, v in extra.items() if v not in (None, "", [], {})})
        return
    nodes[node_id] = {"id": node_id, "kind": kind, "label": label, **{k: v for k, v in extra.items() if v is not None}}


def _edge_key(source: str, target: str, basis: str, directed: bool) -> tuple[str, str, str, bool]:
    if directed or source <= target:
        return source, target, basis, directed
    return target, source, basis, directed


def _add_edge(edges: dict[tuple[str, str, str, bool], dict[str, Any]], source: str, target: str, basis: str, *, directed: bool, weight: float = 1.0, **extra: Any) -> None:
    if source == target:
        return
    key = _edge_key(source, target, basis, directed)
    if key in edges:
        edges[key]["weight"] = round(float(edges[key].get("weight", 1.0)) + float(weight), 6)
        if extra.get("evidence_count"):
            edges[key]["evidence_count"] = int(edges[key].get("evidence_count", 1)) + int(extra["evidence_count"])
        return
    edges[key] = {
        "id": f"edge:{hashlib.sha256('|'.join(map(str, key)).encode('utf-8')).hexdigest()[:20]}",
        "source": key[0],
        "target": key[1],
        "relationship_basis": basis,
        "directed": directed,
        "weight": round(float(weight), 6),
        **extra,
    }


def build_publication_knowledge_map(
    record_id: str,
    *,
    include_citations: bool = True,
    include_semantic_similarity: bool = True,
    semantic_threshold: float = 0.72,
    max_neighbors: int = 40,
    max_topics_per_publication: int = 36,
) -> dict[str, Any]:
    semantic_threshold = max(0.0, min(1.0, float(semantic_threshold)))
    max_neighbors = max(0, min(100, int(max_neighbors)))
    max_topics_per_publication = max(1, min(100, int(max_topics_per_publication)))

    pool = get_pool()
    nodes: dict[str, dict[str, Any]] = {}
    edges: dict[tuple[str, str, str, bool], dict[str, Any]] = {}
    citation_neighbor_ids: list[str] = []
    records: dict[str, dict[str, Any]] = {}
    semantic_status = {
        "requested": bool(include_semantic_similarity),
        "available": False,
        "reason": "not-requested" if not include_semantic_similarity else "insufficient-current-embeddings",
        "threshold": semantic_threshold,
        "vector_count": 0,
        "model": None,
        "provider": None,
    }

    with pool.connection() as conn, conn.cursor() as cur:
        root = _record(cur, record_id)
        records[record_id] = root

        if include_citations and max_neighbors:
            cur.execute(
                """
                SELECT citation_id,citing_record_id,cited_record_id,relation_type,resolution_status,
                       identifier_type,identifier_value,locator,confidence
                  FROM library_citations
                 WHERE resolution_status='resolved'
                   AND cited_record_id IS NOT NULL
                   AND (citing_record_id=%s OR cited_record_id=%s)
                 ORDER BY citation_id DESC
                 LIMIT %s
                """,
                (record_id, record_id, max_neighbors),
            )
            citation_rows = [dict(row) for row in cur.fetchall()]
            for item in citation_rows:
                other = str(item["cited_record_id"] if item["citing_record_id"] == record_id else item["citing_record_id"])
                if other and other != record_id and other not in citation_neighbor_ids:
                    citation_neighbor_ids.append(other)
            if citation_neighbor_ids:
                cur.execute(
                    """
                    SELECT record_id,title,canonical_url,object_type,content_hash,authors,topics,tags,identifiers,
                           visibility,publication_status,published_at,indexed_at,metadata
                      FROM library_records
                     WHERE record_id=ANY(%s)
                       AND visibility='public' AND publication_status='published'
                    """,
                    (citation_neighbor_ids,),
                )
                for row in cur.fetchall():
                    item = dict(row)
                    records[str(item["record_id"])] = item
            for item in citation_rows:
                source = str(item["citing_record_id"])
                target = str(item["cited_record_id"])
                if source in records and target in records:
                    _add_edge(
                        edges, source, target, "explicit-citation", directed=True,
                        weight=max(0.05, float(item.get("confidence") or 1.0)),
                        relation_type=str(item.get("relation_type") or "cites"),
                        citation_id=item.get("citation_id"),
                        locator=item.get("locator"),
                        analytical=False,
                        truth_assertion=False,
                    )

        for rid, rec in records.items():
            _add_node(
                nodes, rid, "publication", str(rec.get("title") or rid),
                root=(rid == record_id), canonical_url=rec.get("canonical_url"),
                published_at=str(rec.get("published_at") or ""),
                object_type=rec.get("object_type"), source_content_hash=rec.get("content_hash"),
            )

        record_ids = list(records)
        cur.execute(
            """
            SELECT candidate_id,record_id,candidate_text,entity_type,confidence,source_locator,
                   source_chunk_ordinal,char_start,char_end,source_content_hash,reviewer
              FROM library_research_candidates
             WHERE record_id=ANY(%s)
               AND candidate_type='entity'
               AND entity_type='concept'
               AND review_state='accepted'
             ORDER BY record_id,confidence DESC,candidate_id ASC
            """,
            (record_ids,),
        )
        accepted_concepts = [dict(row) for row in cur.fetchall()]

        topic_sources: dict[tuple[str, str], dict[str, Any]] = {}
        for rid, rec in records.items():
            labels: list[tuple[str, str, float]] = []
            labels.extend((str(x), "record-topic", 1.0) for x in _as_list(rec.get("topics")))
            labels.extend((str(x), "record-tag", 0.75) for x in _as_list(rec.get("tags")))
            seen_labels: set[str] = set()
            for label, source_type, confidence in labels:
                norm = " ".join(label.strip().split())
                if not norm or norm.lower() in seen_labels:
                    continue
                seen_labels.add(norm.lower())
                tid = _topic_id(norm)
                _add_node(nodes, tid, "topic", norm, source_type=source_type, reviewed=True)
                _add_edge(
                    edges, rid, tid, "metadata-association", directed=False, weight=confidence,
                    evidence_count=1, source_type=source_type, analytical=False, truth_assertion=False,
                )
                topic_sources[(rid, tid)] = {"source_type": source_type, "confidence": confidence}
                if len(seen_labels) >= max_topics_per_publication:
                    break

        chunk_topics: dict[tuple[str, int], list[str]] = defaultdict(list)
        per_record_concepts: dict[str, int] = defaultdict(int)
        for item in accepted_concepts:
            rid = str(item["record_id"])
            if rid not in records or per_record_concepts[rid] >= max_topics_per_publication:
                continue
            label = " ".join(str(item.get("candidate_text") or "").strip().split())
            if not label:
                continue
            tid = _topic_id(label)
            confidence = max(0.0, min(1.0, float(item.get("confidence") or 0.0)))
            _add_node(
                nodes, tid, "topic", label, source_type="accepted-concept", reviewed=True,
                confidence=confidence, source_locator=item.get("source_locator"),
                source_chunk_ordinal=item.get("source_chunk_ordinal"),
                source_content_hash=item.get("source_content_hash"), reviewed_by=item.get("reviewer"),
            )
            _add_edge(
                edges, rid, tid, "reviewed-concept-association", directed=False,
                weight=max(0.05, confidence), evidence_count=1,
                source_locator=item.get("source_locator"), analytical=False, truth_assertion=False,
            )
            ordinal = item.get("source_chunk_ordinal")
            if ordinal is not None:
                chunk_topics[(rid, int(ordinal))].append(tid)
            per_record_concepts[rid] += 1

        # Topic-topic relationships are only created from observed source-span co-occurrence.
        for (rid, ordinal), tids in chunk_topics.items():
            unique = list(dict.fromkeys(tids))[:30]
            for i in range(len(unique)):
                for j in range(i + 1, len(unique)):
                    _add_edge(
                        edges, unique[i], unique[j], "source-span-cooccurrence", directed=False,
                        weight=1.0, evidence_count=1, publication_record_id=rid,
                        source_chunk_ordinal=ordinal, analytical=True, truth_assertion=False,
                    )

        # Real semantic similarity is computed only from current stored embeddings.
        if include_semantic_similarity and len(records) >= 2:
            cur.execute(
                """
                SELECT e.record_id,e.provider,e.model,e.dimensions,e.embedding
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id AND r.content_hash=e.content_hash
                 WHERE e.record_id=ANY(%s)
                 ORDER BY e.updated_at DESC
                """,
                (record_ids,),
            )
            vectors: dict[str, dict[str, Any]] = {}
            for row in cur.fetchall():
                item = dict(row)
                rid = str(item["record_id"])
                if rid not in vectors:
                    vectors[rid] = item
            semantic_status["vector_count"] = len(vectors)
            if len(vectors) >= 2:
                semantic_status.update({
                    "available": True,
                    "reason": "current-record-embeddings",
                    "provider": next(iter(vectors.values())).get("provider"),
                    "model": next(iter(vectors.values())).get("model"),
                })
                ids = list(vectors)
                for i in range(len(ids)):
                    for j in range(i + 1, len(ids)):
                        a, b = vectors[ids[i]], vectors[ids[j]]
                        if a.get("provider") != b.get("provider") or a.get("model") != b.get("model") or a.get("dimensions") != b.get("dimensions"):
                            continue
                        score = _cosine(list(a.get("embedding") or []), list(b.get("embedding") or []))
                        if score >= semantic_threshold:
                            _add_edge(
                                edges, ids[i], ids[j], "embedding-cosine-similarity", directed=False,
                                weight=score, similarity=round(score, 6), analytical=True,
                                truth_assertion=False, provider=a.get("provider"), model=a.get("model"),
                            )

    edge_items = list(edges.values())
    degree: dict[str, float] = defaultdict(float)
    citations: dict[str, int] = defaultdict(int)
    semantic_links: dict[str, int] = defaultdict(int)
    for edge in edge_items:
        source, target = str(edge["source"]), str(edge["target"])
        weight = float(edge.get("weight") or 1.0)
        degree[source] += weight
        degree[target] += weight
        if edge.get("relationship_basis") == "explicit-citation":
            citations[source] += 1
            citations[target] += 1
        if edge.get("relationship_basis") == "embedding-cosine-similarity":
            semantic_links[source] += 1
            semantic_links[target] += 1

    for node_id, node in nodes.items():
        node["metrics"] = {
            "weighted_degree": round(degree.get(node_id, 0.0), 6),
            "citation_links": citations.get(node_id, 0),
            "semantic_links": semantic_links.get(node_id, 0),
        }

    node_items = list(nodes.values())
    publication_count = sum(1 for x in node_items if x.get("kind") == "publication")
    topic_count = sum(1 for x in node_items if x.get("kind") == "topic")
    relationship_counts: dict[str, int] = defaultdict(int)
    for edge in edge_items:
        relationship_counts[str(edge.get("relationship_basis") or "unknown")] += 1

    return {
        "schema": KNOWLEDGE_MAP_CONTRACT,
        "record_id": record_id,
        "title": f"Knowledge Landscape — {records[record_id].get('title')}",
        "analysis_kind": "publication-knowledge-landscape",
        "nodes": node_items,
        "edges": edge_items,
        "metrics": {
            "node_count": len(node_items),
            "edge_count": len(edge_items),
            "publication_count": publication_count,
            "topic_count": topic_count,
            "relationship_counts": dict(sorted(relationship_counts.items())),
        },
        "semantic_analysis": semantic_status,
        "views": [
            {"key": "knowledge-landscape", "label": "Knowledge Landscape", "purpose": "Combined publication/topic relationship field"},
            {"key": "topic-graph", "label": "Topic Graph", "purpose": "Topics and measured co-occurrence"},
            {"key": "citation-overlay", "label": "Citation Overlay", "purpose": "Explicit scholarly citation structure"},
            {"key": "semantic-overlay", "label": "Semantic Overlay", "purpose": "Embedding similarity when real current vectors exist"},
            {"key": "relationship-matrix", "label": "Relationship Matrix", "purpose": "Pairwise analytical relationship inspection"},
        ],
        "renderer_profile": {
            "family": "scientific-knowledge-landscape",
            "renderer_neutral": True,
            "preferred_runtime": "interactive-svg-webgl-capable",
            "layout": "force-directed-multilayer",
            "node_channels": ["kind", "weighted_degree", "source_type"],
            "edge_channels": ["relationship_basis", "weight", "directed"],
            "interactions": ["zoom", "pan", "select", "filter", "focus", "inspect-source", "toggle-layer"],
            "core_visual_runtime_targets": [
                "/v1/visual-runtime/unified",
                "/v1/visual-runtime/grammar",
                "/v1/visual-runtime/linked-views",
                "/v1/visual-runtime/query",
                "/v1/visualization",
            ],
        },
        "provenance": {
            "source_product": "knowledge-library",
            "relationship_methods": [
                "explicit-citation",
                "record-metadata-association",
                "human-reviewed-concept-association",
                "source-span-cooccurrence",
                "stored-embedding-cosine-similarity-if-available",
            ],
            "governed_visual_reasoning_authority": "platform-core",
        },
        "boundaries": {
            "llm_inferred_edges": False,
            "automatic_truth_promotion": False,
            "semantic_edges_are_truth_claims": False,
            "unresolved_citations_guessed": False,
            "human_review_required_for_extracted_concepts": True,
        },
    }


def knowledge_map_readiness() -> dict[str, Any]:
    pool = get_pool()
    storage_ready = False
    storage_error: str | None = None
    counts = {"records": 0, "wordpress_publications": 0, "wordpress_publication_posts": 0, "accepted_concepts": 0, "resolved_citations": 0, "current_embeddings": 0}
    try:
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS n FROM library_records WHERE visibility='public' AND publication_status='published'")
            counts["records"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_records WHERE visibility='public' AND publication_status='published' AND source_key='wordpress-main' AND object_type='post'")
            counts["wordpress_publications"] = int(cur.fetchone()["n"])
            counts["wordpress_publication_posts"] = counts["wordpress_publications"]
            cur.execute("SELECT count(*) AS n FROM library_research_candidates WHERE candidate_type='entity' AND entity_type='concept' AND review_state='accepted'")
            counts["accepted_concepts"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_citations WHERE resolution_status='resolved' AND cited_record_id IS NOT NULL")
            counts["resolved_citations"] = int(cur.fetchone()["n"])
            cur.execute("SELECT count(*) AS n FROM library_record_embeddings e JOIN library_records r ON r.record_id=e.record_id AND r.content_hash=e.content_hash")
            counts["current_embeddings"] = int(cur.fetchone()["n"])
        storage_ready = True
    except Exception as exc:
        storage_error = exc.__class__.__name__
    return {
        "schema": KNOWLEDGE_MAP_READINESS_CONTRACT,
        "publication_knowledge_mapping": True,
        "publication_corpus_integration": True,
        "multi_publication_knowledge_landscape": True,
        "topic_region_analysis": True,
        "cross_publication_relationships": True,
        "temporal_topic_dynamics": True,
        "relationship_matrix": True,
        "bridge_node_diagnostics": True,
        "four_dimensional_knowledge_model_ready": True,
        "four_dimensional_knowledge_terrain": True,
        "terrain_elevation_metrics": True,
        "terrain_time_playback": True,
        "terrain_topic_anchors": True,
        "terrain_temporal_keyframes": True,
        "default_corpus_source": "wordpress-main",
        "live_library_records": True,
        "canonical_publication_manifest_supported": True,
        "safe_backend_fallback_object_type": "post",
        "scientific_graphical_analysis": True,
        "source_anchored_topic_relationships": True,
        "citation_overlay": True,
        "semantic_similarity_from_real_embeddings_only": True,
        "interactive_research_library_renderer": True,
        "workspace_portable_contract": True,
        "platform_core_visual_runtime_alignment": True,
        "storage_ready": storage_ready,
        "storage_error": storage_error,
        "counts": counts,
        "boundaries": {
            "llm_inferred_edges": False,
            "automatic_truth_promotion": False,
            "semantic_edges_are_truth_claims": False,
            "non_publication_wordpress_types_excluded": True,
        },
    }
