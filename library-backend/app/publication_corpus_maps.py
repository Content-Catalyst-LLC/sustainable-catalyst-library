from __future__ import annotations

from collections import defaultdict
from typing import Any

from .db import get_pool
from .publication_knowledge_maps import _add_edge, _add_node, _as_list, _cosine, _topic_id

CORPUS_KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-corpus-knowledge-map/1.0"


def _eligible_records(
    cur: Any, *, source_key: str = "wordpress-main", object_type: str = "",
    record_ids: list[str] | tuple[str, ...] | None = None, max_publications: int = 250,
) -> tuple[dict[str, dict[str, Any]], int, str]:
    source_key = str(source_key or "").strip()
    object_type = str(object_type or "").strip()
    max_publications = max(1, min(1000, int(max_publications)))
    manifest_ids = list(dict.fromkeys(str(x).strip() for x in (record_ids or []) if str(x).strip()))[:1000]
    where = ["visibility='public'", "publication_status='published'"]
    params: list[Any] = []
    if source_key:
        where.append("source_key=%s")
        params.append(source_key)
    if manifest_ids:
        where.append("record_id=ANY(%s)")
        params.append(manifest_ids)
        selection_mode = "publication-library-manifest"
    else:
        # Safe backend fallback: generic wordpress-main calls analyze editorial
        # posts only. Pages, Foundation documents, support content, and other
        # indexed object types are not silently treated as publications.
        object_type = object_type or "post"
        where.append("object_type=%s")
        params.append(object_type)
        selection_mode = "wordpress-post-fallback"
    clause = " AND ".join(where)
    cur.execute(f"SELECT count(*) AS n FROM library_records WHERE {clause}", tuple(params))
    total = int(cur.fetchone()["n"])
    cur.execute(
        f"""
        SELECT record_id,source_key,title,canonical_url,object_type,content_hash,authors,topics,tags,identifiers,
               visibility,publication_status,published_at,indexed_at,metadata
          FROM library_records
         WHERE {clause}
         ORDER BY published_at DESC NULLS LAST,indexed_at DESC,record_id ASC
         LIMIT %s
        """,
        tuple(params + [max_publications]),
    )
    records: dict[str, dict[str, Any]] = {}
    for row in cur.fetchall():
        item = dict(row)
        records[str(item["record_id"])] = item
    return records, total, selection_mode


def build_publication_corpus_knowledge_map(
    *,
    source_key: str = "wordpress-main",
    object_type: str = "",
    record_ids: list[str] | tuple[str, ...] | None = None,
    include_citations: bool = True,
    include_semantic_similarity: bool = True,
    semantic_threshold: float = 0.72,
    max_publications: int = 250,
    max_topics_per_publication: int = 36,
) -> dict[str, Any]:
    semantic_threshold = max(0.0, min(1.0, float(semantic_threshold)))
    max_publications = max(1, min(1000, int(max_publications)))
    max_topics_per_publication = max(1, min(100, int(max_topics_per_publication)))

    pool = get_pool()
    nodes: dict[str, dict[str, Any]] = {}
    edges: dict[tuple[str, str, str, bool], dict[str, Any]] = {}
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
        records, total_eligible, selection_mode = _eligible_records(
            cur, source_key=source_key, object_type=object_type, record_ids=record_ids, max_publications=max_publications
        )
        if not records:
            return {
                "schema": CORPUS_KNOWLEDGE_MAP_CONTRACT,
                "scope": "corpus",
                "title": "Publication Corpus Knowledge Landscape",
                "analysis_kind": "publication-corpus-knowledge-landscape",
                "nodes": [],
                "edges": [],
                "metrics": {"node_count": 0, "edge_count": 0, "publication_count": 0, "topic_count": 0, "relationship_counts": {}},
                "corpus": {
                    "source_key": source_key or None,
                    "object_type": object_type or (None if selection_mode == "publication-library-manifest" else "post"),
                    "selection": selection_mode,
                    "requested_manifest_count": len(record_ids or []),
                    "eligible_publication_count": 0,
                    "analyzed_publication_count": 0,
                    "truncated": False,
                },
                "semantic_analysis": semantic_status,
                "boundaries": {
                    "llm_inferred_edges": False,
                    "automatic_truth_promotion": False,
                    "semantic_edges_are_truth_claims": False,
                    "unresolved_citations_guessed": False,
                },
            }

        record_ids = list(records)
        for rid, rec in records.items():
            _add_node(
                nodes, rid, "publication", str(rec.get("title") or rid),
                root=False, corpus_member=True, canonical_url=rec.get("canonical_url"),
                published_at=str(rec.get("published_at") or ""), object_type=rec.get("object_type"),
                source_key=rec.get("source_key"), source_content_hash=rec.get("content_hash"),
                authors=_as_list(rec.get("authors")),
            )

        if include_citations and len(record_ids) >= 2:
            cur.execute(
                """
                SELECT citation_id,citing_record_id,cited_record_id,relation_type,resolution_status,
                       identifier_type,identifier_value,locator,confidence
                  FROM library_citations
                 WHERE resolution_status='resolved'
                   AND cited_record_id IS NOT NULL
                   AND citing_record_id=ANY(%s)
                   AND cited_record_id=ANY(%s)
                 ORDER BY citation_id DESC
                """,
                (record_ids, record_ids),
            )
            for row in cur.fetchall():
                item = dict(row)
                _add_edge(
                    edges, str(item["citing_record_id"]), str(item["cited_record_id"]),
                    "explicit-citation", directed=True,
                    weight=max(0.05, float(item.get("confidence") or 1.0)),
                    relation_type=str(item.get("relation_type") or "cites"),
                    citation_id=item.get("citation_id"), locator=item.get("locator"),
                    analytical=False, truth_assertion=False,
                )

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

        per_record_topics: dict[str, list[str]] = defaultdict(list)
        for rid, rec in records.items():
            labels: list[tuple[str, str, float]] = []
            labels.extend((str(x), "record-topic", 1.0) for x in _as_list(rec.get("topics")))
            labels.extend((str(x), "record-tag", 0.75) for x in _as_list(rec.get("tags")))
            seen_labels: set[str] = set()
            for label, source_type, confidence in labels:
                norm = " ".join(label.strip().split())
                if not norm or norm.casefold() in seen_labels:
                    continue
                seen_labels.add(norm.casefold())
                tid = _topic_id(norm)
                _add_node(nodes, tid, "topic", norm, source_type=source_type, reviewed=True)
                _add_edge(
                    edges, rid, tid, "metadata-association", directed=False, weight=confidence,
                    evidence_count=1, source_type=source_type, analytical=False, truth_assertion=False,
                )
                per_record_topics[rid].append(tid)
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
            per_record_topics[rid].append(tid)
            ordinal = item.get("source_chunk_ordinal")
            if ordinal is not None:
                chunk_topics[(rid, int(ordinal))].append(tid)
            per_record_concepts[rid] += 1

        # Measured topic proximity within a publication. The cap prevents quadratic
        # explosion on heavily tagged records while preserving the strongest local structure.
        for rid, tids in per_record_topics.items():
            unique = list(dict.fromkeys(tids))[:14]
            for i in range(len(unique)):
                for j in range(i + 1, len(unique)):
                    _add_edge(
                        edges, unique[i], unique[j], "publication-topic-cooccurrence", directed=False,
                        weight=1.0, evidence_count=1, publication_record_id=rid,
                        analytical=True, truth_assertion=False,
                    )

        # More specific concept proximity when two reviewed concepts occur in the same source chunk.
        for (rid, ordinal), tids in chunk_topics.items():
            unique = list(dict.fromkeys(tids))[:30]
            for i in range(len(unique)):
                for j in range(i + 1, len(unique)):
                    _add_edge(
                        edges, unique[i], unique[j], "source-span-cooccurrence", directed=False,
                        weight=1.0, evidence_count=1, publication_record_id=rid,
                        source_chunk_ordinal=ordinal, analytical=True, truth_assertion=False,
                    )

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
    topic_publications: dict[str, set[str]] = defaultdict(set)
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
        if edge.get("relationship_basis") in {"metadata-association", "reviewed-concept-association"}:
            if source.startswith("topic:") and target in records:
                topic_publications[source].add(target)
            elif target.startswith("topic:") and source in records:
                topic_publications[target].add(source)

    for node_id, node in nodes.items():
        node["metrics"] = {
            "weighted_degree": round(degree.get(node_id, 0.0), 6),
            "citation_links": citations.get(node_id, 0),
            "semantic_links": semantic_links.get(node_id, 0),
            "publication_count": len(topic_publications.get(node_id, set())) if node.get("kind") == "topic" else 0,
        }

    node_items = list(nodes.values())
    publication_count = sum(1 for x in node_items if x.get("kind") == "publication")
    topic_count = sum(1 for x in node_items if x.get("kind") == "topic")
    relationship_counts: dict[str, int] = defaultdict(int)
    for edge in edge_items:
        relationship_counts[str(edge.get("relationship_basis") or "unknown")] += 1

    return {
        "schema": CORPUS_KNOWLEDGE_MAP_CONTRACT,
        "scope": "corpus",
        "title": "Publication Corpus Knowledge Landscape",
        "analysis_kind": "publication-corpus-knowledge-landscape",
        "nodes": node_items,
        "edges": edge_items,
        "metrics": {
            "node_count": len(node_items),
            "edge_count": len(edge_items),
            "publication_count": publication_count,
            "topic_count": topic_count,
            "relationship_counts": dict(sorted(relationship_counts.items())),
        },
        "corpus": {
            "source_key": source_key or None,
            "object_type": object_type or (None if selection_mode == "publication-library-manifest" else "post"),
            "selection": selection_mode,
            "requested_manifest_count": len(record_ids or []),
            "eligible_publication_count": total_eligible,
            "analyzed_publication_count": publication_count,
            "truncated": total_eligible > publication_count,
            "max_publications": max_publications,
        },
        "semantic_analysis": semantic_status,
        "views": [
            {"key": "knowledge-landscape", "label": "Knowledge Landscape", "purpose": "Cross-publication topic and publication relationship field"},
            {"key": "topic-graph", "label": "Topic Graph", "purpose": "Measured topic co-occurrence across the publication corpus"},
            {"key": "citation-overlay", "label": "Citation Overlay", "purpose": "Explicit citation structure within the selected corpus"},
            {"key": "semantic-overlay", "label": "Semantic Overlay", "purpose": "Publication similarity from current stored embeddings only"},
            {"key": "relationship-matrix", "label": "Relationship Matrix", "purpose": "Pairwise analytical relationship inspection"},
        ],
        "renderer_profile": {
            "family": "scientific-publication-corpus-landscape",
            "renderer_neutral": True,
            "preferred_runtime": "interactive-svg-webgl-capable",
            "layout": "force-directed-multilayer",
            "node_channels": ["kind", "weighted_degree", "publication_count", "source_type"],
            "edge_channels": ["relationship_basis", "weight", "directed", "evidence_count"],
            "interactions": ["zoom", "pan", "select", "filter", "focus", "inspect-source", "toggle-layer", "drill-to-publication"],
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
            "corpus_source": source_key or "all-public-library-sources",
            "relationship_methods": [
                "explicit-citation",
                "record-metadata-association",
                "human-reviewed-concept-association",
                "publication-topic-cooccurrence",
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
            "corpus_is_live_library_records": True,
            "publication_library_manifest_applied": selection_mode == "publication-library-manifest",
            "non_publication_wordpress_types_excluded": True,
        },
    }
