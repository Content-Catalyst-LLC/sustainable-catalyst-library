# Sustainable Catalyst Library v5.5.1 — Ingestion Hardening

## Purpose

v5.5.1 hardens the WordPress → Python ingestion path after production reindexing showed that fixed 100-record batches could exceed the backend request-body ceiling. The repair keeps the existing public API, database schema, signed-ingestion contract, and Library record identities intact.

## Client-side adaptive batching

Bulk synchronization now uses seed groups only to bound PHP memory. Before any request is sent, each candidate batch is encoded to JSON and checked against both:

- a configurable record target, default **25 records**;
- a configurable payload target, default **6 MB**.

If either target is exceeded, only that batch is divided and reevaluated. This produces variable leaf-batch sizes based on the real content rather than assuming every Library object is the same size.

## HTTP 413 recovery

HTTP 413 is treated as a sizing signal, not as a generic retryable failure. A multi-record batch that receives 413 is split in half and its children are sent independently. The rest of the reindex continues.

A single record that is still too large switches to a compact transport packet that keeps `body_text` but omits duplicated client-generated chunks. Backend v1.0.1 deterministically recreates the same 6,000-character chunk contract before persistence.

## Bounded transient retries

Only network failures and selected transient statuses are retried:

- 408
- 425
- 429
- 500
- 502
- 503
- 504

The default retry budget is two retries after the initial attempt. Backoff is bounded and deliberately small because bulk sync runs inside a WordPress administrative request.

## Truthful bulk-sync telemetry

The previous summary exposed only records, batches, changed, and errors. v5.5.1 records:

- candidate records;
- completed records;
- changed records;
- unchanged records;
- failed records;
- successful leaf batches;
- actual HTTP requests;
- total splits;
- preflight splits;
- HTTP-413 splits;
- retries;
- compact single-record packets;
- payload bytes sent;
- largest payload observed;
- error count;
- bounded error details;
- failed WordPress record IDs.

This makes a result such as `changed: 500` interpretable: unchanged records are counted separately instead of disappearing from the operational picture.

## Resume checkpoint

Each bulk-sync seed updates `sc_library_backend_sync_checkpoint`. If records remain failed after retries, the Python Backend admin screen exposes **Resume failed records**. Resume processes only the stored failed IDs and does not restart the full Library reindex.

## Backend v1.0.1

Backend v1.0.1 remains schema-compatible with v1.0.0 and adds:

- ingest-limit metadata to `/health`;
- `X-SC-Max-Body-Bytes` and `X-SC-Max-Batch-Records` on 413 responses;
- deterministic server-side chunk fallback for compact packets;
- `adaptive_ingestion` and `server_chunk_fallback` capability flags.

No PostgreSQL migration is required.
