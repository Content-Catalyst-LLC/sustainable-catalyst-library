# Library Backend Operations & Recovery — v5.5.2

## Purpose

v5.5.2 makes the Python backend observable and repairable without making it authoritative over WordPress editorial state.

## Integrity model

WordPress produces an expected-state manifest containing each published Library record ID and authoritative `source_updated_at` timestamp. The signed backend audit compares that manifest with the `wordpress-main` source in PostgreSQL and classifies differences as:

- **missing** — published in WordPress, absent from backend;
- **stale** — backend source timestamp trails WordPress;
- **orphaned** — present in backend but absent from current published WordPress truth;
- **chunkless** — non-empty backend record unexpectedly has no chunks.

`repair_record_ids` is the union of missing, stale, and chunkless IDs. Orphans remain separate because deletion is destructive.

## Recovery controls

The WordPress Backend Operations page supports:

1. Run integrity audit.
2. Repair the exact missing/stale/chunkless set.
3. Prune only orphan IDs verified by the last audit.
4. Reindex explicit WordPress post IDs.
5. Reindex one Library Collection.
6. Inspect sync checkpoints, last successful sync, recent operations, backend ingest activity, and coverage diagnostics.

## Security boundaries

All `/v1/admin/*` backend operations use the same bearer + timestamp + HMAC signature contract as ingestion. Public browser routes cannot invoke integrity or prune operations.

Pruning is additionally scoped by `source_key` in SQL so a WordPress recovery action cannot delete records belonging to another source.

## Database impact

No schema migration is introduced. Operational state is derived from existing records, chunks, versions, and ingest events. WordPress stores the latest audit and a bounded recovery-operation history in options.
