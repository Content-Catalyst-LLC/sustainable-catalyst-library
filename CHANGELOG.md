# Changelog

## 5.66.0 — Independent Library Release & Deployment Engineering
- Added backend v2.77.0 release-engineering contracts, manifest validation, deployment planning, preflight, rollback, and certification.
- Added durable release manifest, deployment plan/event, and certification tables.
- Added stable API v1 release-engineering surfaces and a read-only WordPress release status adapter.
- Added Library-owned production deployment orchestration with artifact hashing, predecessor checks, snapshots, backend-first ordering, and rollback metadata.

## 5.65.0 — Library State Migration & WordPress Data Retirement
- Adds signed, hash-verified migration manifests for legacy WordPress-held Library research state.
- Adds PostgreSQL-authoritative migration runs, imported state items, migration events, and retirement certifications.
- Excludes credentials, sessions, users, secrets, caches, search indexes, and WordPress editorial/publication content from migration.
- Adds WordPress WP-CLI inventory/export/retirement commands and a read-only status surface.
- Retirement requires a backend certification and retained rollback copy; automatic or destructive WordPress data deletion is prohibited.
- Certified retirement disables legacy WordPress research-state surfaces while preserving publishing, routing, SEO, embeds, and thin-adapter functions.

## 5.64.0 — Direct Cross-Product Library Service Integration
- Added six first-class direct Library service consumer contracts.
- Added cross-product readiness, product manifest, signed exchange validation, and admin binding APIs.
- Added non-secret service binding persistence and event lineage.
- Added service-principal/scoped-access expectations and product handoff contracts.
- Preserved Library research-state authority, WordPress independence, and Platform Core governed-meaning boundary.

## v5.63.0 — Public Routing, SEO & Embed Bridge
- Backend v2.74.0 publishes a canonical public routing contract, route-level indexing policy, record SEO descriptors, and sandboxed embed descriptors.
- Library Web v1.2.0 moves from hash-only navigation to clean public paths while preserving the direct `/api/library/v1` backend path.
- Default canonical public origin is `https://library.sustainablecatalyst.com`, configurable through `SC_LIBRARY_PUBLIC_ORIGIN`.
- WordPress adds a read-only public bridge with launch and embed shortcodes; it remains non-authoritative and is not a proxy requirement.

## v5.60.0 — Independent Library Web Application Foundation

- Adds standalone `library-web` v1.0.0 with Search, Reader, Discover and System surfaces.
- Adds direct same-origin `/api/library/v1` proxying to the authoritative backend.
- Adds web-application contract/readiness endpoints to backend v2.71.0.
- Keeps WordPress as an optional launch/embed adapter rather than application runtime.

# Changelog

## v5.59.0 — Independent Library API v1 & Service Contract

- Publishes the stable `/api/library/v1` WordPress-independent service boundary.
- Adds versioned service metadata, readiness, capability/route discovery, search, records, stats, and signed durable research-job submission.
- Defines stable error and pagination envelopes plus API compatibility rules.
- Adds deterministic service-contract fingerprinting and optional signed contract publication lineage.
- Keeps legacy `/v1/*` routes as compatibility/internal surfaces rather than silently treating them as the new public contract.
- Preserves v5.58 runtime authority: WordPress is a client adapter and is not required for API v1 execution.

## v5.58.0 — Library Runtime Authority & WordPress Decoupling Foundation

- Makes the Library API/Python/PostgreSQL service boundary authoritative for research execution and state.
- Formalizes WordPress as a non-authoritative publishing, routing, SEO, embed, authentication-handoff, and compatibility adapter.
- Adds deterministic runtime-authority certification, ownership map, client-adapter registry, and dependency graph with zero WordPress dependencies.
- Adds the permanent rule that no new Knowledge Library research capability may require WordPress to execute.
- Adds `architecture.certify.runtime-authority` to the active Python research worker.

## v5.57.0 — Global Knowledge Federation

- Certifies 13 federation, language, provenance, trust, artifact, pipeline, and compute layers as one interoperable Knowledge Library milestone.
- Preserves original-language canonicality, transformation lineage, source-quality/trust separation, and Platform Core governance boundaries.
- Adds deterministic federation certifications and append-only certification events.
- Adds active `federation.certify.global` Python research worker capability.

## v5.56.0 — Source Transparency, Quality Signals & User Trust Policies

- Adds provenance-backed descriptive source-quality signals and source-transparency profiles.
- Keeps user trust policies owner-scoped and separate from source signals.
- Adds stateless trust-policy evaluation without rewriting source history or quality signals.
- Prohibits aggregate credibility/trust scores, automatic source endorsement, evidence weighting, and Platform Core promotion.
- Adds `source.transparency.assess` to the active Python research worker.

## v5.55.0 — Cross-Civilizational Evidence & Scientific Data Linking

- Added provenance-bound links across textual/historical evidence and scientific datasets.
- Preserves language, chronology, geography, methods, units, uncertainty, provenance, and interpretation limits.
- Added active `knowledge.link.cross-civilizational` Python research worker capability.

## v5.54.0 — Translation & Transliteration Alignment Matrix

- Adds source/target text-representation alignment matrices bound to exact SHA-256 text identity.
- Supports 1:1, 1:N, N:1, N:M, omitted, added, partial, and uncertain alignment relations.
- Preserves BCP 47 language, ISO 15924 script, transformation identity, alignment method/engine, confidence signal, review state, and provenance.
- Adds signed persistence and a `language.align` Python worker capability through the durable compute fabric.
- Translation and transliteration remain derived representations; alignment never generates or canonically replaces source-language text.

## v5.53.0 — Distributed Research Compute Broker & Runtime Observability

- Adds a capability catalog spanning active and standby specialized runtime profiles.
- Adds deterministic operational placement based on compatibility, worker freshness, concurrency, queue pressure, and configured limits.
- Adds global queue/running limits and per-capability queue backpressure without creating a second scheduler.
- Adds worker-class affinity for brokered durable jobs while preserving backward compatibility for ordinary jobs.
- Adds descriptive runtime health, recent completion/failure, p50 latency, available-slot, and queue observability.
- Adds PostgreSQL placement-event lineage and optional persisted runtime observations.
- Keeps runtime metrics separate from research quality, evidence validity, truth, and Platform Core governance.

## v5.52.0 — Checkpointed Ingestion & Research Pipeline Engine

- Adds PostgreSQL-authoritative pipeline definitions, runs, stage runs, and pipeline event lineage.
- Validates acyclic stage DAGs and compiles ready stages into the existing durable research-job fabric.
- Adds deterministic stage-job idempotency, stage checkpoints, dependency checkpoint reuse, and resume-after-failure.
- Preserves v5.51 content-addressed artifact identities as pipeline stage outputs.
- Keeps completed checkpoints immutable by default and does not infer evidence truth from pipeline completion.

## v5.51.0 — Research Artifact & Object Storage Fabric
- Immutable SHA-256 content-addressed research artifact identities and storage keys.
- PostgreSQL-authoritative artifact metadata, provenance, lifecycle, derivation links, and integrity events.
- Shared filesystem object-store volume as the safe production baseline.
- S3-compatible adapter contract for MinIO, R2, AWS S3, and compatible providers without changing artifact identity.
- Signed persistence, lifecycle, and integrity-verification endpoints; raw artifact access remains admin/backend-only.
- Python worker integration for artifact persistence and verification.

## v5.50.0 — Specialized Worker Runtime & Failure Isolation
- Durable worker profiles/registrations with heartbeats and concurrency limits.
- Active Python research, Go ingestion-handoff, and Rust graph worker pools.
- Capability-aware leasing and worker quarantine.
- Dead-letter records for exhausted/permanent failures.
- PostgreSQL remains authoritative; Redis remains dispatch coordination only.

## v5.49.0 — Durable Research Job Queue & Execution State
- PostgreSQL-authoritative jobs, attempts, retries, leases, progress, cancellation, and events.
- Redis Streams for rebuildable dispatch/wake-up coordination.

For earlier releases, inspect the corresponding Git tag.

## v5.61.0.1 — Library Web Port Allocation & Deployment Collision Repair
- Removed the fixed `127.0.0.1:8091` Library Web host-port assumption.
- Added `SC_LIBRARY_WEB_BIND_PORT` to the Library Web Compose contract.
- Production deployment now selects the first free localhost port from 8092-8099 unless `SC_LIBRARY_WEB_PORT` is explicitly supplied.
- The deployer refuses to remove or replace non-Library containers that own a requested port.
- Backend v2.72.0, Library Web application v1.1.0, API v1, and WordPress adapter v5.61.0 remain unchanged.


## v5.62.0 — WordPress Thin Adapter
- Adds the explicit WordPress thin-adapter allowlist/denylist and API v1 contract.
- Limits WordPress authority to presentation/routing/SEO/embed/health/optional identity-handoff roles.
- Keeps research state, execution, jobs, artifacts, pipelines, compute, identity/session authority, federation, trust policy, and Platform Core promotion outside WordPress.
- Adds `/api/library/v1/wordpress-adapter` and `/api/library/v1/wordpress-adapter/readiness`.
- Preserves legacy WordPress presentation modules as non-authoritative compatibility surfaces while the independent Library Web application continues to call API v1 directly.
