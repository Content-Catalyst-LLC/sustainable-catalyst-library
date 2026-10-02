from __future__ import annotations

from hashlib import sha256
import json
from typing import Any
from urllib.parse import urlsplit
from uuid import uuid4

from pydantic import ValidationError
from psycopg.types.json import Jsonb

from .db import get_pool
from .models import RecordBatch, RecordPacket, SourcePacket
from .repository import canonical_json, ingest_records

LIBRARY_VERSION = "5.76.0"
BACKEND_VERSION = "2.87.0"
CONTRACT = "sc-library-python-source-ingestion-service/1.0"
READINESS_CONTRACT = "sc-library-python-source-ingestion-readiness/1.0"
NORMALIZATION_CONTRACT = "sc-library-source-normalization/1.0"
SOURCE_STATE_CONTRACT = "sc-library-source-ingestion-state/1.0"


def guardrails() -> dict[str, bool]:
    return {
        "python_is_source_ingestion_authority": True,
        "wordpress_php_is_source_ingestion_authority": False,
        "postgresql_is_ingestion_state_authority": True,
        "normalization_is_deterministic": True,
        "normalization_preserves_source_meaning": True,
        "normalization_infers_research_truth": False,
        "source_inclusion_implies_endorsement": False,
        "source_inclusion_implies_evidence_quality": False,
        "source_identifiers_preserved": True,
        "source_and_record_provenance_preserved": True,
        "normalization_lineage_persisted": True,
        "legacy_v1_ingest_routes_use_python_normalization": True,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "persistence": "postgresql",
        "canonical_ingest_schema": "sc-library-backend-ingest/1.0",
        "normalization_schema": NORMALIZATION_CONTRACT,
        "legacy_ingest_route": "/v1/ingest/records",
        "api_v1_ingest_route": "/api/library/v1/admin/ingestion/records",
        "wordpress": {"role": "upload-configuration-presentation-adapter", "required": False, "authoritative": False},
        "guardrails": guardrails(),
    }


def _clean(value: Any, limit: int) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _url(value: Any) -> str | None:
    text = str(value or "").strip()[:2000]
    if not text:
        return None
    parsed = urlsplit(text)
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        raise ValueError("canonical_url-must-be-http-or-https")
    return text


def _hash(value: Any) -> str:
    return sha256(canonical_json(value).encode("utf-8")).hexdigest()


def _source_payload(raw: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(raw, dict):
        raise ValueError("source-object-required")
    source_key = _clean(raw.get("source_key"), 191)
    name = _clean(raw.get("name"), 255)
    source_type = _clean(raw.get("source_type") or "external", 80)
    if not source_key or not name:
        raise ValueError("source_key-and-name-required")
    metadata = dict(raw.get("metadata") or {})
    metadata.update({
        "ingestion_authority": "python-backend",
        "normalization_contract": NORMALIZATION_CONTRACT,
        "normalization_library_version": LIBRARY_VERSION,
        "normalization_backend_version": BACKEND_VERSION,
    })
    return {
        "source_key": source_key,
        "name": name,
        "source_type": source_type,
        "canonical_url": _url(raw.get("canonical_url")),
        "metadata": metadata,
    }


def _record_payload(raw: dict[str, Any], source_key: str) -> dict[str, Any]:
    if not isinstance(raw, dict):
        raise ValueError("record-object-required")
    out = dict(raw)
    out["record_id"] = _clean(raw.get("record_id"), 255)
    out["source_key"] = _clean(raw.get("source_key") or source_key, 191)
    out["object_type"] = _clean(raw.get("object_type"), 80)
    out["title"] = _clean(raw.get("title"), 1000)
    if not out["record_id"] or not out["object_type"] or not out["title"]:
        raise ValueError("record_id-object_type-title-required")
    if out["source_key"] != source_key:
        raise ValueError("record-source-key-must-match-source")
    if "canonical_url" in out:
        out["canonical_url"] = _url(out.get("canonical_url"))
    metadata = dict(raw.get("metadata") or {})
    metadata.update({
        "ingestion_authority": "python-backend",
        "normalization_contract": NORMALIZATION_CONTRACT,
        "normalization_library_version": LIBRARY_VERSION,
        "normalization_backend_version": BACKEND_VERSION,
    })
    out["metadata"] = metadata
    return out


def normalize_payload(payload: dict[str, Any]) -> dict[str, Any]:
    try:
        if not isinstance(payload, dict):
            raise ValueError("payload-must-be-object")
        source = SourcePacket.model_validate(_source_payload(payload.get("source") or {}))
        records_raw = payload.get("records")
        if not isinstance(records_raw, list) or not records_raw:
            raise ValueError("records-array-required")
        records = [RecordPacket.model_validate(_record_payload(item, source.source_key)) for item in records_raw]
        batch = RecordBatch(source=source, records=records)
    except (ValueError, ValidationError, TypeError) as exc:
        details = exc.errors() if isinstance(exc, ValidationError) else [str(exc)]
        return {
            "schema": NORMALIZATION_CONTRACT,
            "library_version": LIBRARY_VERSION,
            "backend_version": BACKEND_VERSION,
            "valid": False,
            "errors": details,
            "guardrails": guardrails(),
        }
    normalized = batch.model_dump(mode="json", by_alias=True)
    operations = [
        "trim-bounded-source-fields",
        "validate-http-canonical-urls",
        "enforce-source-key-consistency",
        "deduplicate-authors-topics-tags-preserve-order",
        "attach-ingestion-authority-lineage",
        "preserve-record-identifiers-and-source-metadata",
    ]
    return {
        "schema": NORMALIZATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": True,
        "errors": [],
        "input_sha256": _hash(payload),
        "normalized_sha256": _hash(normalized),
        "operations": operations,
        "normalized": normalized,
        "guardrails": guardrails(),
    }


def _persist_normalization(normalized: dict[str, Any], result: dict[str, Any], request_hash: str) -> str:
    normalization_id = "normalization:" + str(uuid4())
    packet = normalized["normalized"]
    source_key = packet["source"]["source_key"]
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO library_ingestion_normalization_runs(
              normalization_id,source_key,request_hash,input_sha256,normalized_sha256,
              received_count,changed_count,unchanged_count,operations,metadata
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                normalization_id, source_key, request_hash, normalized["input_sha256"], normalized["normalized_sha256"],
                int(result.get("received") or 0), int(result.get("changed") or 0), int(result.get("unchanged") or 0),
                Jsonb(normalized["operations"]), Jsonb({"authority": "python-backend", "contract": NORMALIZATION_CONTRACT}),
            ),
        )
        conn.commit()
    return normalization_id


def ingest_normalized(payload: dict[str, Any], request_hash: str) -> dict[str, Any]:
    normalized = normalize_payload(payload)
    if not normalized.get("valid"):
        raise ValueError(json.dumps(normalized, ensure_ascii=False, default=str))
    batch = RecordBatch.model_validate(normalized["normalized"])
    result = ingest_records(batch, request_hash)
    normalization_id = _persist_normalization(normalized, result, request_hash)
    return {
        "schema": "sc-library-source-ingest-result/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "normalization_id": normalization_id,
        "normalization_sha256": normalized["normalized_sha256"],
        "normalization_operations": normalized["operations"],
        "result": result,
        "authority": "python-backend",
        "guardrails": guardrails(),
    }


def source_state(source_key: str) -> dict[str, Any] | None:
    source_key = _clean(source_key, 191)
    if not source_key:
        raise ValueError("source-key-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT source_key,name,source_type,canonical_url,metadata,created_at,updated_at FROM library_sources WHERE source_key=%s", (source_key,))
        source = cur.fetchone()
        if source is None:
            return None
        cur.execute("SELECT id,source_key,received_count,changed_count,request_hash,duration_ms,created_at FROM library_ingest_events WHERE source_key=%s ORDER BY created_at DESC LIMIT 1", (source_key,))
        ingest = cur.fetchone()
        cur.execute("SELECT normalization_id,request_hash,input_sha256,normalized_sha256,received_count,changed_count,unchanged_count,operations,created_at FROM library_ingestion_normalization_runs WHERE source_key=%s ORDER BY created_at DESC LIMIT 1", (source_key,))
        normalization = cur.fetchone()
    return {
        "schema": SOURCE_STATE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source": dict(source),
        "latest_ingest": dict(ingest) if ingest else None,
        "latest_normalization": dict(normalization) if normalization else None,
        "authority": "python-backend",
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    database = "unavailable"
    counts = {"sources": 0, "ingest_events": 0, "normalization_runs": 0, "records": 0}
    try:
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [
                ("library_sources", "sources"),
                ("library_ingest_events", "ingest_events"),
                ("library_ingestion_normalization_runs", "normalization_runs"),
                ("library_records", "records"),
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
        "normalization_authority": "python-backend",
        "database": database,
        "wordpress_required": False,
        "counts": counts,
        "guardrails": guardrails(),
    }
