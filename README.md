# Sustainable Catalyst Knowledge Library

**Current release:** v5.53.0 — Distributed Research Compute Broker & Runtime Observability  
**Backend:** v2.64.0  
**Go ingestion runtime:** v0.1.0  
**Rust graph runtime:** v0.2.0

The Knowledge Library is the research publishing, source-intelligence, corpus, retrieval, preservation, and connected-knowledge layer of Sustainable Catalyst.

v5.53.0 adds a distributed compute broker above the durable v5.49 job fabric, v5.50 worker fleet, v5.51 artifact store, and v5.52 checkpointed pipeline engine. It performs capability discovery, deterministic operational placement, worker-class affinity, queue admission/backpressure, configurable running/queue limits, and descriptive runtime observability. Runtime health, latency, throughput, or placement never imply research quality, evidence validity, or result truth.

The broker does not replace PostgreSQL, Redis, the specialized workers, or the pipeline engine: PostgreSQL remains authoritative for durable job state; Redis remains dispatch coordination; workers execute; pipelines own DAG/checkpoint semantics; Platform Core remains the governed research-object layer.

See `docs/README.md`, `docs/architecture/library-backend.md`, and `CHANGELOG.md`. Historical release artifacts remain available through Git tags rather than accumulating at the root of `main`.
