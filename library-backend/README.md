# Sustainable Catalyst Library Backend v1.0.0

This is the first dedicated Python research-intelligence backend for Sustainable Catalyst Library. WordPress remains the authority for public presentation, editorial state, users, and permissions. The backend is an additive server-side data plane for indexing, discovery, provenance, graph traversal, and knowledge evolution.

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
- adapter-ready schema for later semantic embeddings/reranking without making an external model the authority

## Deployment boundary

The compose file binds the service to `127.0.0.1:8087` so it is not exposed directly to the internet. Put it behind the existing Sustainable Catalyst reverse proxy, for example at `https://library-api.sustainablecatalyst.com`.

The service expects the existing external Docker network `sc-internal` and a PostgreSQL host reachable as `sc-postgres`. It does not create a second database container.

## First deployment

```bash
cd /opt/sustainable-catalyst/library-backend
cp .env.example .env
nano .env

docker compose build --pull
docker compose up -d
docker compose ps
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
```

Create the database/user in the existing PostgreSQL container before first boot. See `DEPLOY_CONTABO.md` in the repository root.

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
