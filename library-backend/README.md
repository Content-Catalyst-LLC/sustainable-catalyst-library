# Sustainable Catalyst Library Backend v1.0.2

This backend is the dedicated Python research-intelligence data plane for Sustainable Catalyst Library. WordPress remains the authority for public presentation, editorial state, users, permissions, and the set of records that should exist.

v1.0.2 is the operations/recovery companion to Library v5.5.2. It retains the v1.0 ingestion schema and existing PostgreSQL schema while adding signed operational diagnostics and integrity recovery endpoints.

## Capabilities

- FastAPI service with `/health` and `/ready`
- PostgreSQL durable research index
- weighted PostgreSQL full-text search with trigram title recovery
- record chunks, provenance, revisions, timelines, graph edges, facets, and related discovery
- signed HMAC + bearer server-to-server ingestion
- v1.0.1 adaptive-ingestion and deterministic chunk fallback contracts
- operations status from existing ingest events and index coverage
- WordPress-vs-backend integrity auditing
- missing, stale, orphan, and chunkless classification
- source-scoped exact-ID pruning for verified orphan cleanup
- public-only read boundary by default
- adapter-ready schema for later semantic embeddings/reranking

## Signed operations API

The following endpoints require the same bearer + timestamp + HMAC signature as ingestion:

- `GET /v1/admin/status`
- `POST /v1/admin/integrity`
- `POST /v1/admin/prune`

The integrity request supplies the authoritative WordPress record manifest for a source. The backend does not independently decide which records should be deleted.

## Integrity classifications

- **missing** — expected by WordPress but absent from the backend;
- **stale** — backend `source_updated_at` trails WordPress;
- **orphaned** — present in the backend but absent from the current WordPress published manifest;
- **chunkless** — non-empty backend record unexpectedly has no chunks.

`repair_record_ids` contains missing + stale + chunkless IDs. Orphans remain separate because pruning is destructive.

## Deployment boundary

The compose file remains bound to `127.0.0.1:8087` and the external `sc-internal` Docker network. Continue using `https://library-api.sustainablecatalyst.com` through Caddy. Preserve the existing `.env` on upgrade.

No PostgreSQL schema migration is required for v1.0.2.

## API

Public/read:

- `GET /health`
- `GET /ready`
- `GET /v1/search`
- `GET /v1/records/{record_id}`
- `GET /v1/records/{record_id}/related`
- `GET /v1/records/{record_id}/timeline`
- `GET /v1/graph/{record_id}`
- `GET /v1/facets`

Signed/write/operations:

- `POST /v1/ingest/records`
- `POST /v1/ingest/edges`
- `DELETE /v1/records/{record_id}`
- `GET /v1/admin/status`
- `POST /v1/admin/integrity`
- `POST /v1/admin/prune`
