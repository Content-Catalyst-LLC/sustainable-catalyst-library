# Knowledge Library v5.73.0 — Python Retrieval & Search Orchestration

Python/FastAPI is the authoritative Library retrieval coordinator. The orchestrator does not replace the proven lexical, semantic, hybrid, neural-reranking, adaptive-ranking, facet, or Platform Core enrichment components; it composes them behind one stable decision boundary.

## Authority

- Query normalization and retrieval planning: Python.
- Lexical, hybrid and semantic candidate generation: Python/PostgreSQL and the existing embedding runtime.
- Optional neural reranking: Python, with baseline fallback.
- Optional adaptive reranking: Python, only with an explicit ranking profile.
- Pagination and result envelopes: Python.
- WordPress: query UI, presentation and API-client compatibility only.

## Guardrails

Ranking scores are not truth probabilities. Semantic similarity does not imply evidence equivalence. Neural-reranking failure falls back to baseline ranking. Adaptive ranking cannot change truth status or evidence relations. Platform Core enrichment is descriptive and never an automatic promotion.

The stable `/api/library/v1/search` and legacy `/v1/search` routes converge on this orchestrator in v5.73.0.
