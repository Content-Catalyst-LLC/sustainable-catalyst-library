from __future__ import annotations

from collections import defaultdict, deque
from datetime import datetime, timezone
import hashlib
import re
from typing import Any

from psycopg.types.json import Jsonb
from pydantic import BaseModel, Field, field_validator, model_validator

from .db import get_pool
from .platform_core import CoreOutboxRequest, enqueue_operation, list_bindings

CITATION_CONTRACT = "sc-library-citation-graph/1.0"
CITATION_READINESS_CONTRACT = "sc-library-citation-graph-readiness/1.0"
CORE_CITATION_HANDOFF_CONTRACT = "sc-library-core-scholarly-citation-handoff/1.0"

IDENTIFIER_TYPES = {"doi", "pmid", "pmcid", "isbn", "issn", "arxiv", "url", "other"}
RELATION_TYPES = {"cites", "references", "supports", "discusses", "reuses", "extends", "corrects", "retracts"}
EXTRACTION_METHODS = {"manual", "import", "metadata", "parser", "connector"}


def normalize_identifier(identifier_type: str, value: str) -> str:
    kind = str(identifier_type or "other").strip().lower()
    raw = " ".join(str(value or "").split()).strip()
    if kind == "doi":
        raw = re.sub(r"^https?://(?:dx\.)?doi\.org/", "", raw, flags=re.I)
        raw = re.sub(r"^doi:\s*", "", raw, flags=re.I)
        return raw.lower()
    if kind in {"pmid", "pmcid"}:
        raw = re.sub(rf"^{kind}:\s*", "", raw, flags=re.I)
        return raw.upper() if kind == "pmcid" else raw
    if kind in {"isbn", "issn"}:
        return re.sub(r"[^0-9Xx]", "", raw).upper()
    return raw


def stable_citation_key(*parts: str) -> str:
    canonical = "\x1f".join(str(part or "").strip() for part in parts)
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


class CitationCreateRequest(BaseModel):
    citing_record_id: str = Field(min_length=1, max_length=500)
    cited_record_id: str | None = Field(default=None, max_length=500)
    identifier_type: str = Field(default="other", max_length=30)
    identifier_value: str = Field(default="", max_length=1000)
    raw_citation: str = Field(default="", max_length=10000)
    relation_type: str = Field(default="cites", max_length=40)
    extraction_method: str = Field(default="manual", max_length=40)
    confidence: float = Field(default=1.0, ge=0.0, le=1.0)
    locator: str = Field(default="", max_length=500)
    source_chunk_ordinal: int | None = Field(default=None, ge=0)
    metadata: dict[str, Any] = Field(default_factory=dict)

    @field_validator("identifier_type")
    @classmethod
    def validate_identifier_type(cls, value: str) -> str:
        value = value.strip().lower()
        if value not in IDENTIFIER_TYPES:
            raise ValueError("unsupported identifier_type")
        return value

    @field_validator("relation_type")
    @classmethod
    def validate_relation_type(cls, value: str) -> str:
        value = value.strip().lower()
        if value not in RELATION_TYPES:
            raise ValueError("unsupported relation_type")
        return value

    @field_validator("extraction_method")
    @classmethod
    def validate_extraction_method(cls, value: str) -> str:
        value = value.strip().lower()
        if value not in EXTRACTION_METHODS:
            raise ValueError("unsupported extraction_method")
        return value

    @model_validator(mode="after")
    def require_target(self) -> "CitationCreateRequest":
        if not (self.cited_record_id or self.identifier_value.strip() or self.raw_citation.strip()):
            raise ValueError("a cited record, persistent identifier, or raw citation is required")
        self.identifier_value = normalize_identifier(self.identifier_type, self.identifier_value)
        return self


class CoreScholarlyCitationHandoffRequest(BaseModel):
    citation_id: int = Field(gt=0)
    core_package_id: str = Field(min_length=1, max_length=500)
    project_ref: str = Field(min_length=1, max_length=500)
    cited_object_ref: str = Field(default="", max_length=1000)
    citation_format: str = Field(default="csl-json", max_length=50)
    visibility: str = Field(default="private", pattern="^(private|shared|public|internal)$")


def _record_exists(cur: Any, record_id: str) -> bool:
    cur.execute("SELECT 1 FROM library_records WHERE record_id=%s", (record_id,))
    return cur.fetchone() is not None


def _resolve_identifier(cur: Any, identifier_type: str, identifier_value: str) -> str | None:
    if not identifier_value:
        return None
    # Identifiers remain Library-owned metadata. Resolution is exact and declared;
    # it never guesses a record from title similarity or model output.
    cur.execute(
        """
        SELECT record_id
          FROM library_records
         WHERE visibility='public'
           AND publication_status='published'
           AND lower(coalesce(identifiers->>%s,''))=lower(%s)
         ORDER BY indexed_at DESC
         LIMIT 1
        """,
        (identifier_type, identifier_value),
    )
    row = cur.fetchone()
    return str(row["record_id"]) if row else None


def upsert_citation(request: CitationCreateRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        if not _record_exists(cur, request.citing_record_id):
            raise ValueError("citing_record_id does not exist")
        cited_record_id = request.cited_record_id
        if cited_record_id and not _record_exists(cur, cited_record_id):
            raise ValueError("cited_record_id does not exist")
        if not cited_record_id and request.identifier_value:
            cited_record_id = _resolve_identifier(cur, request.identifier_type, request.identifier_value)

        resolution_status = "resolved" if cited_record_id else "unresolved"
        citation_key = stable_citation_key(
            request.citing_record_id,
            cited_record_id or "",
            request.identifier_type,
            request.identifier_value,
            request.raw_citation,
            request.relation_type,
            request.locator,
        )
        cur.execute(
            """
            INSERT INTO library_citations(
                citation_key,citing_record_id,cited_record_id,identifier_type,identifier_value,
                raw_citation,relation_type,extraction_method,confidence,locator,
                source_chunk_ordinal,resolution_status,metadata
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT (citation_key) DO UPDATE SET
                cited_record_id=COALESCE(EXCLUDED.cited_record_id,library_citations.cited_record_id),
                confidence=GREATEST(library_citations.confidence,EXCLUDED.confidence),
                resolution_status=CASE WHEN COALESCE(EXCLUDED.cited_record_id,library_citations.cited_record_id) IS NOT NULL THEN 'resolved' ELSE EXCLUDED.resolution_status END,
                metadata=library_citations.metadata || EXCLUDED.metadata,
                updated_at=now()
            RETURNING *
            """,
            (
                citation_key, request.citing_record_id, cited_record_id,
                request.identifier_type, request.identifier_value, request.raw_citation,
                request.relation_type, request.extraction_method, request.confidence,
                request.locator, request.source_chunk_ordinal, resolution_status, Jsonb(request.metadata),
            ),
        )
        row = dict(cur.fetchone())
        conn.commit()
    return {"schema": CITATION_CONTRACT, "citation": row}


def import_record_metadata_citations(record_id: str) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT metadata FROM library_records WHERE record_id=%s", (record_id,))
        row = cur.fetchone()
    if not row:
        raise ValueError("record_id does not exist")
    metadata = dict(row.get("metadata") or {})
    declared = metadata.get("citations") or metadata.get("references") or []
    if not isinstance(declared, list):
        return {"schema": CITATION_CONTRACT, "record_id": record_id, "imported": 0, "skipped": 1, "reason": "metadata-citations-not-list"}

    imported = 0
    errors: list[dict[str, Any]] = []
    for ordinal, item in enumerate(declared):
        try:
            if isinstance(item, str):
                payload = CitationCreateRequest(citing_record_id=record_id, raw_citation=item, extraction_method="metadata", confidence=1.0)
            elif isinstance(item, dict):
                payload = CitationCreateRequest(
                    citing_record_id=record_id,
                    cited_record_id=item.get("record_id") or item.get("cited_record_id"),
                    identifier_type=item.get("identifier_type") or ("doi" if item.get("doi") else "other"),
                    identifier_value=item.get("identifier_value") or item.get("doi") or "",
                    raw_citation=item.get("citation") or item.get("raw_citation") or item.get("title") or "",
                    relation_type=item.get("relation_type") or "cites",
                    extraction_method="metadata",
                    confidence=float(item.get("confidence", 1.0)),
                    locator=item.get("locator") or "",
                    metadata={"metadata_ordinal": ordinal, **dict(item.get("metadata") or {})},
                )
            else:
                raise ValueError("unsupported citation metadata item")
            upsert_citation(payload)
            imported += 1
        except Exception as exc:
            errors.append({"ordinal": ordinal, "error": exc.__class__.__name__})
    return {"schema": CITATION_CONTRACT, "record_id": record_id, "imported": imported, "errors": errors, "error_count": len(errors)}


def list_citations(record_id: str, direction: str = "both", limit: int = 100) -> dict[str, Any]:
    direction = direction if direction in {"outgoing", "incoming", "both"} else "both"
    predicates = {
        "outgoing": "c.citing_record_id=%s",
        "incoming": "c.cited_record_id=%s",
        "both": "(c.citing_record_id=%s OR c.cited_record_id=%s)",
    }
    params: list[Any] = [record_id] if direction != "both" else [record_id, record_id]
    params.append(max(1, min(500, int(limit))))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT c.*,
                   src.title AS citing_title,src.canonical_url AS citing_url,
                   dst.title AS cited_title,dst.canonical_url AS cited_url
              FROM library_citations c
              JOIN library_records src ON src.record_id=c.citing_record_id
              LEFT JOIN library_records dst ON dst.record_id=c.cited_record_id
             WHERE {predicates[direction]}
             ORDER BY c.created_at DESC,c.citation_id DESC
             LIMIT %s
            """,
            params,
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": CITATION_CONTRACT, "record_id": record_id, "direction": direction, "items": items, "count": len(items)}


def _binding_map(record_ids: list[str]) -> dict[str, list[dict[str, Any]]]:
    result: dict[str, list[dict[str, Any]]] = {}
    for record_id in record_ids:
        rows = list_bindings(record_id, limit=100).get("items", [])
        synced = [dict(row) for row in rows if row.get("sync_status") == "synced"]
        if synced:
            result[record_id] = synced
    return result


def citation_graph(record_id: str, depth: int = 2, limit: int = 250, include_core: bool = True) -> dict[str, Any]:
    depth = max(1, min(4, int(depth)))
    limit = max(1, min(1000, int(limit)))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        if not _record_exists(cur, record_id):
            raise ValueError("record_id does not exist")
        # Pull a bounded citation set and walk it in Python. This avoids an
        # unbounded recursive SQL graph traversal against public API traffic.
        cur.execute(
            """
            SELECT citation_id,citation_key,citing_record_id,cited_record_id,
                   identifier_type,identifier_value,raw_citation,relation_type,
                   extraction_method,confidence,locator,resolution_status,metadata,
                   created_at,updated_at
              FROM library_citations
             WHERE resolution_status IN ('resolved','unresolved')
             ORDER BY updated_at DESC,citation_id DESC
             LIMIT %s
            """,
            (max(limit * 8, 500),),
        )
        all_edges = [dict(row) for row in cur.fetchall()]
        adjacency: dict[str, list[dict[str, Any]]] = defaultdict(list)
        for edge in all_edges:
            adjacency[str(edge["citing_record_id"])].append(edge)
            if edge.get("cited_record_id"):
                adjacency[str(edge["cited_record_id"])].append(edge)

        seen_nodes = {record_id}
        selected_edges: dict[int, dict[str, Any]] = {}
        queue: deque[tuple[str, int]] = deque([(record_id, 0)])
        while queue and len(selected_edges) < limit:
            node_id, level = queue.popleft()
            if level >= depth:
                continue
            for edge in adjacency.get(node_id, []):
                selected_edges[int(edge["citation_id"])] = edge
                for candidate in (edge.get("citing_record_id"), edge.get("cited_record_id")):
                    if candidate and str(candidate) not in seen_nodes:
                        seen_nodes.add(str(candidate))
                        queue.append((str(candidate), level + 1))
                if len(selected_edges) >= limit:
                    break

        resolved_ids = sorted(seen_nodes)
        cur.execute(
            """
            SELECT record_id,object_type,title,canonical_url,source_key,published_at,
                   identifiers,authors,topics,content_hash
              FROM library_records
             WHERE record_id=ANY(%s)
            """,
            (resolved_ids,),
        )
        nodes = [dict(row) for row in cur.fetchall()]

    bindings = _binding_map(resolved_ids) if include_core else {}
    for node in nodes:
        linked = bindings.get(str(node["record_id"]), [])
        node["platform_core"] = {
            "bound": bool(linked),
            "objects": linked,
            "binding_count": len(linked),
            "authority": "platform-core" if linked else None,
        }
    unresolved = [edge for edge in selected_edges.values() if not edge.get("cited_record_id")]
    return {
        "schema": CITATION_CONTRACT,
        "root_record_id": record_id,
        "depth": depth,
        "nodes": nodes,
        "edges": list(selected_edges.values()),
        "node_count": len(nodes),
        "edge_count": len(selected_edges),
        "unresolved_reference_count": len(unresolved),
        "platform_core_enrichment": include_core,
        "lineage_policy": {
            "library_citations_are_declared_or_imported": True,
            "unresolved_references_are_preserved": True,
            "title_similarity_resolution": False,
            "llm_inferred_citations": False,
            "platform_core_owns_governed_lineage": True,
        },
    }


def citation_readiness() -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT count(*) AS count FROM library_citations")
        total = int(cur.fetchone()["count"])
        cur.execute("SELECT resolution_status,count(*) AS count FROM library_citations GROUP BY resolution_status ORDER BY resolution_status")
        states = {str(row["resolution_status"]): int(row["count"]) for row in cur.fetchall()}
    return {
        "schema": CITATION_READINESS_CONTRACT,
        "citation_graph": True,
        "exact_identifier_resolution": True,
        "metadata_citation_import": True,
        "unresolved_reference_preservation": True,
        "core_aware_nodes": True,
        "platform_core_scholarly_citation_handoff": True,
        "platform_core_research_lineage_dependency": True,
        "automatic_claim_promotion": False,
        "automatic_citation_inference": False,
        "citation_count": total,
        "resolution_counts": states,
    }


def enqueue_core_scholarly_citation(request: CoreScholarlyCitationHandoffRequest) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_citations WHERE citation_id=%s", (request.citation_id,))
        citation = cur.fetchone()
        if not citation:
            raise ValueError("citation_id does not exist")
        citation = dict(citation)

    cited_object_ref = request.cited_object_ref.strip()
    if not cited_object_ref and citation.get("cited_record_id"):
        bound = list_bindings(str(citation["cited_record_id"]), limit=20).get("items", [])
        bound = [row for row in bound if row.get("sync_status") == "synced"]
        if bound:
            cited_object_ref = str(bound[0].get("core_object_id") or "")
    if not cited_object_ref:
        cited_object_ref = (
            f"identifier:{citation.get('identifier_type')}:{citation.get('identifier_value')}"
            if citation.get("identifier_value")
            else f"library-citation:{citation['citation_id']}"
        )

    payload = {
        "package_id": request.core_package_id,
        "project_ref": request.project_ref,
        "citation_key": f"library:{citation['citation_key']}",
        "cited_object_ref": cited_object_ref,
        "citation_format": request.citation_format,
        "locator": citation.get("locator") or None,
        "citation_text": citation.get("raw_citation") or None,
        "citation_data": {
            "library_citation_id": citation["citation_id"],
            "citing_record_id": citation["citing_record_id"],
            "cited_record_id": citation.get("cited_record_id"),
            "identifier_type": citation.get("identifier_type"),
            "identifier_value": citation.get("identifier_value"),
            "relation_type": citation.get("relation_type"),
            "extraction_method": citation.get("extraction_method"),
            "confidence": citation.get("confidence"),
            "resolution_status": citation.get("resolution_status"),
        },
        "visibility": request.visibility,
        "provenance": {
            "source": "sustainable-catalyst-library",
            "library_backend_contract": CITATION_CONTRACT,
            "declared_not_inferred": True,
            "promoted_at": datetime.now(timezone.utc).isoformat(),
        },
    }
    queued = enqueue_operation(CoreOutboxRequest(
        library_record_id=str(citation["citing_record_id"]),
        operation="scholarly-citation.create",
        payload=payload,
        metadata={
            "schema": CORE_CITATION_HANDOFF_CONTRACT,
            "citation_id": citation["citation_id"],
            "requires_core_capability": "scholarly_interoperability",
            "research_lineage_capability": "research_lineage",
        },
    ))
    return {
        "schema": CORE_CITATION_HANDOFF_CONTRACT,
        "citation_id": citation["citation_id"],
        "cited_object_ref": cited_object_ref,
        "queued": queued,
        "policy": {
            "explicit_governed_promotion": True,
            "platform_core_is_authoritative_after_promotion": True,
            "library_source_citation_remains_preserved": True,
        },
    }
