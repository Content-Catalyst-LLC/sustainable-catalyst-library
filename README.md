# Sustainable Catalyst Knowledge Library

**Current release:** v5.50.0 — Specialized Worker Runtime & Failure Isolation  
**Backend:** v2.61.0  
**Go ingestion runtime:** v0.1.0  
**Rust graph runtime:** v0.2.0

The Knowledge Library is the research publishing, source-intelligence, corpus, retrieval, preservation, and connected-knowledge layer of Sustainable Catalyst.

v5.50.0 activates isolated worker pools for Python research operations, Go ingestion handoff, and Rust graph queries. PostgreSQL remains authoritative for jobs, workers, attempts, provenance, and dead letters; Redis remains rebuildable dispatch coordination. OCR/HTR/speech, neural, and Workspace profiles are declared as explicit standby profiles until their adapters are configured.

See `docs/README.md`, `docs/architecture/library-backend.md`, and `CHANGELOG.md`. Historical release artifacts remain available through Git tags rather than accumulating at the root of `main`.
