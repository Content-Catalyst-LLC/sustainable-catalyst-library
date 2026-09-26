# Sustainable Catalyst Knowledge Library v5.28.0

**Release:** Research Retrieval Evaluation & Adaptive Ranking  
**WordPress:** 5.28.0  
**Python backend:** 2.39.0

## Added
- deterministic retrieval evaluation and benchmark contracts;
- precision/recall-style cutoff metrics, nDCG, MRR, MAP contribution, evidence coverage and source-diversity diagnostics;
- explicit 0–3 relevance judgments without treating unjudged records as negatives;
- bounded adaptive-ranking profiles derived from judged retrieval outcomes;
- transparent adaptive reranking preserving original rank and score components;
- POST `/v1/search/adaptive` for controlled comparison against baseline hybrid retrieval;
- WordPress REST proxies for evaluation, profile generation, reranking and adaptive search;
- `[sc_library_retrieval_evaluation]` interactive evaluation console;
- JSON schemas for evaluation and ranking profiles.

## Guardrails
Adaptive ranking is rerank-only: no automatic result filtering, deletion, evidence mutation, claim promotion, or truth promotion. User acceptance is relevance feedback, not a factual truth label.

## Preservation
The release preserves v5.27 source identity, v5.26 scientific-document intelligence, v5.25 graph/pathfinding, v5.24 synthesis, hybrid retrieval and Platform Core governance boundaries.
