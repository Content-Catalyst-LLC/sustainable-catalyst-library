# Knowledge Library v5.43.0 — Publication Embedding Maps & Semantic Knowledge Landscape

## Purpose

v5.43.0 turns the governed embedding representations introduced in v5.40–v5.41 into reproducible visual research objects. It adds publication-level embedding maps, semantic neighborhoods, and a first-class Embedding Map view inside the existing Knowledge Landscape.

## Contracts

- `sc-library-publication-embedding-map/1.0`
- `sc-library-semantic-knowledge-landscape/1.0`
- `sc-library-semantic-neighborhood/1.0`
- `sc-library-deterministic-pca-projection/1.0`

## Representation policy

A map contains one embedding specification only. Current-content representations are grouped by `specification_fingerprint`. The current configured specification is preferred when at least two matching representations exist; otherwise the most common stored current-content specification is selected deterministically. Vector spaces are never mixed in one map.

Stored representations can be mapped while the embedding provider is offline. Rendering does not call an embedding provider and does not queue a backfill.

## Projection

The backend performs a deterministic centered two-component PCA using power iteration over `X^T X` without materializing a full covariance matrix. Projection coordinates are normalized to `[-1,1]` for renderer portability. The map reports the explained energy ratio of each projected axis and the exact projection contract.

The X/Y axes do not have intrinsic scientific domain meaning. Spatial proximity is a projection of semantic representation geometry only.

## Semantic neighborhoods

Each publication receives a bounded same-specification nearest-neighbor list using cosine similarity. Map edges are the symmetric union of top-k neighbors above the selected similarity threshold. Every edge is marked analytical and `truth_assertion=false` and carries the governing specification fingerprint plus source/target representation IDs.

## Knowledge Landscape integration

Corpus responses now contain `publication_embedding_map`. The WordPress Knowledge Landscape adds an `Embedding Map` view. Publication nodes use backend-provided deterministic embedding coordinates and only governed embedding-cosine-similarity relationships are rendered in that view. If fewer than two compatible representations exist, the view is disabled rather than fabricating positions.

## Authority boundary

- Library owns operational vector storage, publication selection, map construction, retrieval, and rendering payloads.
- Platform Core-compatible contracts govern representation/provenance identity.
- Workspace remains the preferred scalable compute path for embedding generation when configured.
- Map proximity, clusters, and semantic neighbors do **not** establish evidence, truth, causality, consensus, or scholarly agreement.
- No map relationship is automatically promoted into Platform Core.
