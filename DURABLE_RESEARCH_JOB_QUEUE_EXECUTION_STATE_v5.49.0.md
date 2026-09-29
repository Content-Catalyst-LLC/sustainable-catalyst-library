# Knowledge Library v5.49.0 — Durable Research Job Queue & Execution State

Backend: **v2.60.0**

## Purpose

v5.49.0 is the first Library backend-infrastructure release. It evolves the existing v5.36 Go Research Ingestion & Job Fabric and v5.38 Unified Research Runtime Contract into a Library-wide durable execution-state model. It does not replace FastAPI, PostgreSQL, Go, Rust, or Platform Core.

## Authority model

- **PostgreSQL is authoritative** for jobs, attempts, leases, retry state, progress, cancellation, failure history, outputs, and provenance.
- **Redis Streams is dispatch/wake-up coordination only.** Redis can be rebuilt without deleting a durable research job.
- **FastAPI remains orchestration and research-semantics authority.**
- **Go 0.1.0 and Rust 0.2.0 remain intact.**
- Specialized worker pools are deliberately deferred to **v5.50.0**.

## Durable objects

`library_research_jobs` stores one idempotent durable job record. `library_research_job_attempts` stores each leased worker attempt. `library_research_job_events` provides append-only operational lineage.

## State machine

`queued → leased → running → complete`

Failure can become `retry` until `max_attempts` is exhausted, then `failed`. Cancellation is explicit. Expired leases are recoverable through a signed administrative operation.

## Redis degradation

A failed Redis dispatch returns `deferred`; it does not roll back or delete the PostgreSQL job. Workers introduced in v5.50 can recover queued/retry work directly from PostgreSQL even when a wake-up notification was missed.

## Scientific/governance boundary

Job completion means only that the declared execution operation completed. It does not establish source validity, evidence truth, model correctness, scholarly agreement, or automatic Platform Core promotion.
