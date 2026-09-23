# Sustainable Catalyst Library v5.13.0 — Hybrid Research Retrieval & Core-Aware Results

Release class: research retrieval infrastructure  
WordPress: 5.13.0  
Python backend: 2.24.0  
Platform Core: consumes the v5.12.0 Core v3.3+ Research Bridge  
Database: additive PostgreSQL migration through idempotent `schema.sql`  
Required new credentials: none for lexical operation; semantic retrieval requires an explicitly configured real embedding provider

## Added

- `mode=hybrid|lexical|semantic` on the canonical public `/v1/search` endpoint.
- Weighted Reciprocal Rank Fusion across lexical and semantic candidate rankings.
- Library-owned `library_record_embeddings` vector store using PostgreSQL arrays and cosine similarity.
- Idempotent `library_embedding_jobs` queue with background processing, retries, and content-hash invalidation.
- Gemini embedding adapter and an optional OpenAI-compatible embedding adapter.
- Explicit lexical fallback when semantic embeddings are not configured or unavailable.
- `GET /v1/search/readiness` with provider/index/degradation state.
- Signed embedding queue status and bounded run-once operations.
- Core-aware search results populated from durable `library_core_bindings` rather than live per-result Core calls.
- Synced Core bindings are marked stale when the source content hash changes, preventing outdated governed identities from appearing current.
- Expanded WordPress backend search proxy and retrieval readiness diagnostics.

## Platform Core leverage

v5.13.0 does not duplicate Core reasoning. Retrieval returns Core object identities and binding metadata created through v5.12.0, allowing later interfaces to traverse into Core-governed evidence, provenance, claims/findings, synthesis, reproducibility, visual reasoning, statistical reasoning, and exchange objects.

Library continues to own source-near search data: raw documents, chunks, lexical indexes, semantic vectors, connectors, and retrieval ranking.

## Guardrails

- No fake/hash vectors are labeled semantic embeddings.
- No automatic truth, authority, quality, or evidence-strength score is produced from retrieval rank.
- Raw Library chunks and embeddings are not mirrored to Platform Core.
- Non-public/unpublished Library records are not retained in the public semantic vector index.
- Embedding credentials remain backend-only.
- Hybrid search does not require Core to be reachable on every request.

## Compatibility

- Retains v5.12.0 Platform Core Research Bridge and backend v2.23.0 contracts.
- Retains v5.11.0 Private Organizational Knowledge Foundation; private search remains physically separate from the public semantic index.
- Retains v5.10.0 Institutional Research Network II.
- Retains the existing biomedical, Carbon & Nature, Energy Systems, Explorer, Publications, and Research Network surfaces.
