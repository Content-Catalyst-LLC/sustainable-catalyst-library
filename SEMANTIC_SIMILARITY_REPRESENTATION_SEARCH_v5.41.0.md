# Knowledge Library v5.41.0 — Semantic Similarity & Representation Search

## Release identity

- WordPress plugin: **5.41.0**
- Python backend: **2.52.0**
- Go ingestion runtime: **0.1.0**
- Rust graph runtime: **0.2.0**

## Purpose

v5.41.0 turns the governed embedding foundation introduced in v5.40 into a production retrieval capability. It adds specification-aware semantic text search, stored record-to-record similarity, representation descriptors, and transparent semantic lineage inside the existing hybrid retrieval engine.

## Architecture

### Text query path

`query text → governed embedding specification → query embedding → current-content/current-specification vectors → cosine similarity → semantic ranking → optional hybrid RRF fusion`

Text-query semantic retrieval requires a configured embedding provider because the query itself must be embedded. If no provider is configured, `/v1/search?mode=hybrid` degrades to lexical retrieval and `/v1/semantic-similarity/search` reports `available=false` rather than fabricating vectors.

### Record similarity path

`stored record representation → same-specification current representations → cosine similarity → ranked semantic neighborhood`

Record-to-record similarity does **not** require a live embedding provider. It reuses an already-governed stored vector and compares it only with current-content representations created under the same specification fingerprint.

## Current/stale policy

Semantic candidates are eligible only when the stored embedding `content_hash` matches the current Library record `content_hash`. Text-query search additionally requires the active embedding specification fingerprint. Record-to-record search uses the seed representation's specification fingerprint, allowing semantic exploration to continue even if the provider is temporarily disabled.

Legacy, stale-content, or different-specification vectors remain stored for provenance/migration but are excluded from governed semantic search by default.

## Result lineage

Semantic candidates expose:

- `representation_id`
- embedding specification fingerprint
- provider and model
- dimensions
- execution target
- execution ID
- source content hash
- semantic similarity score/rank

Hybrid retrieval continues to use weighted reciprocal-rank fusion rather than pretending lexical and cosine scores share a common numeric scale.

## Public backend surfaces

- `GET /v1/semantic-similarity/readiness`
- `GET /v1/semantic-similarity/search`
- `GET /v1/semantic-similarity/records/{record_id}`
- `GET /v1/semantic-representations/{record_id}`
- Existing `GET /v1/search` remains the main hybrid search surface and now uses governed semantic candidates.

WordPress exposes matching public proxy routes under `sc-library/v1/backend/...`.

## Guardrails

The release permanently preserves these distinctions:

- semantic similarity is **not evidence**;
- semantic similarity is **not truth**;
- semantic similarity is **not causality**;
- an embedding is a derived analytical representation, not the source;
- semantic retrieval does not automatically promote anything into Platform Core;
- lexical and semantic signals remain inspectable independently.

## Upgrade behavior

The schema change is additive: it adds lookup indexes for embedding specification fingerprints and representation IDs. Existing vectors, embedding jobs, handoffs, corpus data, graph runtimes, and WordPress records are retained.

No automatic embedding backfill is performed by this release. v5.40.0.1 remains the safe baseline and v5.41.0 can be deployed before enabling provider compute.
