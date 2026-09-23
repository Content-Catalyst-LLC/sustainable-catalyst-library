# Sustainable Catalyst Library Backend v2.29.0

Adds multi-publication knowledge-landscape analysis while preserving the existing ingestion, retrieval, citation, extraction, and Platform Core bridge contracts.

# Sustainable Catalyst Library Backend v2.26.0

## v2.26.0 — Entity, Finding & Claim Extraction

v2.26.0 adds Library-owned extraction candidates for entities, findings, and claims. Candidates are anchored to exact source locators, chunk ordinals, character spans, source content hashes, extraction methods, and confidence values. They remain non-governed until human review. Accepted finding/claim candidates can be queued through the durable Platform Core outbox into Core Finding, Claim & Evidence Intelligence using allowlisted project-scoped operations. Entity candidates remain Library-owned in this release.

Public readiness: `GET /v1/research-extraction/readiness`. Signed operations: `POST /v1/research-extraction/extract`, `GET /v1/research-extraction/candidates`, `POST /v1/research-extraction/candidates/{candidate_id}/review`, and `POST /v1/research-extraction/core-handoff`.

The extraction layer does not determine truth, scientific validity, causal validity, consensus, evidence strength, or recommendations. Source changes supersede pending/accepted candidates tied to an older content hash.


## v2.25.1 — Citation Graph Route Precedence Repair

v2.25.1 repairs FastAPI/Starlette route precedence so `/v1/citations/{record_id}/graph` reaches the graph handler instead of the generic citation-list route. It is a runtime-only patch; schemas and WordPress v5.14.0 remain unchanged.

## v2.25.0 — Citation Graph & Scholarly Lineage

v2.25.0 adds a Library-owned declared citation graph, exact DOI/PMID/PMCID/ISBN/ISSN resolution, unresolved-reference preservation, bounded citation-neighborhood traversal, Platform Core binding enrichment, and an explicit governed handoff into Platform Core Scholarly Interoperability. Library continues to own citation extraction/indexing; Platform Core remains authoritative for governed lineage, evidence, scholarly packages, synthesis, and reasoning. No LLM-inferred citation edges or automatic truth promotion are introduced.

## v2.24.1 — Core-Aware Search Runtime Repair

v2.24.1 is the runtime-repair backend companion to Knowledge Library v5.13.0.1. It preserves the v2.24.0 retrieval architecture and fixes Core-aware search enrichment by importing the required collections.defaultdict runtime dependency. It preserves the v2.23.0 Platform Core Research Bridge and adds a Library-owned semantic retrieval plane without moving raw documents, chunks, embeddings, or search indexes into Platform Core.

### Retrieval architecture

- `GET /v1/search` now supports `mode=hybrid|lexical|semantic` and `include_core=true|false`.
- Lexical retrieval retains PostgreSQL weighted full-text ranking and trigram title recovery.
- Semantic retrieval uses real provider-generated vectors stored in `library_record_embeddings`; there are no fake/hash embeddings.
- Hybrid relevance uses weighted Reciprocal Rank Fusion (RRF), avoiding invalid arithmetic across lexical and embedding score scales.
- Search results can include durable Platform Core bindings from `library_core_bindings`; search does not make a live Core network call per result.
- If an embedding provider is unavailable, hybrid/semantic requests explicitly degrade to lexical retrieval and report the effective mode.

### Semantic indexing

Changed public/published Library records are queued in `library_embedding_jobs`. A bounded background worker processes the queue when a real embedding provider is configured. Existing public records without a current vector are queued idempotently by the additive schema migration.

Supported provider adapters:

- `gemini` — Gemini Embedding API, default model `gemini-embedding-2`.
- `openai_compatible` — configurable embeddings endpoint implementing the common `data[0].embedding` response shape.
- `disabled` — safe default; lexical retrieval remains fully operational.

Public readiness: `GET /v1/search/readiness`. Signed operations: `GET /v1/admin/embeddings/status` and `POST /v1/admin/embeddings/run-once`.

### Platform Core boundary

Knowledge Library owns acquisition, parsing, chunks, indexes, embeddings, retrieval, connectors, and document intelligence. Platform Core owns governed research/evidence objects, provenance/lineage, claims/findings/arguments, synthesis, reproducibility, visual reasoning, statistical reasoning, and cross-product exchange. v2.24.0 consumes the durable Core bindings introduced in v2.23.0; it does not duplicate Core reasoning.

---

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
- hybrid lexical/semantic retrieval with Library-owned embeddings and explicit lexical fallback

## Public Explorer API

- `GET /v1/explorer/bootstrap` — bounded stats, facets, four featured records, and four recently updated records
- `GET /v1/search` — supports `q`, filters, `sort`, `mode=hybrid|lexical|semantic`, `include_core`, `limit`, and `offset`
- `GET /v1/search/readiness` — semantic-provider/index state plus Core-aware retrieval boundary
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
- `GET /v1/admin/embeddings/status`
- `POST /v1/admin/embeddings/run-once`

## Deployment boundary

The compose file remains bound to `127.0.0.1:8087` and the external `sc-internal` Docker network. Continue using `https://library-api.sustainablecatalyst.com` through Caddy. Preserve the existing `.env` on upgrade.

v2.24.0 applies an additive, idempotent PostgreSQL migration for semantic embeddings and embedding jobs. Existing tables and v2.23.0 Core bridge state are preserved.
