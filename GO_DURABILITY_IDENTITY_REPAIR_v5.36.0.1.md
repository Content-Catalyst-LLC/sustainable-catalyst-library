# Knowledge Library v5.36.0.1 — Go Durability Identity Repair

This patch repairs the runtime identity exposed by the Go Research Ingestion & Job Fabric.

## Problem
v5.36.0 implemented state-file persistence and mounted `/data/jobs.json`, but `/health` retained the earlier `process-memory-foundation` label. The production verifier correctly rejected that mismatch.

## Repair
The Go health contract now reports:
- `state-file` when `SC_LIBRARY_GO_STATE_FILE` is configured.
- `process-memory-foundation` when no state file is configured.

The deployment verifier continues to require `state-file` in production. Queue persistence, restart recovery, idempotency, retry, cancellation, backpressure, Python research semantics, Rust graph compute, and Platform Core governance are unchanged.
