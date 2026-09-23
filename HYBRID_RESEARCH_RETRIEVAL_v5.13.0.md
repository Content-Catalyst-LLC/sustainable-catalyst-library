# Sustainable Catalyst Library v5.13.0 — Hybrid Research Retrieval & Core-Aware Results

## Release intent

v5.13.0 turns the Library's public research index into a hybrid retrieval service while preserving the Platform Core boundary established in v5.12.0.

**Library owns** source acquisition, parsing, normalized records, raw chunks, full-text indexes, semantic embeddings, retrieval, connectors, and document intelligence.

**Platform Core owns** governed research/evidence objects, provenance and lineage, claims/findings/arguments, cross-study synthesis, reproducibility, visual reasoning, statistical-reasoning objects, and cross-product exchange.

The search path consumes durable Core bindings but does not move raw chunks or embeddings into Core and does not make a live Core request for every result.

## Versions

- Knowledge Library / WordPress: **5.13.0**
- Library Python backend: **2.24.0**
- Platform Core dependency: **v3.3.0+ bridge contracts from Library v5.12.0**

## Retrieval modes

`GET /v1/search` now accepts:

- `mode=lexical` — PostgreSQL weighted full-text search plus trigram title recovery.
- `mode=semantic` — provider-generated query embedding against Library-owned document embeddings.
- `mode=hybrid` — default; lexical and semantic candidate rankings are combined through weighted Reciprocal Rank Fusion (RRF).
- `include_core=true|false` — include or suppress durable Platform Core binding context on returned records.

Existing object type, source, topic, year, sort, limit, and offset filters are preserved.

## Why Reciprocal Rank Fusion

Lexical scores and embedding cosine similarity are not measurements on the same scale. v5.13.0 therefore does not add or average the raw values. It converts each retrieval channel into a rank and fuses the rankings with configurable weighted RRF.

Defaults:

```text
RRF k                60
lexical weight       1.0
semantic weight      1.0
candidate multiplier 4
```

Each result exposes its lexical rank/score, semantic rank/score, fused rank, and fused score for auditability.

## Semantic vector store

`library_record_embeddings` stores one current semantic representation per public Library record:

- Library record ID
- Library content hash
- embedding-input hash
- provider/model
- dimensions
- normalized vector
- timestamps

The table intentionally uses PostgreSQL `double precision[]` and a deterministic cosine function rather than requiring a new pgvector server extension. This keeps v5.13.0 deployable on the existing PostgreSQL service. A future release can add an ANN/pgvector adapter if corpus size requires it without changing the public retrieval contract.

## Embedding job queue

`library_embedding_jobs` is an idempotent queue for semantic indexing.

- Existing public/published records without a current vector are queued by the additive schema migration.
- Changed public/published records are re-queued by normal Library ingestion.
- Records that become non-public or unpublished have their public embedding/job removed.
- A bounded background worker processes jobs only when a real embedding provider is configured.
- Failed jobs retain bounded error state and exponential retry timing.

Signed operational endpoints remain available for diagnostics/manual execution:

- `GET /v1/admin/embeddings/status`
- `POST /v1/admin/embeddings/run-once?limit=25`

## Provider adapters

The backend ships with two real embedding adapters:

1. **Gemini** — default model identity `gemini-embedding-2`, with configurable dimensions.
2. **OpenAI-compatible** — a configurable embeddings endpoint using the common `data[0].embedding` response contract.

There is no pseudo-semantic hash-vector fallback. If no provider is configured, semantic search reports that state and hybrid requests explicitly fall back to lexical search.

## Safe degraded mode

Semantic retrieval is optional at deployment time. The backend remains healthy and useful without a new embedding credential:

```json
{
  "requested_mode": "hybrid",
  "effective_mode": "lexical-fallback",
  "degraded": true,
  "reason": "embedding-provider-not-configured"
}
```

This lets the retrieval architecture ship before an external embedding credential is added, without silently misrepresenting lexical heuristics as semantic search.

## Platform Core integration

v5.13.0 reuses `library_core_bindings` from v5.12.0. For each returned Library record, the backend can attach synchronized Core identities including:

When a Library record content hash changes, any mismatched synchronized Core binding is marked `stale` until the governed object is re-synchronized. Search enrichment only exposes current `synced` bindings.

- Core object ID
- Core object type
- canonical Core URI
- Library content hash at binding time
- binding metadata
- synchronization state/time

This creates a direct research path from a Library hit to governed Core research/evidence objects while keeping the search request independent of live Core availability.

The result boundary is therefore:

```text
Library result
  ├─ source/document metadata       (Library)
  ├─ lexical/semantic rank signals  (Library)
  ├─ chunks/index/embedding         (Library, not returned wholesale)
  └─ Platform Core bindings
       ├─ research objects           (Core authority)
       ├─ evidence/provenance        (Core authority)
       ├─ findings/claims/arguments  (Core authority)
       └─ synthesis/reproducibility  (Core authority)
```

No claim, finding, evidence judgment, truth status, or research conclusion is inferred by retrieval ranking.

## WordPress integration

The existing `/wp-json/sc-library/v1/backend/search` proxy now forwards:

- source/topic/year filters
- sort
- retrieval mode
- Core-enrichment preference

The Python Backend admin screen also exposes hybrid retrieval readiness. Embedding credentials remain backend-only and are never exposed to WordPress or browser JavaScript.

## Environment

```bash
SC_LIBRARY_EMBEDDING_PROVIDER=disabled
SC_LIBRARY_EMBEDDING_API_KEY=
SC_LIBRARY_EMBEDDING_MODEL=gemini-embedding-2
SC_LIBRARY_EMBEDDING_API_URL=
SC_LIBRARY_EMBEDDING_DIMENSIONS=768
SC_LIBRARY_EMBEDDING_TIMEOUT_SECONDS=12
SC_LIBRARY_EMBEDDING_MAX_ATTEMPTS=5
SC_LIBRARY_EMBEDDING_WORKER_ENABLED=true
SC_LIBRARY_EMBEDDING_WORKER_INTERVAL_SECONDS=30
SC_LIBRARY_EMBEDDING_WORKER_BATCH_SIZE=10
SC_LIBRARY_HYBRID_CANDIDATE_MULTIPLIER=4
SC_LIBRARY_HYBRID_RRF_K=60
SC_LIBRARY_HYBRID_LEXICAL_WEIGHT=1.0
SC_LIBRARY_HYBRID_SEMANTIC_WEIGHT=1.0
```

If `SC_LIBRARY_EMBEDDING_PROVIDER` is omitted and a `GEMINI_API_KEY`/`SC_LIBRARY_EMBEDDING_API_KEY` is already present, the backend can select Gemini automatically. An explicit `disabled` value always wins.

## Next architectural step

v5.14.0 can build Citation Graph & Scholarly Lineage on this retrieval layer while delegating durable research lineage/provenance meaning to Platform Core rather than creating a second Core inside Knowledge Library.
