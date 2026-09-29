# Release Notes — Knowledge Library v5.41.0

## Semantic Similarity & Representation Search

v5.41.0 converts governed embedding representations into an explicit retrieval surface.

### Added

- specification-aware semantic query search;
- record-to-record similarity using stored vectors;
- semantic representation descriptors and lineage;
- current-content filtering for all semantic candidates;
- current-specification filtering for text-query retrieval;
- seed-specification filtering for record similarity;
- configurable `SC_LIBRARY_SEMANTIC_MIN_SIMILARITY` threshold;
- embedding specification/representation lookup indexes;
- semantic readiness and search endpoints;
- WordPress REST proxy routes for the new surfaces;
- hybrid-search result metadata showing semantic provenance and guardrails.

### Preserved

- weighted reciprocal-rank fusion for hybrid retrieval;
- lexical fallback when query embedding compute is unavailable;
- v5.40 embedding governance and Workspace handoff architecture;
- v5.40.0.1 backfill timestamp repair;
- existing vector storage and content hashes;
- Go ingestion and Rust graph runtimes.

### Guardrails

Similarity remains a retrieval signal only. It does not become evidence, truth, causality, a scholarly relationship, or an automatically promoted Platform Core object.
