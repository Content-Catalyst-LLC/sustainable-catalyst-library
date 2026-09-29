# Embedding Backfill Timestamp Repair — v5.40.0.1

## Defect

The v5.40.0 backfill planner used `ORDER BY r.updated_at`, but `library_records` does not define `updated_at`. This affected `/v1/admin/embeddings/backfill` after authorization and caused HTTP 500 before candidate results could be returned.

## Repair

Use the record timestamps already defined by the Library schema:

`COALESCE(r.source_updated_at, r.indexed_at, r.created_at) ASC, r.record_id ASC`

This preserves deterministic ordering while preferring upstream source modification time, then Library index time, then record creation time.

## Unchanged guardrails

- Backfill remains dry-run by default.
- Existing vectors are not deleted or recomputed automatically.
- Embeddings are analytical representations, not evidence or truth.
- Semantic similarity is not causality or consensus.
- Workspace execution does not automatically promote objects into Platform Core.
