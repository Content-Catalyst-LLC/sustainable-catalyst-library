# Sustainable Catalyst Knowledge Library v5.43.0

**Release:** Publication Embedding Maps & Semantic Knowledge Landscape  
**Backend:** 2.54.0  
**Go ingestion runtime:** 0.1.0  
**Rust graph runtime:** 0.2.0

## Added

- Governed publication embedding maps from current-content stored representations.
- Deterministic two-dimensional centered PCA projection with reproducible coordinates.
- One-specification-per-map enforcement with configured-specification preference and deterministic stored-spec fallback.
- Traceable semantic neighborhoods and sparse top-k cosine-similarity map edges.
- Representation ID, content hash, execution lineage, model/provider, dimensionality, and specification fingerprint on map points.
- Source/target representation IDs and specification fingerprint on semantic map edges.
- New backend readiness, map-build, and publication-neighborhood endpoints.
- WordPress REST proxy routes for semantic map workflows.
- First-class **Embedding Map** view in the Knowledge Landscape.
- Corpus Knowledge Landscape responses now expose `publication_embedding_map`.

## Preserved guardrails

Semantic proximity is an analytical retrieval/visualization signal. It is not evidence, truth, causality, consensus, agreement, a probability, or an automatic Platform Core relationship.

## Upgrade behavior

No embedding backfill is queued and no external embedding or reranking request is made by the installer. Existing v5.42 reranking and v5.41 semantic retrieval behavior are preserved.
