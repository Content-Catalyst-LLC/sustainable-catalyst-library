# Sustainable Catalyst Library Backend v1.0.1

This backend is the dedicated Python research-intelligence data plane for Sustainable Catalyst Library. WordPress remains the authority for public presentation, editorial state, users, and permissions.

v1.0.1 is the ingestion-hardening companion to Library v5.5.1. It keeps the v1.0 API and PostgreSQL schema compatible while making large Library reindexes safer and more observable.

## Capabilities

- FastAPI service with `/health` and `/ready`
- PostgreSQL durable research index
- weighted PostgreSQL full-text search with trigram title recovery
- record-level provenance and stable source identities
- chunk storage for long-form documents
- immutable per-record revision snapshots and public timelines
- explicit knowledge-graph edges
- related-record discovery from shared topics/tags
- public facets for object type, source, and topic
- signed HMAC + bearer server-to-server ingestion
- public-only read boundary by default
- request-limit telemetry in `/health`
- explicit `413` limit headers for adaptive clients
- deterministic backend chunk generation when a compact single-record packet omits duplicated chunks
- adapter-ready schema for later semantic embeddings/reranking without making an external model the authority

## Ingestion hardening contract

Library v5.5.1 preflights JSON payload bytes and record count before sending. If a server still responds with HTTP 413, the client splits only that batch and retries its children. Network failures and selected transient HTTP statuses receive a small bounded retry budget; 413 is never blindly retried.

Backend v1.0.1 reports:

```json
"ingest_limits": {
  "max_batch_records": 200,
  "max_body_bytes": 12582912,
  "max_body_mb": 12
}
```

When a single record is too large because `body_text` and chunks would duplicate too much transport data, WordPress can send the record with an empty `chunks` list. The backend regenerates the same 6,000-character `wordpress-text-v1` chunk contract before hashing and persistence.

## Deployment boundary

The compose file binds the service to `127.0.0.1:8087` so it is not exposed directly to the internet. Put it behind the existing Sustainable Catalyst reverse proxy at `https://library-api.sustainablecatalyst.com`.

The service expects the existing external Docker network `sc-internal` and a PostgreSQL host reachable as `sc-postgres`. It does not create a second database container.

## Upgrade from v1.0.0

Preserve the existing `.env`; no database migration is required.

```bash
cd /opt/sustainable-catalyst/library-backend
cp .env /tmp/sc-library-backend.env
# replace application files with the v1.0.1 package
cp /tmp/sc-library-backend.env .env

docker compose build --pull
docker compose up -d --force-recreate
docker compose ps
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/ready | python3 -m json.tool
```

## Signed write contract

Write requests require:

- `Authorization: Bearer <SC_LIBRARY_BACKEND_API_KEY>`
- `X-SC-Timestamp: <unix seconds>`
- `X-SC-Signature: HMAC-SHA256(key, METHOD + "\\n" + PATH + "\\n" + TIMESTAMP + "\\n" + SHA256(BODY))`

Read endpoints return only records with `visibility=public` and `publication_status=published`.

## API

- `GET /health`
- `GET /ready`
- `POST /v1/ingest/records`
- `POST /v1/ingest/edges`
- `DELETE /v1/records/{record_id}`
- `GET /v1/search`
- `GET /v1/records/{record_id}`
- `GET /v1/records/{record_id}/related`
- `GET /v1/records/{record_id}/timeline`
- `GET /v1/graph/{record_id}`
- `GET /v1/facets`
