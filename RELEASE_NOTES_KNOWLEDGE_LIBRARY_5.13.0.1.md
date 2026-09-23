# Sustainable Catalyst Knowledge Library v5.13.0.1

## Core-Aware Search Runtime Repair

v5.13.0.1 is a backend-only corrective release for v5.13.0. The v2.24.0 deployment successfully initialized Hybrid Research Retrieval and Platform Core readiness, but the first live `/v1/search?include_core=true` smoke test returned HTTP 500 because `app/hybrid_retrieval.py` used `collections.defaultdict` without importing it.

### Repair
- imports `defaultdict` from `collections` in the hybrid retrieval runtime
- advances the Python backend to v2.24.1
- adds executable regression coverage for `_core_bindings()` and `enrich_with_core_bindings()`
- preserves all v5.13.0 schemas, database tables, embedding queues, Platform Core contracts, and WordPress code
- requires no database migration and no WordPress reinstall

### Acceptance
The release is accepted when backend health reports `2.24.1`, `/v1/search/readiness` remains healthy, Platform Core readiness remains 8/8, and the live hybrid search smoke test returns schema `sc-library-hybrid-retrieval/1.0` with Core enrichment enabled.
