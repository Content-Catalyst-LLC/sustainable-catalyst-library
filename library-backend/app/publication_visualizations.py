from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
from typing import Any

from psycopg.types.json import Jsonb
from pydantic import BaseModel, Field, field_validator

from .db import get_pool
from .platform_core import CoreOutboxRequest, enqueue_operation

VISUALIZATION_CONTRACT = "sc-library-publication-visualization/1.0"
VISUALIZATION_SPEC_CONTRACT = "sc-library-publication-visualization-spec/1.0"
VISUALIZATION_READINESS_CONTRACT = "sc-library-publication-visualization-readiness/1.0"
CORE_VISUAL_HANDOFF_CONTRACT = "sc-library-core-visual-research-handoff/1.0"

VISUALIZATION_KINDS = {"citation-network", "concept-map", "finding-map", "claim-map"}
REVIEW_STATES = {"draft", "published", "rejected", "superseded"}


class VisualizationBuildRequest(BaseModel):
    record_id: str = Field(min_length=1, max_length=500)
    kinds: list[str] = Field(default_factory=lambda: ["citation-network", "concept-map", "finding-map", "claim-map"])
    replace_drafts: bool = True
    created_by: str = Field(default="library-visualization-builder", max_length=200)

    @field_validator("kinds")
    @classmethod
    def validate_kinds(cls, values: list[str]) -> list[str]:
        normalized: list[str] = []
        for value in values:
            kind = str(value or "").strip().lower()
            if kind not in VISUALIZATION_KINDS:
                raise ValueError(f"unsupported visualization kind: {kind}")
            if kind not in normalized:
                normalized.append(kind)
        if not normalized:
            raise ValueError("at least one visualization kind is required")
        return normalized


class VisualizationReviewRequest(BaseModel):
    review_state: str = Field(pattern="^(published|rejected)$")
    reviewer: str = Field(min_length=1, max_length=300)
    review_note: str = Field(default="", max_length=4000)


class VisualizationCoreHandoffRequest(BaseModel):
    visualization_id: int = Field(gt=0)
    core_project_id: str = Field(min_length=1, max_length=500)
    created_by: str = Field(default="library-reviewer", max_length=200)
    core_metadata: dict[str, Any] = Field(default_factory=dict)


def _stable_hash(value: Any) -> str:
    payload = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def _record(cur: Any, record_id: str) -> dict[str, Any]:
    cur.execute(
        """
        SELECT record_id,title,canonical_url,object_type,content_hash,authors,topics,tags,identifiers,metadata,
               visibility,publication_status,published_at,indexed_at
          FROM library_records
         WHERE record_id=%s
        """,
        (record_id,),
    )
    row = cur.fetchone()
    if not row:
        raise ValueError("record_id does not exist")
    return dict(row)


def _node(record_id: str, kind: str, label: str, **extra: Any) -> dict[str, Any]:
    out = {"id": record_id, "kind": kind, "label": label}
    out.update({k: v for k, v in extra.items() if v is not None})
    return out


def _base_spec(record: dict[str, Any], kind: str, title: str, description: str, nodes: list[dict[str, Any]], edges: list[dict[str, Any]]) -> dict[str, Any]:
    renderer = "network"
    layout = "force-directed"
    if kind in {"finding-map", "claim-map"}:
        layout = "radial"
    return {
        "schema": VISUALIZATION_SPEC_CONTRACT,
        "visualization_kind": kind,
        "record_id": record["record_id"],
        "title": title,
        "description": description,
        "renderer": renderer,
        "layout_hint": layout,
        "renderer_neutral": True,
        "nodes": nodes,
        "edges": edges,
        "provenance": {
            "source_product": "knowledge-library",
            "library_record_id": record["record_id"],
            "source_content_hash": record["content_hash"],
            "generated_at": _now_iso(),
            "generation_method": "deterministic-library-graph-composition",
            "governed_visual_reasoning_authority": "platform-core",
        },
        "boundaries": {
            "inferred_edges": False,
            "inferred_truth": False,
            "automatic_claim_promotion": False,
            "automatic_finding_promotion": False,
            "layout_execution_by_library": False,
            "renderer_execution_by_core": False,
            "library_visualization_is_governed_core_object": False,
        },
    }


def _citation_spec(cur: Any, record: dict[str, Any]) -> dict[str, Any]:
    rid = str(record["record_id"])
    nodes: dict[str, dict[str, Any]] = {
        rid: _node(rid, "publication", str(record["title"]), canonical_url=record.get("canonical_url"), root=True)
    }
    edges: list[dict[str, Any]] = []
    cur.execute(
        """
        SELECT c.citation_id,c.citing_record_id,c.cited_record_id,c.identifier_type,c.identifier_value,
               c.raw_citation,c.relation_type,c.resolution_status,
               citing.title AS citing_title,cited.title AS cited_title
          FROM library_citations c
          LEFT JOIN library_records citing ON citing.record_id=c.citing_record_id
          LEFT JOIN library_records cited ON cited.record_id=c.cited_record_id
         WHERE c.citing_record_id=%s OR c.cited_record_id=%s
         ORDER BY c.citation_id ASC
         LIMIT 500
        """,
        (rid, rid),
    )
    for row in cur.fetchall():
        item = dict(row)
        source = str(item["citing_record_id"])
        if item.get("cited_record_id"):
            target = str(item["cited_record_id"])
            target_label = str(item.get("cited_title") or target)
            nodes.setdefault(target, _node(target, "publication", target_label))
        else:
            unresolved = str(item.get("identifier_value") or item.get("raw_citation") or f"citation:{item['citation_id']}")
            target = f"unresolved:{item['citation_id']}"
            nodes.setdefault(
                target,
                _node(
                    target,
                    "unresolved-reference",
                    unresolved[:300],
                    identifier_type=item.get("identifier_type"),
                    resolution_status=item.get("resolution_status"),
                ),
            )
        source_label = str(item.get("citing_title") or source)
        nodes.setdefault(source, _node(source, "publication", source_label))
        edges.append({
            "id": f"citation:{item['citation_id']}",
            "source": source,
            "target": target,
            "kind": str(item.get("relation_type") or "cites"),
            "directed": True,
            "declared": True,
        })
    return _base_spec(
        record,
        "citation-network",
        f"Citation Network — {record['title']}",
        "Declared and imported citation relationships around this publication. Unresolved references remain explicit and are not guessed from title similarity.",
        list(nodes.values()),
        edges,
    )


def _candidate_spec(cur: Any, record: dict[str, Any], candidate_type: str, kind: str, label: str, relation: str) -> dict[str, Any]:
    rid = str(record["record_id"])
    nodes: list[dict[str, Any]] = [
        _node(rid, "publication", str(record["title"]), canonical_url=record.get("canonical_url"), root=True)
    ]
    edges: list[dict[str, Any]] = []
    where = "candidate_type=%s AND record_id=%s AND review_state='accepted'"
    params: list[Any] = [candidate_type, rid]
    if candidate_type == "entity":
        where += " AND entity_type='concept'"
    cur.execute(
        f"""
        SELECT candidate_id,candidate_key,candidate_type,candidate_text,entity_type,confidence,
               source_locator,source_chunk_ordinal,char_start,char_end,source_content_hash,review_state,reviewer
          FROM library_research_candidates
         WHERE {where}
         ORDER BY confidence DESC,candidate_id ASC
         LIMIT 500
        """,
        tuple(params),
    )
    for row in cur.fetchall():
        item = dict(row)
        node_id = f"candidate:{item['candidate_id']}"
        nodes.append(_node(
            node_id,
            candidate_type,
            str(item["candidate_text"])[:500],
            entity_type=item.get("entity_type"),
            confidence=float(item.get("confidence") or 0.0),
            source_locator=item.get("source_locator"),
            source_chunk_ordinal=item.get("source_chunk_ordinal"),
            char_start=item.get("char_start"),
            char_end=item.get("char_end"),
            source_content_hash=item.get("source_content_hash"),
            human_review_state=item.get("review_state"),
            reviewed_by=item.get("reviewer"),
        ))
        edges.append({
            "id": f"edge:{kind}:{item['candidate_id']}",
            "source": rid,
            "target": node_id,
            "kind": relation,
            "directed": True,
            "declared": True,
            "source_locator": item.get("source_locator"),
        })
    return _base_spec(
        record,
        kind,
        f"{label} — {record['title']}",
        f"Source-anchored, human-accepted {candidate_type} candidates associated with this publication.",
        nodes,
        edges,
    )


def build_spec(cur: Any, record: dict[str, Any], kind: str) -> dict[str, Any]:
    if kind == "citation-network":
        return _citation_spec(cur, record)
    if kind == "concept-map":
        return _candidate_spec(cur, record, "entity", kind, "Concept Map", "mentions-concept")
    if kind == "finding-map":
        return _candidate_spec(cur, record, "finding", kind, "Finding Map", "reports-finding")
    if kind == "claim-map":
        return _candidate_spec(cur, record, "claim", kind, "Claim Map", "states-claim")
    raise ValueError("unsupported visualization kind")


def _visualization_key(record_id: str, kind: str, source_content_hash: str, spec_hash: str) -> str:
    return _stable_hash({
        "record_id": record_id,
        "kind": kind,
        "source_content_hash": source_content_hash,
        "spec_hash": spec_hash,
        "contract": VISUALIZATION_SPEC_CONTRACT,
    })


def build_publication_visualizations(request: VisualizationBuildRequest) -> dict[str, Any]:
    pool = get_pool()
    built: list[dict[str, Any]] = []
    with pool.connection() as conn, conn.cursor() as cur:
        record = _record(cur, request.record_id)
        if record.get("visibility") != "public" or record.get("publication_status") != "published":
            raise ValueError("publication visualizations require a public, published Library record")

        # Any visualization tied to older publication content is explicitly superseded.
        cur.execute(
            """
            UPDATE library_publication_visualizations
               SET review_state='superseded',updated_at=now()
             WHERE record_id=%s AND source_content_hash IS DISTINCT FROM %s
               AND review_state IN ('draft','published')
            """,
            (request.record_id, record["content_hash"]),
        )
        if request.replace_drafts:
            cur.execute(
                """
                UPDATE library_publication_visualizations
                   SET review_state='superseded',updated_at=now()
                 WHERE record_id=%s AND source_content_hash=%s
                   AND review_state='draft' AND visualization_kind=ANY(%s)
                """,
                (request.record_id, record["content_hash"], request.kinds),
            )

        for kind in request.kinds:
            spec = build_spec(cur, record, kind)
            spec_hash = _stable_hash(spec)
            key = _visualization_key(request.record_id, kind, str(record["content_hash"]), spec_hash)
            cur.execute(
                """
                INSERT INTO library_publication_visualizations(
                    visualization_key,record_id,visualization_kind,title,description,source_content_hash,
                    specification,spec_hash,review_state,created_by,metadata
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'draft',%s,%s)
                ON CONFLICT (visualization_key) DO UPDATE SET
                    title=EXCLUDED.title,description=EXCLUDED.description,specification=EXCLUDED.specification,
                    spec_hash=EXCLUDED.spec_hash,metadata=library_publication_visualizations.metadata || EXCLUDED.metadata,
                    updated_at=now()
                RETURNING *
                """,
                (
                    key, request.record_id, kind, spec["title"], spec["description"], record["content_hash"],
                    Jsonb(spec), spec_hash, request.created_by,
                    Jsonb({"contract": VISUALIZATION_CONTRACT, "deterministic": True, "machine_inferred_edges": False}),
                ),
            )
            built.append(dict(cur.fetchone()))
        conn.commit()
    return {
        "schema": VISUALIZATION_CONTRACT,
        "record_id": request.record_id,
        "items": built,
        "count": len(built),
        "review_required_before_publication": True,
        "core_visual_reasoning_authority": "platform-core",
    }


def list_publication_visualizations(record_id: str, *, published_only: bool = True, limit: int = 50) -> dict[str, Any]:
    pool = get_pool()
    clauses = ["v.record_id=%s"]
    params: list[Any] = [record_id]
    if published_only:
        clauses.append("v.review_state='published'")
    params.append(max(1, min(200, int(limit))))
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT v.visualization_id,v.visualization_key,v.record_id,v.visualization_kind,v.title,v.description,
                   v.source_content_hash,v.specification,v.spec_hash,v.review_state,v.reviewer,v.review_note,
                   v.reviewed_at,v.created_by,v.core_operation,v.core_outbox_event_id,
                   o.status AS core_outbox_status,o.core_object_id,o.last_http_status,o.last_error,
                   v.metadata,v.created_at,v.updated_at
              FROM library_publication_visualizations v
              LEFT JOIN library_core_sync_outbox o ON o.event_id=v.core_outbox_event_id
             WHERE {' AND '.join(clauses)}
             ORDER BY v.visualization_kind,v.updated_at DESC
             LIMIT %s
            """,
            tuple(params),
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": VISUALIZATION_CONTRACT, "record_id": record_id, "items": items, "count": len(items)}


def get_publication_visualization(visualization_id: int, *, published_only: bool = True) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT v.*,o.status AS core_outbox_status,o.core_object_id,o.last_http_status,o.last_error
              FROM library_publication_visualizations v
              LEFT JOIN library_core_sync_outbox o ON o.event_id=v.core_outbox_event_id
             WHERE v.visualization_id=%s
            """,
            (visualization_id,),
        )
        row = cur.fetchone()
    if not row:
        raise ValueError("visualization_id does not exist")
    item = dict(row)
    if published_only and item.get("review_state") != "published":
        raise ValueError("published visualization does not exist")
    return {"schema": VISUALIZATION_CONTRACT, "visualization": item}


def review_publication_visualization(visualization_id: int, request: VisualizationReviewRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            UPDATE library_publication_visualizations
               SET review_state=%s,reviewer=%s,review_note=%s,reviewed_at=now(),updated_at=now()
             WHERE visualization_id=%s AND review_state <> 'superseded'
             RETURNING *
            """,
            (request.review_state, request.reviewer, request.review_note, visualization_id),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("visualization_id does not exist or is superseded")
        result = dict(row)
        conn.commit()
    return {"schema": VISUALIZATION_CONTRACT, "visualization": result}


def _core_visual_payload(visualization: dict[str, Any], request: VisualizationCoreHandoffRequest) -> dict[str, Any]:
    if visualization.get("review_state") != "published":
        raise ValueError("visualization must be human-reviewed and published before Core handoff")
    spec = dict(visualization.get("specification") or {})
    return {
        "data": {
            "project_entity_id": request.core_project_id,
            "object_key": f"library-publication-visual:{visualization['visualization_key'][:40]}",
            "name": str(visualization["title"]),
            "description": str(visualization.get("description") or ""),
            "object_kind": "research-composite",
            "lifecycle_state": "published",
            "visibility": "public",
            "source_products": ["knowledge-library"],
            "provenance": {
                "source_product": "knowledge-library",
                "library_record_id": visualization["record_id"],
                "library_visualization_id": visualization["visualization_id"],
                "source_content_hash": visualization.get("source_content_hash"),
                "spec_hash": visualization.get("spec_hash"),
                "visualization_contract": VISUALIZATION_SPEC_CONTRACT,
                "human_review_state": visualization.get("review_state"),
                "reviewed_by": visualization.get("reviewer"),
            },
            "metadata": {
                "visualization_kind": visualization["visualization_kind"],
                "renderer_neutral": bool(spec.get("renderer_neutral", True)),
                "library_specification": spec,
                "automatic_truth_promotion": False,
                **dict(request.core_metadata or {}),
            },
            "created_by": request.created_by,
        }
    }


def enqueue_core_visualization(request: VisualizationCoreHandoffRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_publication_visualizations WHERE visualization_id=%s", (request.visualization_id,))
        row = cur.fetchone()
        if not row:
            raise ValueError("visualization_id does not exist")
        visualization = dict(row)
    payload = _core_visual_payload(visualization, request)
    outbox = enqueue_operation(CoreOutboxRequest(
        library_record_id=str(visualization["record_id"]),
        operation="visual-research-object.create",
        payload=payload,
        metadata={
            "contract": CORE_VISUAL_HANDOFF_CONTRACT,
            "visualization_id": visualization["visualization_id"],
            "visualization_kind": visualization["visualization_kind"],
            "explicit_governed_promotion": True,
        },
    ))
    event = dict(outbox["event"])
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            UPDATE library_publication_visualizations
               SET core_operation='visual-research-object.create',core_outbox_event_id=%s,updated_at=now()
             WHERE visualization_id=%s
            """,
            (event["event_id"], request.visualization_id),
        )
        conn.commit()
    return {
        "schema": CORE_VISUAL_HANDOFF_CONTRACT,
        "visualization_id": request.visualization_id,
        "record_id": visualization["record_id"],
        "core_project_id": request.core_project_id,
        "operation": "visual-research-object.create",
        "outbox_event": event,
        "queued_for_core": True,
        "governed_core_visual_object_created": False,
    }


def visualization_readiness() -> dict[str, Any]:
    pool = get_pool()
    counts: dict[str, int] = {}
    reviews: dict[str, int] = {}
    storage_ready = False
    storage_error: str | None = None
    try:
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT visualization_kind,count(*) AS count FROM library_publication_visualizations GROUP BY visualization_kind ORDER BY visualization_kind")
            counts = {str(row["visualization_kind"]): int(row["count"]) for row in cur.fetchall()}
            cur.execute("SELECT review_state,count(*) AS count FROM library_publication_visualizations GROUP BY review_state ORDER BY review_state")
            reviews = {str(row["review_state"]): int(row["count"]) for row in cur.fetchall()}
        storage_ready = True
    except Exception as exc:
        storage_error = exc.__class__.__name__
    return {
        "schema": VISUALIZATION_READINESS_CONTRACT,
        "publication_visualizations": True,
        "storage_ready": storage_ready,
        "storage_error": storage_error,
        "visualization_kinds": sorted(VISUALIZATION_KINDS),
        "renderer_neutral_specs": True,
        "source_content_hash_binding": True,
        "human_review_required": True,
        "research_library_delivery": True,
        "visualization_counts": counts,
        "review_counts": reviews,
        "platform_core": {
            "governed_visual_research_handoff": "explicit-after-human-review",
            "operation": "visual-research-object.create",
            "authority": "governed-visual-reasoning-and-provenance",
        },
        "boundaries": {
            "automatic_truth_promotion": False,
            "automatic_claim_promotion": False,
            "automatic_finding_promotion": False,
            "machine_inferred_graph_edges": False,
            "library_visualizations_are_governed_core_objects": False,
            "renderer_execution_by_core": False,
        },
    }
