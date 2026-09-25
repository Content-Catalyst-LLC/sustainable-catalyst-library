from __future__ import annotations

from collections import defaultdict
from typing import Any

from .db import get_pool

TRACE_CONTRACT = "sc-library-visual-evidence-trace/1.0"


def _selected_ids(visual_state: dict[str, Any] | None) -> list[str]:
    state = visual_state or {}
    q = state.get("visual_query") if isinstance(state.get("visual_query"), dict) else state
    ids = q.get("selected_node_ids") or []
    if isinstance(ids, str):
        ids = [ids]
    return list(dict.fromkeys(str(x).strip() for x in ids if str(x).strip()))[:100]


def _edge_basis(edge: dict[str, Any]) -> str:
    return str(edge.get("basis") or edge.get("relationship_basis") or edge.get("type") or "relationship")


def build_visual_evidence_trace(corpus: dict[str, Any], visual_state: dict[str, Any] | None) -> dict[str, Any]:
    """Explain a visual selection using only traceable Library evidence.

    This endpoint does not infer truth, causality, or new claims. It explains why
    selected visual objects exist by tracing them to publication metadata,
    explicit citations, reviewed candidates, source chunks, and measured graph
    relationships already present in the Library corpus.
    """
    selected = _selected_ids(visual_state)
    nodes = {str(x.get("id")): x for x in (corpus.get("nodes") or []) if isinstance(x, dict) and x.get("id")}
    edges = [x for x in (corpus.get("edges") or []) if isinstance(x, dict)]

    selected_nodes = [nodes[x] for x in selected if x in nodes]
    publication_ids = [str(n.get("record_id") or n.get("id")) for n in selected_nodes if n.get("kind") == "publication"]
    topic_nodes = [n for n in selected_nodes if n.get("kind") == "topic"]
    topic_labels = [str(n.get("label") or "").strip() for n in topic_nodes if str(n.get("label") or "").strip()]

    # Pull directly connected publications for selected topics so topic traces can
    # cite actual corpus records even when the user did not select a publication.
    connected_publications: set[str] = set(publication_ids)
    selected_set = set(selected)
    relationship_items: list[dict[str, Any]] = []
    for edge in edges:
        source = str(edge.get("source") or "")
        target = str(edge.get("target") or "")
        if source not in selected_set and target not in selected_set:
            continue
        basis = _edge_basis(edge)
        relationship_items.append({
            "source": source,
            "target": target,
            "basis": basis,
            "weight": edge.get("weight"),
            "directed": bool(edge.get("directed", False)),
            "provenance": edge.get("provenance") or {},
        })
        other = target if source in selected_set else source
        other_node = nodes.get(other) or {}
        if other_node.get("kind") == "publication":
            connected_publications.add(str(other_node.get("record_id") or other))

    record_ids = list(connected_publications)[:100]
    records: list[dict[str, Any]] = []
    chunks: list[dict[str, Any]] = []
    citations: list[dict[str, Any]] = []
    reviewed: list[dict[str, Any]] = []

    if record_ids:
        pool = get_pool()
        with pool.connection() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT record_id,title,canonical_url,object_type,content_hash,published_at,authors,topics,tags
                      FROM library_records
                     WHERE record_id=ANY(%s) AND visibility='public' AND publication_status='published'
                     ORDER BY published_at DESC NULLS LAST,record_id
                    """,
                    (record_ids,),
                )
                records = [dict(r) for r in cur.fetchall()]

                cur.execute(
                    """
                    SELECT record_id,ordinal,heading,text,metadata
                      FROM library_record_chunks
                     WHERE record_id=ANY(%s)
                     ORDER BY record_id,ordinal
                     LIMIT 300
                    """,
                    (record_ids,),
                )
                raw_chunks = [dict(r) for r in cur.fetchall()]
                labels_lower = [x.lower() for x in topic_labels]
                per_record = defaultdict(int)
                for row in raw_chunks:
                    text = str(row.get("text") or "")
                    heading = str(row.get("heading") or "")
                    hay = f"{heading}\n{text}".lower()
                    topic_matches = [label for label, lower in zip(topic_labels, labels_lower) if lower and lower in hay]
                    if labels_lower and not topic_matches:
                        continue
                    rid = str(row.get("record_id"))
                    if per_record[rid] >= 4:
                        continue
                    per_record[rid] += 1
                    chunks.append({
                        "record_id": rid,
                        "ordinal": int(row.get("ordinal") or 0),
                        "heading": heading,
                        "excerpt": text[:900],
                        "topic_matches": topic_matches,
                        "source_locator": f"chunk:{int(row.get('ordinal') or 0)}",
                    })

                cur.execute(
                    """
                    SELECT citation_key,citing_record_id,cited_record_id,identifier_type,identifier_value,
                           raw_citation,relation_type,extraction_method,confidence,locator,source_chunk_ordinal,
                           resolution_status,metadata
                      FROM library_citations
                     WHERE citing_record_id=ANY(%s) OR cited_record_id=ANY(%s)
                     ORDER BY updated_at DESC
                     LIMIT 200
                    """,
                    (record_ids, record_ids),
                )
                citations = [dict(r) for r in cur.fetchall()]

                cur.execute(
                    """
                    SELECT candidate_key,record_id,candidate_type,candidate_text,entity_type,extraction_method,
                           confidence,source_locator,source_chunk_ordinal,char_start,char_end,source_content_hash,
                           review_state,reviewer,review_note,reviewed_at,metadata
                      FROM library_research_candidates
                     WHERE record_id=ANY(%s) AND review_state='accepted'
                     ORDER BY reviewed_at DESC NULLS LAST,confidence DESC
                     LIMIT 200
                    """,
                    (record_ids,),
                )
                reviewed = [dict(r) for r in cur.fetchall()]

    record_lookup = {str(r.get("record_id")): r for r in records}
    source_items = []
    for rid in record_ids:
        r = record_lookup.get(rid)
        if not r:
            continue
        source_items.append({
            "record_id": rid,
            "title": r.get("title"),
            "canonical_url": r.get("canonical_url"),
            "content_hash": r.get("content_hash"),
            "published_at": r.get("published_at"),
            "topics": r.get("topics") or [],
            "tags": r.get("tags") or [],
        })

    return {
        "schema": TRACE_CONTRACT,
        "scope": "visual-selection",
        "selected_node_ids": selected,
        "selected_nodes": selected_nodes,
        "relationship_explanations": relationship_items[:250],
        "source_records": source_items,
        "source_passages": chunks[:100],
        "explicit_citations": citations[:100],
        "reviewed_research_candidates": reviewed[:100],
        "metrics": {
            "selected_node_count": len(selected_nodes),
            "relationship_count": len(relationship_items),
            "source_record_count": len(source_items),
            "source_passage_count": len(chunks[:100]),
            "citation_count": len(citations[:100]),
            "reviewed_candidate_count": len(reviewed[:100]),
        },
        "interpretation": {
            "trace_explains_visual_presence": True,
            "trace_creates_new_claims": False,
            "relationship_implies_causality": False,
            "semantic_similarity_is_truth": False,
            "source_passages_are_excerpted": True,
            "accepted_candidates_remain_source_bound": True,
        },
    }
