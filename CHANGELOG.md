## 6.15.0 — Research Timeline & Historical Event Workspace

- Added dedicated `/research/archives/timeline` workspace.
- Added historical event objects with source-linked assertions, explicit event keys, uncertain/range dates, and human-asserted event relationships.
- Added uncertainty-preserving timeline construction, event/source coverage matrices, and comparison of competing chronologies.
- Cross-chronology alignment requires explicit shared event keys; no fuzzy event merging, winner selection, causal inference, or false date precision is introduced.
- Synchronized Library 6.15.0 / backend 3.15.0 / web 2.15.0 / SDK 1.15.0 and corrected top-level health generation markers.
- No database migration; WordPress remains optional.

## 6.14.0 — Primary-Source Comparison & Source Criticism Workspace

- Added dedicated `/research/archives/compare` workspace.
- Added ten-dimension non-scoring source criticism, comparison matrices, explicit source relationships, and claim/source corroboration ledgers.
- Preserves disagreement, ambiguity, mediation, and unresolved questions without automatic ranking or adjudication.
- Synchronized Library 6.14.0 / backend 3.14.0 / web 2.14.0 / SDK 1.14.0.
- No database migration; WordPress remains optional.

## 6.13.0 — Historical Archives Research Workspace

- Added standalone `/research/archives` workspace backed by Python/FastAPI authority.
- Added explicit archive search execution, source criticism, comparison, uncertainty-preserving timelines, packet previews, and ingestion-handoff previews.
- Synchronized Library 6.13.0 / backend 3.13.0 / web 2.13.0 / SDK 1.13.0.
- No database migration; WordPress remains optional.

## 6.12.0 — Historical Archive & Primary-Source Intelligence
- Advances Library to v6.12.0, backend to v3.12.0, Web to v2.12.0, and first-party SDKs to v1.12.0.
- Adds canonical historical primary-source objects with archival hierarchy, shelfmarks, identifiers, original language/script, rights/access metadata, and digital-surrogate lineage.
- Preserves uncertain historical dates without manufacturing false precision.
- Distinguishes original objects/text from scans, OCR, HTR, transcriptions, translations, editions, excerpts, and annotations.
- Adds source-criticism observations and research questions without authenticity certification or truth scoring.
- Adds cross-source comparison, archival search planning over Institutional Repository Federation, uncertainty-preserving timelines, reproducible source packets, and explicit ingestion handoffs.
- Adds no database migration and no WordPress/PHP domain authority.

## 5.72.0 — Python Source Ingestion & Normalization Service
- Advances the backend to v2.83.0 and first-party clients to v0.4.0.
- Makes Python/FastAPI authoritative for source packet validation, record normalization, and source ingestion.
- Routes legacy `/v1/ingest/records` through the canonical Python normalization service before repository ingestion.
- Adds deterministic normalization hashes, explicit transformation operations, and PostgreSQL normalization lineage.
- Preserves the existing record revision, chunking, embedding invalidation, Core-binding staleness, candidate supersession, and ingest-event path.
- Adds API v1 ingestion contract/readiness, normalization, ingestion, and source-state endpoints.
- Adds source-ingestion coverage to WordPress-failure certification.
- Keeps legacy PHP scanner/indexer/ingestion surfaces as retire-candidates; no new PHP domain authority is added.

## 5.71.0 — Python Research Projects, Collections & Saved Research State
- Advances the backend to v2.82.0 and first-party clients to v0.3.0.
- Makes Python/PostgreSQL authoritative for Library-identity-owned research projects and saved research continuity state.
- Adds durable projects, project references, references-only source bundles, saved searches, passive watchlists, research queue items, collections, and collection items.
- Adds API v1 research-state contract/readiness and signed service mutation/read surfaces.
- Preserves private-by-default ownership and reference-only bundle semantics without copying binary/source content.
- Adds research-state coverage to WordPress-failure certification.
- Keeps legacy PHP project/saved-state/collection classes as retire-candidates only; no new PHP domain authority is added.

## 5.70.0 — Python Catalog, Publication & Research Object Service
- Advances the backend to v2.81.0 and moves publication/catalog write authority to Python.
- Adds the Python catalog service contract/readiness surface and canonical research-object envelopes.
- Adds signed API v1 catalog validate/upsert/delete operations backed by existing PostgreSQL record revisioning.
- Reuses existing chunking, content hashing, embedding invalidation/re-indexing, extraction supersession and Platform Core stale-binding behavior.
- Advances first-party Python and JavaScript/TypeScript clients to v0.2.0 with catalog/research-object methods.
- Adds catalog-service coverage to WordPress-failure certification.
- Adds no new PHP domain implementation; legacy publication/indexing PHP remains explicitly classified as retire-candidate.

## 5.69.0 — Python Domain Service Migration Foundation
- Advances the Library backend to v2.80.0.
- Declares Python/FastAPI as the default authority for Library domain behavior.
- Adds `/api/library/v1/domain-authority`, `/domain-authority/readiness`, and `/domain-authority/migration-plan`.
- Adds an explicit migration sequence through v5.80.0 and distinguishes already-authoritative Python domains from pending cutovers.
- Adds a checked-in PHP retirement inventory; new PHP include files fail validation until explicitly classified.
- Adds a PHP retirement progress metric without treating it as a quality/security score.
- Tightens the WordPress thin-adapter contract to prohibit domain-logic authority while allowing presentation, routing, SEO, lifecycle and API-client adaptation.
- Adds domain-authority readiness to WordPress-failure runtime certification.
- Performs no destructive mass rewrite and introduces no database migration.

## 5.68.0 — Library SDK, Client & Integration Framework
- Adds backend v2.79.0 client-framework discovery and readiness contracts.
- Adds first-party Python SDK v0.1.0 with API v1 reads, signed writes, retries, error mapping, and six product adapters.
- Adds JavaScript/TypeScript client v0.1.0 with Web Crypto HMAC signing and TypeScript declarations.
- Adds client-framework routes to API v1 and the OpenAPI document.
- Adds the client framework to WordPress-failure runtime certification.
- Preserves WordPress as an optional thin adapter and keeps Library Web v1.2.0 and API v1.0.

## 5.67.0.2 — WordPress Runtime Boot Integrity Repair
- Permanently loads `class-sc-library-runtime-certification.php` before `SC_Library_Runtime_Certification` is instantiated.
- Aligns the WordPress thin-adapter release identity and backend runtime-certification Library identity with v5.67.0.2.
- Keeps backend capability/version at v2.78.0, Library Web at v1.2.0, and API at v1.0.
- Adds source-order validation so the missing-loader defect cannot silently recur.
- Adds deployment-time WP-CLI boot verification and rollback-safe WordPress installation.
- Adds no new research capability.

## 5.67.0.1 — Backend Release Identity & Deployment Integrity Repair
- Corrects the authoritative backend package version marker so `/health` reports backend v2.78.0.
- Aligns the WordPress thin-adapter plugin header, `SC_LIBRARY_VERSION`, and runtime-certification adapter with Library v5.67.0.1.
- Requires deployment tooling to preserve the production `.env` across backend directory replacement.
- Uses the canonical host-side Library backend endpoint `127.0.0.1:8087` for production certification instead of port 8080.
- Adds release-integrity validation for version markers, port topology, secret preservation, and WordPress-independence certification.
- Adds no new research capability and does not change Library Web v1.2.0 or API v1.0.

## 5.67.0 — WordPress-Failure Independence & Runtime Certification
- Certifies the authoritative Library runtime with WordPress failed/unreachable/disabled across API, identity, retrieval, execution, integrations, routing and independent web.

## 5.66.0 — Independent Library Release & Deployment Engineering
- Library-owned release manifests, integrity/preflight/rollback contracts, and WordPress-independent deployment authority.

# Changelog

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
