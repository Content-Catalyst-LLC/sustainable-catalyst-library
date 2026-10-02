from __future__ import annotations

from typing import Any

from pydantic import ValidationError

from .db import get_pool
from .models import RecordBatch, RecordPacket, SourcePacket
from .query import get_record
from .repository import delete_record, ingest_records

LIBRARY_VERSION = "6.1.0"
BACKEND_VERSION = "3.1.0"
CONTRACT = "sc-library-python-catalog-service/1.0"
READINESS_CONTRACT = "sc-library-python-catalog-service-readiness/1.0"
RESEARCH_OBJECT_CONTRACT = "sc-library-research-object/1.0"
UPSERT_VALIDATION_CONTRACT = "sc-library-catalog-upsert-validation/1.0"

PUBLICATION_FAMILY = {
    "publication", "article", "report", "document", "book", "chapter",
    "dataset", "working-paper", "brief", "reference", "web-resource",
}


def guardrails() -> dict[str, bool]:
    return {
        "python_is_catalog_domain_authority": True,
        "wordpress_php_is_catalog_domain_authority": False,
        "postgresql_is_catalog_state_authority": True,
        "catalog_writes_use_existing_record_revisioning": True,
        "catalog_writes_trigger_existing_embedding_invalidation": True,
        "catalog_writes_preserve_source_and_content_provenance": True,
        "normalization_infers_research_truth": False,
        "normalization_rewrites_source_meaning": False,
        "publication_status_controls_public_visibility": True,
        "signed_admin_required_for_catalog_mutation": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "persistence": "postgresql-library-records",
        "record_model": "sc-library-backend-ingest/1.0",
        "research_object_schema": RESEARCH_OBJECT_CONTRACT,
        "mutation_model": "signed-api-v1-upsert-and-delete",
        "normalization": {
            "authors_topics_tags": "trim-deduplicate-preserve-order",
            "metadata": "preserve-user-fields-add-authority-lineage",
            "language": "explicit-source-value",
            "object_type": "explicit-source-value",
            "title_body_abstract": "preserve-source-meaning",
        },
        "publication_family": sorted(PUBLICATION_FAMILY),
        "wordpress": {
            "role": "presentation-and-api-client",
            "required": False,
            "authoritative": False,
            "legacy_publication_logic": "compatibility-only-retire-candidate",
        },
        "guardrails": guardrails(),
    }


def _packets(payload: dict[str, Any]) -> tuple[SourcePacket, RecordPacket]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    source_raw = payload.get("source")
    record_raw = payload.get("record")
    if not isinstance(source_raw, dict):
        raise ValueError("source-object-required")
    if not isinstance(record_raw, dict):
        raise ValueError("record-object-required")
    source = SourcePacket.model_validate(source_raw)
    record = RecordPacket.model_validate(record_raw)
    if record.source_key != source.source_key:
        raise ValueError("record-source-key-must-match-source")
    metadata = dict(record.metadata)
    metadata.update({
        "catalog_authority": "python-backend",
        "catalog_contract": CONTRACT,
        "catalog_library_version": LIBRARY_VERSION,
        "catalog_backend_version": BACKEND_VERSION,
    })
    record = record.model_copy(update={"metadata": metadata})
    return source, record


def validate_upsert_payload(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        source, record = _packets(payload)
    except (ValueError, ValidationError) as exc:
        details = exc.errors() if isinstance(exc, ValidationError) else [str(exc)]
        return {
            "schema": UPSERT_VALIDATION_CONTRACT,
            "valid": False,
            "errors": details,
            "library_version": LIBRARY_VERSION,
            "backend_version": BACKEND_VERSION,
            "guardrails": guardrails(),
        }
    return {
        "schema": UPSERT_VALIDATION_CONTRACT,
        "valid": True,
        "errors": [],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "normalized": {
            "source": source.model_dump(mode="json"),
            "record": record.model_dump(mode="json"),
        },
        "guardrails": guardrails(),
    }


def _record_any(record_id: str, include_body: bool = True, public_only: bool = False) -> dict[str, Any] | None:
    if public_only:
        return get_record(record_id, include_body=include_body)
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        body_expr = "body_text" if include_body else "left(body_text, 1600) AS body_text"
        cur.execute(
            f"""SELECT record_id,source_key,object_type,title,canonical_url,abstract,{body_expr},language,
                       visibility,publication_status,published_at,source_updated_at,authors,topics,tags,identifiers,
                       metadata,content_hash,revision,created_at,indexed_at
                FROM library_records WHERE record_id=%s""",
            (record_id,),
        )
        row = cur.fetchone()
        if not row:
            return None
        if include_body:
            cur.execute(
                "SELECT ordinal,heading,text,token_count,metadata FROM library_record_chunks WHERE record_id=%s ORDER BY ordinal",
                (record_id,),
            )
            row["chunks"] = list(cur.fetchall())
        else:
            row["chunks"] = []
        return row


def research_object_envelope(record: dict[str, Any]) -> dict[str, Any]:
    return {
        "schema": RESEARCH_OBJECT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "identity": {
            "record_id": record.get("record_id"),
            "object_type": record.get("object_type"),
            "revision": record.get("revision"),
            "content_hash": record.get("content_hash"),
        },
        "source": {
            "source_key": record.get("source_key"),
            "canonical_url": record.get("canonical_url"),
            "source_updated_at": record.get("source_updated_at"),
        },
        "publication": {
            "status": record.get("publication_status"),
            "visibility": record.get("visibility", "public"),
            "published_at": record.get("published_at"),
            "publication_family": record.get("object_type") in PUBLICATION_FAMILY,
        },
        "descriptive": {
            "title": record.get("title"),
            "abstract": record.get("abstract", ""),
            "language": record.get("language"),
            "authors": list(record.get("authors") or []),
            "topics": list(record.get("topics") or []),
            "tags": list(record.get("tags") or []),
            "identifiers": dict(record.get("identifiers") or {}),
        },
        "content": {
            "body_text": record.get("body_text", ""),
            "chunks": list(record.get("chunks") or []),
        },
        "metadata": dict(record.get("metadata") or {}),
        "indexed_at": record.get("indexed_at"),
        "guardrails": guardrails(),
    }


def get_research_object(record_id: str, include_body: bool = True, public_only: bool = True) -> dict[str, Any] | None:
    row = _record_any(record_id, include_body=include_body, public_only=public_only)
    return None if row is None else research_object_envelope(row)


def upsert_record(payload: dict[str, Any], request_hash: str) -> dict[str, Any]:
    source, record = _packets(payload)
    result = ingest_records(RecordBatch(source=source, records=[record]), request_hash)
    stored = _record_any(record.record_id, include_body=True, public_only=False)
    return {
        "schema": "sc-library-catalog-upsert-result/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "result": result,
        "research_object": research_object_envelope(stored) if stored else None,
        "guardrails": guardrails(),
    }


def delete_catalog_record(record_id: str) -> dict[str, Any]:
    deleted = delete_record(record_id)
    return {
        "schema": "sc-library-catalog-delete-result/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_id": record_id,
        "deleted": bool(deleted),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    database = "unavailable"
    counts = {"records": 0, "sources": 0, "versions": 0, "chunks": 0}
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [
                ("library_records", "records"),
                ("library_sources", "sources"),
                ("library_record_versions", "versions"),
                ("library_record_chunks", "chunks"),
            ]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            database = "ready"
    except Exception:
        pass
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if database == "ready" else "degraded",
        "authority": "python-backend",
        "write_authority": "python-backend",
        "database": database,
        "wordpress_required": False,
        "counts": counts,
        "research_object_schema": RESEARCH_OBJECT_CONTRACT,
        "guardrails": guardrails(),
    }
