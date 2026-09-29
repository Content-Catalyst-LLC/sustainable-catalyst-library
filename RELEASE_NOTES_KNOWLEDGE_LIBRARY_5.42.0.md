# Release Notes — Sustainable Catalyst Knowledge Library v5.42.0

**Title:** Neural Reranking & Retrieval Evaluation  
**Backend:** v2.53.0  
**Baseline:** v5.41.0 / backend v2.52.0, cumulative with v5.40.0.1 repair

## Added
- Provider-neutral `rerank_compatible` neural reranking client.
- Deterministic reranker specification and SHA-256 fingerprint.
- Baseline-rank, baseline-score, candidate-text-hash, provider/model, neural-score, neural-rank, and rank-delta lineage.
- Provider-disabled/error baseline fallback with no fabricated neural score.
- Baseline-vs-reranked evaluation using the existing v5.28 judged retrieval metrics.
- Neural-reranked search endpoint layered after v5.41 lexical/semantic/hybrid candidate generation.
- WordPress proxy routes and JSON schemas for all v5.42 contracts.

## Preserved
- v5.41 current-content/current-specification semantic candidate rules.
- Provider-independent stored-record similarity.
- v5.40 embedding governance and Workspace compute handoff.
- v5.40.0.1 backfill timestamp repair.
- Go ingestion runtime v0.1.0 and Rust graph runtime v0.2.0.

## Guardrails
Reranking is a retrieval operation only. A provider relevance score is not a probability, evidence grade, truth score, causal conclusion, or Platform Core promotion. Candidate records are not automatically removed.
