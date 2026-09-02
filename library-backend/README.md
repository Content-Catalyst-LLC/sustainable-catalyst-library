# Sustainable Catalyst Library Backend v1.1.0

This backend is the dedicated Python/PostgreSQL research-intelligence data plane for Sustainable Catalyst Library. WordPress remains authoritative for editorial state, public URLs, users, permissions, and the set of records that should exist.

v1.1.0 is the public-discovery companion to Library v5.6.0. It retains the v1.0 ingestion schema, the v1.0.1 ingestion-hardening contract, the v1.0.2 operations/recovery contract, and the existing PostgreSQL schema while adding bounded Explorer bootstrap, filterable public retrieval, year/topic/source/type facets, and progressive record detail.

## Capabilities

- FastAPI service with `/health` and `/ready`
- PostgreSQL durable research index
- weighted full-text search with trigram title recovery
- topic, object-type, source, year-range, and sort filters
- bounded Explorer bootstrap instead of full-catalog transport
- progressive record preview with body/chunk suppression for Explorer reads
- record chunks, provenance, revisions, timelines, graph edges, facets, and related discovery
- signed HMAC + bearer server-to-server ingestion
- adaptive ingestion, payload splitting, and deterministic chunk fallback
- operations status, integrity audit, targeted repair, and verified pruning
- public-only read boundary by default
- adapter-ready schema for later semantic embeddings/reranking

## Public Explorer API

- `GET /v1/explorer/bootstrap` — bounded stats, facets, four featured records, and four recently updated records
- `GET /v1/search` — supports `q`, `object_type`, `source_key`, `topic`, `year_from`, `year_to`, `sort`, `limit`, and `offset`
- `GET /v1/records/{record_id}?include_body=false` — bounded progressive record detail
- `GET /v1/records/{record_id}/related`
- `GET /v1/records/{record_id}/timeline`
- `GET /v1/graph/{record_id}`
- `GET /v1/facets`

The WordPress Explorer normally calls these routes through its own public REST proxy, which provides a WordPress-local fallback if the Python service is unavailable.

## Signed operations API

The existing signed endpoints remain unchanged:

- `POST /v1/ingest/records`
- `POST /v1/ingest/edges`
- `DELETE /v1/records/{record_id}`
- `GET /v1/admin/status`
- `POST /v1/admin/integrity`
- `POST /v1/admin/prune`

## Deployment boundary

The compose file remains bound to `127.0.0.1:8087` and the external `sc-internal` Docker network. Continue using `https://library-api.sustainablecatalyst.com` through Caddy. Preserve the existing `.env` on upgrade.

No PostgreSQL schema migration is required for v1.1.0.
