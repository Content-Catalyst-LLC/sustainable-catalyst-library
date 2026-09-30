# Sustainable Catalyst Knowledge Library

**Current release:** v5.60.0 — Independent Library Web Application Foundation  
**Backend:** v2.71.0  
**Web:** v1.0.0  
**Go ingestion runtime:** v0.1.0  
**Rust graph runtime:** v0.2.0

The Knowledge Library is the research publishing, source-intelligence, corpus, retrieval, preservation, and connected-knowledge layer of Sustainable Catalyst.

## v5.59 Independent Library API v1

The Library now publishes a stable WordPress-independent service contract at `/api/library/v1`. The contract owns service metadata, health/readiness, capability discovery, route discovery, search/record reads, stable pagination/error envelopes, and signed durable research-job submission. Existing `/v1/*` routes remain compatibility/internal surfaces and are not the API v1 stability boundary.

**Compatibility rule:** breaking API changes require a new major API version. Additive fields may be introduced within v1, and clients must ignore unknown fields. WordPress is not required to execute API v1.

See `docs/library-api-v1-openapi.json` and `docs/architecture/library-api-v1.md`.

## v5.58 runtime authority

The Knowledge Library is now architecturally defined as an independent research service. The Library API, Python backend, PostgreSQL state, retrieval/indexes, jobs, workers, artifact storage, pipelines, compute broker, native runtimes, federation, and Platform Core contracts form the authoritative runtime. WordPress is a non-authoritative client adapter for publishing, routing, SEO, embeds, authentication handoff, and public-site integration.

**Permanent rule:** no new Knowledge Library research capability may require WordPress to execute.

See `docs/architecture/library-runtime-authority.md`.

v5.53.0 adds a distributed compute broker above the durable v5.49 job fabric, v5.50 worker fleet, v5.51 artifact store, and v5.52 checkpointed pipeline engine. It performs capability discovery, deterministic operational placement, worker-class affinity, queue admission/backpressure, configurable running/queue limits, and descriptive runtime observability. Runtime health, latency, throughput, or placement never imply research quality, evidence validity, or result truth.

The broker does not replace PostgreSQL, Redis, the specialized workers, or the pipeline engine: PostgreSQL remains authoritative for durable job state; Redis remains dispatch coordination; workers execute; pipelines own DAG/checkpoint semantics; Platform Core remains the governed research-object layer.

See `docs/README.md`, `docs/architecture/library-backend.md`, and `CHANGELOG.md`. Historical release artifacts remain available through Git tags rather than accumulating at the root of `main`.


v5.54.0 adds governed alignment matrices between preserved original-language text representations and derived translations/transliterations. Alignment binds exact source/target text hashes and character spans, supports 1:1, 1:N, N:1, N:M, added/omitted/uncertain relationships, and preserves transformation provenance and explicit review state. The Library never generates a translation or transliteration merely to satisfy an alignment request.

## Independent web application

`library-web/` is the first standalone Knowledge Library client. It calls `/api/library/v1` directly and does not require WordPress.
