# Knowledge Library v5.42.0 — Neural Reranking & Retrieval Evaluation

## Purpose
v5.42.0 adds a governed second-stage neural reranking layer on top of v5.41.0 Semantic Similarity & Representation Search. The Library continues to retrieve candidates through lexical, semantic, or hybrid retrieval first. A configured reranker may then reorder a bounded candidate set while preserving the original rank, original score, candidate identity, and retrieval lineage.

## Backend
- Knowledge Library backend: **v2.53.0**
- Go ingestion runtime: **v0.1.0** (preserved)
- Rust graph runtime: **v0.2.0** (preserved)

## New contracts
- `sc-library-neural-reranker-specification/1.0`
- `sc-library-neural-reranking/1.0`
- `sc-library-neural-reranking-evaluation/1.0`

## Reranker specification
The reranker specification records provider, model, API contract, candidate-text contract, maximum candidate count, and a deterministic SHA-256 fingerprint. Provider configuration is explicit and disabled by default.

`rerank_compatible` expects a query/documents API shape:

```json
{
  "model": "...",
  "query": "...",
  "documents": ["..."],
  "top_n": 20,
  "return_documents": false
}
```

with response scores shaped as:

```json
{"results":[{"index":0,"relevance_score":0.87}]}
```

No fake or hash-based reranking score is generated when the provider is disabled.

## Candidate lineage
Every reranked result preserves:
- baseline rank;
- baseline score when present;
- candidate-text SHA-256;
- reranker specification fingerprint;
- provider and model identity;
- provider relevance score;
- neural rank and rank delta.

Unscored candidates remain in the result set. Provider errors fail open to the baseline order.

## Retrieval evaluation integration
v5.42 reuses the v5.28 retrieval-evaluation metrics rather than creating a second quality system. Explicit researcher judgments may be applied to baseline and reranked lists, producing baseline and reranked metrics plus transparent deltas.

A metric improvement is a retrieval-quality observation for the judged case. It is not validation of source truth, evidence validity, or a scientific claim.

## New backend routes
- `GET /v1/neural-reranking/readiness`
- `POST /v1/neural-reranking/rerank`
- `POST /v1/neural-reranking/evaluate`
- `POST /v1/search/neural-reranked`

WordPress exposes matching backend proxy routes under the Library REST namespace.

## Configuration
```dotenv
SC_LIBRARY_RERANK_PROVIDER=disabled
SC_LIBRARY_RERANK_API_KEY=
SC_LIBRARY_RERANK_MODEL=rerank-model
SC_LIBRARY_RERANK_API_URL=
SC_LIBRARY_RERANK_TIMEOUT_SECONDS=12
SC_LIBRARY_RERANK_MAX_CANDIDATES=40
```

The production-safe default is `disabled`. The normal v5.41 retrieval stack remains usable without a reranking provider.

## Non-negotiable guardrails
- neural relevance score **is not a probability**;
- neural relevance score **is not evidence**;
- neural relevance score **is not truth**;
- reranking does not alter claims, findings, evidence relations, or Platform Core objects;
- reranking does not automatically filter or delete candidates;
- baseline rank and result identity remain visible;
- retrieval quality claims require explicit judgments;
- a better retrieval metric does not validate the scientific content of a result.

## Preserved chain
v5.42.0 includes and preserves the cumulative v5.40.0.1 backfill timestamp repair and v5.41.0 current-content/current-specification semantic similarity safeguards.
