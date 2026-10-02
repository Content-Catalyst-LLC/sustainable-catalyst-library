from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .citation_graph import (
    CitationCreateRequest, CoreScholarlyCitationHandoffRequest,
    citation_graph, citation_readiness, enqueue_core_scholarly_citation,
    import_record_metadata_citations, list_citations, upsert_citation,
)
from .db import get_pool
from .query import get_record, graph_neighborhood

LIBRARY_VERSION = "6.1.0"
BACKEND_VERSION = "3.1.0"
CONTRACT = "sc-library-python-provenance-citation-evidence-graph/1.0"
READINESS_CONTRACT = "sc-library-python-provenance-citation-evidence-graph-readiness/1.0"
PROVENANCE_CONTRACT = "sc-library-record-provenance/1.0"
EVIDENCE_GRAPH_CONTRACT = "sc-library-evidence-graph/1.0"
CITATION_ENVELOPE_CONTRACT = "sc-library-citation-envelope/1.0"


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "python_is_provenance_authority": True,
        "python_is_citation_authority": True,
        "python_is_evidence_graph_authority": True,
        "wordpress_php_is_provenance_authority": False,
        "wordpress_php_is_citation_authority": False,
        "wordpress_php_is_evidence_graph_authority": False,
        "citation_confidence_is_truth_probability": False,
        "graph_relationship_implies_causality": False,
        "unresolved_references_are_preserved": True,
        "citations_are_declared_or_imported_not_llm_inferred": True,
        "provenance_lineage_is_descriptive_not_truth_certification": True,
        "platform_core_bindings_are_descriptive": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    basis = {
        "authority": "python-backend",
        "resources": ["record-provenance", "citations", "evidence-graph", "record-versions", "normalization-lineage", "core-bindings"],
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "service_id": "library-provenance-graph:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "database": "postgresql",
        "wordpress": {"role": "presentation-and-api-client", "required": False, "authoritative": False},
        "components": {
            "citations": "citation_graph.library_citations",
            "evidence_relationships": "query.graph_neighborhood/library_edges",
            "record_versions": "library_record_versions",
            "normalization_lineage": "library_ingestion_normalization_runs",
            "platform_core_bindings": "citation_graph/core-bindings",
        },
        "guardrails": guardrails(),
    }


def _public_record_ids(record_ids: list[str]) -> set[str]:
    ids=sorted({str(x) for x in record_ids if x})
    if not ids: return set()
    pool=get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT record_id FROM library_records WHERE record_id=ANY(%s) AND visibility='public' AND publication_status='published'", (ids,))
        return {str(row["record_id"]) for row in cur.fetchall()}


def record_provenance(record_id: str, version_limit: int = 25) -> dict[str, Any]:
    record=get_record(record_id, include_body=False)
    if record is None: raise ValueError("public record not found")
    version_limit=max(1,min(100,int(version_limit)))
    pool=get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT revision,content_hash,observed_at,snapshot FROM library_record_versions WHERE record_id=%s ORDER BY revision DESC LIMIT %s", (record_id,version_limit))
        versions=[dict(x) for x in cur.fetchall()]
        cur.execute("SELECT normalization_id,request_hash,input_sha256,normalized_sha256,received_count,changed_count,unchanged_count,operations,metadata,created_at FROM library_ingestion_normalization_runs WHERE source_key=%s ORDER BY created_at DESC LIMIT 10", (record.get("source_key"),))
        normalization=[dict(x) for x in cur.fetchall()]
        cur.execute("SELECT event_id,request_hash,received_count,changed_count,duration_ms,created_at FROM library_ingest_events WHERE source_key=%s ORDER BY created_at DESC LIMIT 10", (record.get("source_key"),))
        ingest_events=[dict(x) for x in cur.fetchall()]
    basis={"record_id":record_id,"content_hash":record.get("content_hash"),"revision":record.get("revision"),"versions":[{"revision":x.get("revision"),"content_hash":x.get("content_hash")} for x in versions]}
    return {
        "schema": PROVENANCE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record": record,
        "lineage_fingerprint_sha256": _fp(basis),
        "record_versions": versions,
        "normalization_lineage": normalization,
        "ingest_events": ingest_events,
        "guardrails": guardrails(),
    }


def citations_for_record(record_id: str, direction: str = "both", limit: int = 100) -> dict[str, Any]:
    if get_record(record_id, include_body=False) is None: raise ValueError("public record not found")
    raw=list_citations(record_id,direction=direction,limit=limit)
    items=[dict(x) for x in raw.get("items") or []]
    ids=[]
    for item in items:
        ids.extend([str(item.get("citing_record_id") or ""),str(item.get("cited_record_id") or "")])
    public=_public_record_ids(ids)
    filtered=[]
    for item in items:
        citing=str(item.get("citing_record_id") or "")
        cited=str(item.get("cited_record_id") or "")
        if citing not in public: continue
        if cited and cited not in public: continue
        filtered.append(item)
    return {
        "schema": CITATION_ENVELOPE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_id": record_id,
        "direction": direction if direction in {"incoming","outgoing","both"} else "both",
        "items": filtered,
        "count": len(filtered),
        "guardrails": guardrails(),
    }


def evidence_graph(record_id: str, depth: int = 2, limit: int = 250, include_core: bool = True) -> dict[str, Any]:
    if get_record(record_id, include_body=False) is None: raise ValueError("public record not found")
    depth=max(1,min(4,int(depth))); limit=max(1,min(1000,int(limit)))
    relationships=graph_neighborhood(record_id,limit)
    citations=citation_graph(record_id,depth=depth,limit=limit,include_core=include_core)
    nodes={}
    for node in list(relationships.get("nodes") or []) + list(citations.get("nodes") or []):
        rid=str(node.get("record_id") or "")
        if rid: nodes[rid]={**nodes.get(rid,{}),**dict(node)}
    public=_public_record_ids(list(nodes))
    nodes={rid:node for rid,node in nodes.items() if rid in public}
    edges=[]
    for edge in relationships.get("edges") or []:
        source=str(edge.get("source_record_id") or ""); target=str(edge.get("target_record_id") or "")
        if source in public and target in public:
            edges.append({"edge_family":"relationship","edge_id":f"relationship:{edge.get('edge_id')}",**dict(edge)})
    for edge in citations.get("edges") or []:
        source=str(edge.get("citing_record_id") or ""); target=str(edge.get("cited_record_id") or "")
        if source in public and (not target or target in public):
            edges.append({"edge_family":"citation","edge_id":f"citation:{edge.get('citation_id')}",**dict(edge)})
    basis={"root":record_id,"depth":depth,"node_ids":sorted(nodes),"edge_ids":sorted(str(x.get("edge_id")) for x in edges)}
    return {
        "schema": EVIDENCE_GRAPH_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "root_record_id": record_id,
        "depth": depth,
        "nodes": list(nodes.values()),
        "edges": edges[:limit],
        "node_count": len(nodes),
        "edge_count": min(len(edges),limit),
        "graph_fingerprint_sha256": _fp(basis),
        "edge_families": ["relationship","citation"],
        "platform_core_enrichment": include_core,
        "guardrails": guardrails(),
    }


def create_citation(payload: dict[str, Any]) -> dict[str, Any]:
    return upsert_citation(CitationCreateRequest.model_validate(payload))


def import_citations(record_id: str) -> dict[str, Any]:
    return import_record_metadata_citations(record_id)


def core_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    return enqueue_core_scholarly_citation(CoreScholarlyCitationHandoffRequest.model_validate(payload))


def readiness() -> dict[str, Any]:
    citation=citation_readiness()
    counts={"citations":int(citation.get("citation_count") or 0),"edges":0,"record_versions":0,"normalization_runs":0}
    db_state="unavailable"
    try:
        pool=get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            for table,key in [("library_edges","edges"),("library_record_versions","record_versions"),("library_ingestion_normalization_runs","normalization_runs")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key]=int(cur.fetchone()["n"])
        db_state="ready"
    except Exception:
        pass
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if db_state=="ready" else "degraded",
        "authority": "python-backend",
        "database": db_state,
        "wordpress_required": False,
        "counts": counts,
        "citation_runtime": citation,
        "guardrails": guardrails(),
    }
