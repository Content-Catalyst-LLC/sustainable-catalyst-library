# Release Notes — Knowledge Library v5.40.0.1

**Backend:** v2.51.1  
**Release type:** targeted production repair

v5.40.0.1 repairs the signed embedding-backfill dry-run/queue endpoint introduced in v5.40.0. The candidate query referenced `library_records.updated_at`, but the canonical Library record table uses `source_updated_at`, `indexed_at`, and `created_at`. PostgreSQL therefore raised an undefined-column error and FastAPI returned HTTP 500.

The repaired query orders deterministically by:

`COALESCE(source_updated_at, indexed_at, created_at), record_id`

No vector data, embedding specification, governance contract, Workspace handoff contract, schema table, or research authority rule changes in this repair. Backfill remains dry-run by default and no embedding quota is consumed during deployment verification.
