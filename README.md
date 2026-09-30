# Sustainable Catalyst Knowledge Library

**Current release:** v5.52.0 — Checkpointed Ingestion & Research Pipeline Engine  
**Backend:** v2.63.0  
**Go ingestion runtime:** v0.1.0  
**Rust graph runtime:** v0.2.0

The Knowledge Library is the research publishing, source-intelligence, corpus, retrieval, preservation, and connected-knowledge layer of Sustainable Catalyst.

v5.51.0 adds immutable content-addressed research artifacts backed by SHA-256 identity, PostgreSQL-authoritative metadata/provenance, derivation lineage, lifecycle state, a shared filesystem object store, and an S3-compatible adapter. Heavyweight bytes no longer need to live in PostgreSQL or WordPress. Artifact presence or successful integrity verification does not establish source validity or evidence truth.

The v5.49 durable job fabric and v5.50 specialized worker layer remain active; the Python research worker can persist and verify artifact objects through the same governed execution fabric.

See `docs/README.md`, `docs/architecture/library-backend.md`, and `CHANGELOG.md`. Historical release artifacts remain available through Git tags rather than accumulating at the root of `main`.
