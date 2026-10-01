# Sustainable Catalyst Knowledge Library

Current release: **v5.69.0 — Python Domain Service Migration Foundation**. Backend: **v2.80.0** · Python SDK: **v0.1.0** · JS/TS Client: **v0.1.0** · Library Web: **v1.2.0** · API: **v1.0**.

This release makes Python/FastAPI the default authority for Library domain behavior, introduces a machine-readable domain-authority and migration-plan contract, establishes a checked-in PHP retirement inventory and drift gate, and keeps WordPress as an optional presentation/integration adapter. Legacy PHP domain behavior is retired incrementally after parity and rollback certification rather than rewritten wholesale.

# Sustainable Catalyst Knowledge Library

Current release: **v5.68.0 — Library SDK, Client & Integration Framework**. Backend: **v2.79.0** · Python SDK: **v0.1.0** · JS/TS Client: **v0.1.0** · Library Web: **v1.2.0** · API: **v1.0**.

This release adds first-party Python and JavaScript/TypeScript clients, typed API contracts, capability discovery, bounded transport retries, stable error mapping, signed-write helpers, and adapters for the six Sustainable Catalyst product consumers. WordPress remains optional and non-authoritative.

# Sustainable Catalyst Knowledge Library

Current release: **v5.67.0.2 — WordPress Runtime Boot Integrity Repair**. Backend: **v2.78.0** · Library Web: **v1.2.0** · API: **v1.0**.

This patch permanently loads the runtime-certification adapter before instantiation, aligns backend certification identity with Library v5.67.0.2, and adds deployment-time WordPress boot verification with rollback. It adds no new research capability.

# Sustainable Catalyst Knowledge Library

Current release: **v5.67.0.1 — Backend Release Identity & Deployment Integrity Repair**. Backend: **v2.78.0** · Library Web: **v1.2.0** · API: **v1.0**.

This patch aligns the backend and WordPress release identities, preserves the production `.env` during backend replacement, and certifies against the canonical host endpoint `127.0.0.1:8087`. It adds no new research capability.

# Sustainable Catalyst Knowledge Library

Current release: **v5.66.0**. Backend: **v2.77.0** · Library Web: **v1.2.0** · API: **v1.0**.

# Sustainable Catalyst Knowledge Library

Current release: **v5.65.0 — Direct Cross-Product Library Service Integration**. The authoritative Library API can now be consumed directly by Research Librarian AI, Workspace, Research Lab, Workbench, Decision Studio, and Site Intelligence through machine-readable service contracts and scoped handoff rules.

# Sustainable Catalyst Knowledge Library

Current release: **v5.61.0 — Library Identity, Session & Access Boundary**  
Backend: **v2.74.0** · Library Web: **v1.2.0** · API: **v1.0**

The Library service now owns identity, sessions, roles/scopes, and access decisions. WordPress remains an optional non-authoritative adapter.

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


Current release: v5.63.0 — Public Routing, SEO & Embed Bridge (backend v2.74.0; Library Web v1.2.0).
