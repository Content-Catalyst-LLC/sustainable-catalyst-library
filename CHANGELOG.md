# Changelog

## v5.50.0 — Specialized Worker Runtime & Failure Isolation
- Durable worker profiles/registrations with heartbeats and concurrency limits.
- Active Python research, Go ingestion-handoff, and Rust graph worker pools.
- Capability-aware leasing and worker quarantine.
- Dead-letter records for exhausted/permanent failures.
- PostgreSQL remains authoritative; Redis remains dispatch coordination only.

## v5.49.0 — Durable Research Job Queue & Execution State
- PostgreSQL-authoritative jobs, attempts, retries, leases, progress, cancellation, and events.
- Redis Streams for rebuildable dispatch/wake-up coordination.

For earlier releases, inspect the corresponding Git tag.
