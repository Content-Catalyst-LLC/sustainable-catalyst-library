# Release Notes — Knowledge Library v5.49.0

**Durable Research Job Queue & Execution State** · backend **2.60.0**

- Adds PostgreSQL-authoritative job, attempt, lease, retry, progress, cancellation, and event records.
- Adds Redis 7.4 Streams as rebuildable dispatch coordination.
- Adds deterministic idempotent job packages and signed administrative job/worker APIs.
- Adds lease heartbeat and expired-lease recovery.
- Extends the existing Go ingestion-fabric/runtime lineage rather than creating a replacement scheduler.
- Adds read-only WordPress execution-fabric readiness.
- Does not activate specialized worker pools; that belongs to v5.50.0.
- Does not change Platform Core governance or infer truth from job completion.
