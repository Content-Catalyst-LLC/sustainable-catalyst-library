# Knowledge Library v5.78.0 — Python Background Job & Workflow Consolidation

Python/FastAPI is the authoritative control plane for background research jobs and workflows.

- PostgreSQL remains authoritative for durable research-job state.
- Redis remains non-authoritative wake-up/dispatch infrastructure.
- Existing specialized-worker registration, leasing, heartbeat, quarantine and dead-letter behavior remains intact.
- Existing checkpointed pipeline definitions, runs, stage jobs and checkpoint-resume behavior remains intact.
- The Go ingestion runtime remains an internal sidecar behind Python public API/research semantics authority.
- WordPress remains presentation/API-client compatibility only.

Successful execution is not research truth, source validity, evidence strength or model correctness. No automatic Platform Core promotion is introduced.
