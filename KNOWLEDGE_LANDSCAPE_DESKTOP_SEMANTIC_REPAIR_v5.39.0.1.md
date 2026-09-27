# Knowledge Landscape Desktop Layout & Semantic Availability Repair

## Problem 1 — desktop graph height
The Knowledge Landscape workspace used `min-height` only. In the three-column desktop grid, long control/inspector sidebars could therefore determine the grid-row height and stretch the central visualization far beyond the intended 680px viewport.

### Repair
At desktop widths (`min-width: 1101px`):
- `.sc-kl__workspace` is fixed to `var(--sc-kl-height)` and no longer grows with sidebar content.
- `.sc-kl__panel` uses `overflow-y:auto`.
- `.sc-kl__stage-wrap` is bounded and clips overflow.
- `.sc-kl__stage` remains the flexible visualization region between toolbar and metrics.

At `max-width:1100px`, height returns to `auto` and panels return to visible overflow so tablet/mobile behavior is preserved.

## Problem 2 — unexplained Semantic Overlay availability
Semantic edges are intentionally available only when at least two current stored publication embeddings exist. Current means the embedding `content_hash` matches the current Library record `content_hash`.

### Repair
The corpus response now reports:
- `vector_count` / `current_embedding_count`
- `missing_current_embedding_count`
- `stale_embedding_count`
- `embedding_job_counts`
- `provider_configured`
- `worker_enabled`
- `reason`
- `message`

Possible unavailability reasons include:
- `insufficient-publications`
- `embedding-provider-not-configured`
- `embedding-jobs-pending`
- `stale-embeddings-require-refresh`
- `insufficient-current-embeddings`

The Semantic Overlay tab remains clickable so researchers can inspect its status. The semantic relationship checkbox stays unavailable until real semantic edges can exist.

## Interpretation boundary
No fake/hash embeddings are introduced. Semantic edges continue to require real stored embeddings, matching provider/model/dimensions, cosine similarity, and the configured threshold. Similarity remains an analytical relationship, not a factual, causal or evidentiary claim.
