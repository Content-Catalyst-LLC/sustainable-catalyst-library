# Sustainable Catalyst Knowledge Library v5.40.0

## Scientific Embedding Governance & Compute Handoff

WordPress: **5.40.0**  
Python backend: **2.51.0**  
Go ingestion runtime: **0.1.0**  
Rust graph runtime: **0.2.0**

v5.40.0 turns the Library's existing semantic vector infrastructure into an explicitly governed research subsystem without replacing the working Library-owned vector index.

### What is new
- Deterministic `EmbeddingSpecification` objects with provider, model, dimensions, input profile, normalization and cosine-similarity semantics.
- Content-, input- and specification-bound `EmbeddingRepresentation` provenance.
- Specification-aware embedding backfill planning and queueing.
- Durable Workspace embedding-compute handoff queue with idempotent payloads, claim, complete and fail/retry contracts.
- Local execution remains the default and the existing Library embedding worker remains the safe fallback path.
- `workspace_preferred` can fall back to local compute after terminal Workspace failures; `workspace_only` does not silently fall back.
- Current operational vectors remain in `library_record_embeddings`; existing vectors are not deleted during the migration.
- Three portable JSON Schemas define the specification, representation and Workspace handoff contracts.

### Authority boundary
- Knowledge Library owns source ingestion, canonical source text, retrieval and the operational vector index.
- Workspace may execute embedding compute.
- Platform Core owns the governed representation/provenance contract and cross-product exchange semantics.
- An embedding or similarity score is not evidence, truth, causality, consensus or source quality.
- No automatic Platform Core promotion is introduced.

### Upgrade behavior
The new compute mode defaults to `local`, so an existing v5.39.0.1 deployment keeps using the current embedding worker unless `SC_LIBRARY_EMBEDDING_COMPUTE_TARGET` is explicitly changed.

Existing vector rows receive new nullable provenance columns through idempotent `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` migrations. Legacy vectors stay usable. They appear as legacy/stale-specification representations until an explicit v5.40 backfill is queued.

### Compute modes
- `local` — Library backend executes embeddings exactly as before, while writing v5.40 provenance.
- `workspace_preferred` — newly queued/backfilled work is handed to Workspace; terminal Workspace failure returns the job to `local-fallback`.
- `workspace_only` — newly queued/backfilled work remains Workspace-only and never silently executes locally.

### New backend endpoints
- `GET /v1/embeddings/specification`
- `GET /v1/embeddings/governance/readiness`
- `POST /v1/admin/embeddings/backfill`
- `GET /v1/admin/embeddings/handoffs`
- `POST /v1/admin/embeddings/handoffs/prepare`
- `POST /v1/admin/embeddings/handoffs/claim`
- `POST /v1/admin/embeddings/handoffs/{handoff_id}/complete`
- `POST /v1/admin/embeddings/handoffs/{handoff_id}/fail`

Admin routes retain the existing signed Library backend write authorization model.
