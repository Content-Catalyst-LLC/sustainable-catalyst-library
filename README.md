# Sustainable Catalyst Knowledge Library

The Sustainable Catalyst Knowledge Library is the research publishing, source-intelligence, corpus, retrieval, preservation, and connected-knowledge layer of the Sustainable Catalyst platform.

**Current release:** v5.49.0 — Durable Research Job Queue & Execution State  
**Backend:** v2.60.0  
**Go ingestion runtime:** v0.1.0  
**Rust graph runtime:** v0.2.0

## Architecture

The Library uses a multi-runtime research-data architecture:

- **FastAPI / Python** — research semantics, APIs, orchestration, ingestion logic, NLP, source adapters, semantic workflows, and Platform Core integration.
- **PostgreSQL** — authoritative metadata, provenance, corpus, entity-resolution, and durable execution state.
- **Redis** — rebuildable job dispatch and wake-up coordination; it is not authoritative job storage.
- **Go** — ingestion and network-oriented runtime services.
- **Rust** — selective high-performance deterministic graph and data operations.
- **WordPress** — public and institutional Library interface.
- **Platform Core** — governed research-object and cross-product contracts.

The architectural boundary is: **Library retrieves and processes knowledge; Platform Core defines governed research meaning and exchange.**

## Repository layout

- `library-backend/` — FastAPI backend, PostgreSQL schema, Go ingestion runtime, Rust graph runtime, and deployment configuration.
- `sustainable-catalyst-library/` — WordPress plugin.
- `docs/` — current schemas, API material, examples, and living architecture documentation.
- `tests/` — active validation and regression tests.
- `tools/` — repository tooling.
- `data/` — tracked research/configuration data used by the Library.

See [`docs/README.md`](docs/README.md) for the documentation index and [`CHANGELOG.md`](CHANGELOG.md) for recent release history.

## Release history

Historical release notes, deployment guides, generated validation reports, installers, and page snapshots are intentionally not retained at the root of `main`. Git tags preserve the exact source and release documentation for each historical release.

For example:

```bash
git show v5.45.0:RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.45.0.md
git show v5.49.0:DURABLE_RESEARCH_JOB_QUEUE_EXECUTION_STATE_v5.49.0.md
```

Generated release artifacts belong in release bundles or ignored staging directories rather than the source-tree root.
