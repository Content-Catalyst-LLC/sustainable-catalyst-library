# Knowledge Library v5.36.0.1 — Go Research Ingestion & Job Fabric

## Purpose
v5.36.0.1 adds a dedicated Go execution fabric beneath the Python research backend. The fabric coordinates concurrent ingestion work without taking ownership of research meaning, evidence quality, claim truth, or Platform Core governance.

## Runtime architecture

WordPress → Python backend v2.47.1 → Go ingestion runtime v0.1.0 → connector/OCR/parser/extraction/index workers

Rust graph runtime v0.2.0 remains a separate structural graph-compute layer.

## Contract
`sc-library-go-ingestion-runtime/1.0`

## Supported job classes
- connector-fetch
- pdf-ingest
- ocr
- document-parse
- metadata-extract
- citation-parse
- scientific-object-extract
- identity-resolution
- embedding-index
- search-index

## Job lifecycle
`queued → running → completed`

Retryable failure uses `running → retry_wait → running`. Terminal alternatives are `failed` and `cancelled`.

The Go coordinator provides idempotency keys, priority ordering, maximum-attempt policy, bounded in-flight work, queue backpressure, cancellation, worker claim/ownership, completion/failure acknowledgements, and worker-health/runtime status.

## Durability
The runtime persists queue metadata to `/data/jobs.json` on a dedicated Docker volume. Jobs that were `running` when the coordinator restarts are returned to `retry_wait`, preserving operational continuity without pretending the interrupted execution succeeded.

## Security
The Go service is internal-only on the `sc-internal` Docker network. Public job submission/read/cancel surfaces are exposed through the Python backend and require the existing signed backend credential contract. WordPress proxies those operations server-to-server.

## Research integrity boundary
- queued does not mean a source is verified
- completed does not mean a source is valid
- completed does not mean extracted evidence is true
- retries do not alter scholarly meaning
- queue priority is not scientific priority
- worker success does not promote Platform Core objects
- Python validates worker results before research interpretation
- Platform Core remains the durable authority for governed research objects

## Production verification
The v2.47.1 installer performs a no-cache Docker build of both the Rust and Go runtimes, waits for both services to become healthy, validates the Go contract/version, executes a submit/claim/fail/retry/claim/complete lifecycle, confirms durable state is enabled, verifies Rust v0.2.0 continuity, and checks Platform Core readiness.
