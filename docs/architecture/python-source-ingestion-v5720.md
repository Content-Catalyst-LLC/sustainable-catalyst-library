# Knowledge Library v5.72.0 — Python Source Ingestion & Normalization Service

v5.72.0 moves source-ingestion and normalization authority to Python/FastAPI while preserving the existing PostgreSQL Library record model and ingestion revisioning pipeline.

## Authority

Python owns source-packet validation, record-packet normalization, source-key consistency, canonical URL validation, normalization lineage, and the API v1 mutation surface. PostgreSQL remains the durable source/record state authority. WordPress remains an optional upload, configuration, routing, and presentation adapter.

## Compatibility

The existing `/v1/ingest/records` route remains supported but is routed through the Python normalization service before entering the existing `ingest_records()` repository path. This preserves record revisions, chunks, embedding invalidation, Core binding staleness, research-candidate supersession, and ingest-event accounting.

## Guardrails

Normalization is deterministic and does not infer research truth, source quality, endorsement, or evidentiary weight. Source identifiers and metadata are preserved. Canonical HTTP(S) URLs are validated rather than silently rewritten. Every API-owned ingest records normalization lineage in PostgreSQL.
