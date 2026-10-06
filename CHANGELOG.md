## 6.31.0 — Research Dependency & Lineage Graph

- Adds explicit project dependency and provenance lineage graph composition, validation, traversal, path, impact, provenance audit, and deterministic export.
- Preserves v6.30 project/domain authorities and PostgreSQL research-state persistence.
- Repairs Library Web deployment default to the established production bind port 8095.
- No database migration and no automatic semantic/causal relationship inference.

## 6.30.0 — Unified Research Project Workspace

- Added `/research/project` and `/api/library/v1/research-project-workspace`.
- Added deterministic project manifests over typed research-object references with explicit authority lineage.
- Added component inventory, coverage diagnostics, authority audit, dependency summary, cross-product handoff manifest, and portable project export.
- Preserves Python/PostgreSQL research-state authority and all originating domain authorities.
- No automatic persistence, remote execution, result import, claim/evidence/truth promotion, or Platform Core promotion.
- Synchronized Library 6.30.0 / backend 3.30.0 / web 2.30.0 / SDK 1.30.0.
- No database migration; WordPress remains optional.

## 6.29.0 — Cross-Product Research Handoff & Contract Certification

- Added `/research/integration-certification` and `/api/library/v1/cross-product-certification`.
- Added explicit product contracts for Research Librarian AI, Workspace, Research Lab, Workbench, Site Intelligence, Decision Studio, and Platform Core.
- Added provenance-preserving cross-product handoff envelopes, validation, compatibility matrices, fail-closed behavior audits, and deterministic certification export.
- Structural contract certification remains distinct from live runtime certification; live claims require explicit runtime observations.
- Preserves originating-product authority, Platform Core governed-meaning authority, and signed persistence boundaries.
- No automatic remote execution, cross-product push, result import, persistence, claim/evidence/truth promotion, or Platform Core promotion.
- Synchronized Library 6.29.0 / backend 3.29.0 / web 2.29.0 / SDK 1.29.0.
- No database migration; WordPress remains optional.

## 6.28.0 — Library ↔ Workspace Research Integration

- Added `/research/workspace` and `/api/library/v1/workspace-integration`.
- Added deterministic Library → Workspace research handoff packets with typed Library object references and explicit requested actions.
- Added handoff validation, Workspace-result registration previews, project/source correlation, round-trip audits, and portable exchange export.
- Preserves Library originating authorities and Workspace execution authority; neither product becomes the other product’s persistence authority.
- No automatic Workspace execution/project creation, cross-product push, result import, Library persistence, claim/evidence/truth promotion, or Platform Core promotion.
- Live cross-product transport and end-to-end execution certification are deferred to v6.29.0.
- Synchronized Library 6.28.0 / backend 3.28.0 / web 2.28.0 / SDK 1.28.0.
- No database migration; WordPress remains optional.

## 6.27.0 — Unified Research Knowledge Graph

- Added `/research/graph` as a unified research-object graph workspace.
- Added typed nodes spanning research question, investigation, source, claim, evidence, dataset, statistical result, place, event, annotation, citation, synthesis, research package, and publication.
- Added explicit relation normalization, deterministic graph snapshots, graph validation, canonical research-chain audits, neighborhood traversal, path traversal, and portable JSON export.
- Preserves originating object authorities and versions; v6.27 is graph composition/traversal authority only.
- Graph connectivity, centrality, and paths do not imply truth, causality, evidence quality, semantic equivalence, or importance.
- No automatic semantic merge, entity merge, claim/evidence/truth promotion, external fetch, server-side graph persistence, or Platform Core promotion.
- Synchronized Library 6.27.0 / backend 3.27.0 / web 2.27.0 / SDK 1.27.0.
- No database migration; WordPress remains optional.

## 6.26.0 — Research Publication Studio

- Added `/research/publication` as a publication-draft workspace consuming explicit v6.25 Research Package Composer compositions.
- Added publication profiles, structured sections, contributors, figure/table/appendix inventories, citation inventories, editorial audits, publication-readiness audits, deterministic JSON draft export, and preview-only Research Package Publishing handoffs.
- Preserves existing authorities: the Studio is not source, evidence, citation, package-composition, publication-persistence, reproducibility, or artifact-byte authority.
- Publication readiness remains an editorial completeness signal and never implies truth, evidence strength, source validity, peer review, or publication acceptance.
- No automatic research-prose generation, external publication, DOI registration, artifact persistence, claim/evidence promotion, or Platform Core promotion is introduced.
- Synchronized Library 6.26.0 / backend 3.26.0 / web 2.26.0 / SDK 1.26.0.
- No database migration; WordPress remains optional.

## 6.25.0 — Research Package Composer

- Added `/research/package` as a draft composition workspace over investigation, synthesis, evidence, statistical, geospatial, citation, annotation, timeline, corpus, entity/place, primary-source, dataset, record, artifact, publication, and method outputs.
- Added typed component assembly, explicit section ordering and dependency maps, completeness audits against user-declared requirements, provenance audits, deterministic draft exports, portable publishing handoff previews, and signed reproducibility-package handoff previews.
- Preserves existing authority boundaries: the composer is not the Research Package reproducibility authority, portable publishing authority, structured-state authority, or artifact-byte store.
- Draft completeness and package integrity never imply research truth, source validity, evidence strength, or publication readiness.
- Synchronized Library 6.25.0 / backend 3.25.0 / web 2.25.0 / SDK 1.25.0.
- No database migration; WordPress remains optional.

## 6.24.0 — Geospatial & Place-Based Research Workspace

- Added `/research/geospatial` as a geospatial/place-based research workspace over Entity/Place, Statistical Evidence, Structured Evidence, Federation, Investigation, and Evidence Matrix layers.
- Added place, layer, and spatial-feature inventories; explicit CRS metadata; temporal validity; point-distance and bounding-box relation previews; spatial coverage auditing; place-linked evidence handoffs; investigation-gap handoffs; and reproducible JSON export.
- Spatial proximity, overlap, containment, clustering, raster resolution, coordinate precision, and map appearance never become automatic causality, identity, jurisdiction, evidence-strength, or truth judgments.
- Missing CRS is never silently assumed and coordinate transformation is not automatic.
- Synchronized Library 6.24.0 / backend 3.24.0 / web 2.24.0 / SDK 1.24.0.
- No database migration; WordPress remains optional.

## 6.23.0 — Dataset Discovery & Statistical Evidence Workspace

- Added `/research/data` as a quantitative evidence workspace over Structured Evidence, Scientific Literature, Global Federation, Investigation, and Evidence Matrix layers.
- Added metadata-based dataset discovery, dataset profiles, variable dictionaries, units and denominators, study-design/population context, normalized statistical-result tables, uncertainty audits, effect-size preservation, multiple-comparison and missing-data context, and dataset/statistical gap analysis.
- Added non-executing handoff previews to the v6.22 Evidence Matrix and v6.21 Investigation workspace, plus reproducible JSON export.
- P-values, confidence intervals, sample size, correlations, regression adjustment, model fit, and effect sizes never become automatic truth, validity, practical-significance, or causality judgments.
- Synchronized Library 6.23.0 / backend 3.23.0 / web 2.23.0 / SDK 1.23.0.
- No database migration; WordPress remains optional.

## 6.22.0 — Evidence Matrix & Claim Support Analysis

- Added `/research/evidence` as a claim-evidence analysis workspace over existing provenance, citation, structured-evidence, synthesis, and investigation layers.
- Added explicit claim/evidence inventories and links, claim-evidence matrices, descriptive support profiles, contradiction/qualification analysis, provenance coverage, source-dependency and independence-group analysis, evidence-gap analysis, and reproducible JSON exports.
- Added non-executing handoff previews to Research Synthesis and Research Investigation for downstream synthesis and evidence-gap follow-up.
- Support counts, contradiction counts, directness, provenance completeness, and independent-source counts remain descriptive; none are truth probabilities, confidence scores, quality scores, or automatic verdicts.
- Synchronized Library 6.22.0 / backend 3.22.0 / web 2.22.0 / SDK 1.22.0.
- No database migration; WordPress remains optional.

## 6.21.0 — Research Question & Investigation Workspace

- Added `/research/investigation` as a structured research-question and investigation-control workspace.
- Added explicit subquestions, hypotheses, evidence needs, source strategy, investigation tasks, dependency-aware execution waves, decision points, stop conditions, risk/bias register, coverage analysis, and reproducible JSON exports.
- Added preview-only signed handoffs to existing research-project, saved-search, research-queue, and retrieval-plan authorities; the workspace never submits or persists them automatically.
- Preserves the boundary between planning and evidence: hypotheses are not truth, task completion is not evidence, search/retrieval results are not automatically promoted, and priorities or stop conditions never determine truth.
- Synchronized Library 6.21.0 / backend 3.21.0 / web 2.21.0 / SDK 1.21.0.
- No database migration; WordPress remains optional.

## 6.20.0 — Research Synthesis Workspace

- Added `/research/synthesis` as a cross-source synthesis workspace over existing Library citation, provenance/evidence, annotation, timeline, corpus, entity/place, research-state, and research-package authorities.
- Added source and claim inventories, explicit source↔claim stance relationships, evidence matrices, contradiction ledgers, convergence summaries, source-attribution maps, unresolved-question preservation, gap analysis, and reproducible JSON synthesis exports.
- Added research-package publishing handoff previews while keeping synthesis workspace state non-authoritative and non-persistent.
- Preserves disagreement and source attribution; support counts, source counts, citation counts, agreement, and convergence patterns are never treated as truth probability, quality scores, or automatic winner selection.
- Synchronized Library 6.20.0 / backend 3.20.0 / web 2.20.0 / SDK 1.20.0.
- No database migration; WordPress remains optional.

## 6.19.0 — Entity, Place & Historical Toponym Workspace

- Added `/research/entities` as a researcher-facing workspace over the existing v5.48 Python/PostgreSQL cross-language entity-resolution authority.
- Added non-persistent authority previews, multilingual/endonym/exonym/transliteration name forms, historical toponym timelines, temporal candidate resolution, candidate comparison matrices, explicit decision previews, persisted-case inspection, and reproducible JSON research exports.
- Added signed persistence-handoff previews for authority registries, resolution cases, and decisions without creating a parallel entity store or silently persisting workspace previews.
- Preserves ambiguity and source/name-form provenance; candidate scores/ranks, name similarity, coordinates, country codes, and historical validity windows never establish identity, truth, evidence strength, or winner selection.
- Synchronized Library 6.19.0 / backend 3.19.0 / web 2.19.0 / SDK 1.19.0.
- No database migration; WordPress remains optional.

## 6.18.0 — Corpus & Computational Linguistics Workspace

- Added `/research/corpus` as a researcher-facing corpus and computational-linguistics workspace over the existing v5.47 Python/PostgreSQL linguistic-corpus authority.
- Added non-persistent corpus preview, persisted-corpus analysis, token frequency tables, KWIC/concordance, n-gram analysis, co-occurrence analysis, representation-lineage visibility, and reproducible JSON analysis exports.
- Added an explicit persistence handoff preview to the existing signed `/admin/language/corpora` authority; the workspace never creates a second corpus store or auto-persists preview corpora.
- Preserves original-language-first analysis and explicit derived-representation lineage; frequency, KWIC, n-grams, and co-occurrence are descriptive and never promoted to meaning, importance, evidence, truth, or causation.
- Synchronized Library 6.18.0 / backend 3.18.0 / web 2.18.0 / SDK 1.18.0.
- No database migration; WordPress remains optional.

## 6.17.0.1 — Citation Workspace Route Precedence Repair

- Repairs FastAPI route ordering so `/api/library/v1/citations/workspace`, `/readiness`, and `/bootstrap` resolve before the legacy `/api/library/v1/citations/{record_id:path}` catch-all.
- Preserves the existing durable Python/PostgreSQL citation authority and all v6.17 bibliographic workspace semantics.
- Library 6.17.0.1 / backend 3.17.0.1; Web remains 2.17.0 and SDK remains 1.17.0.
- No database migration. WordPress remains optional/non-authoritative.

## 6.17.0 — Citation Workspace & Bibliographic Intelligence

- Added dedicated `/research/citations` workspace for bibliographic normalization, collection composition, duplicate-candidate analysis, and portable citation exports.
- Added CSL-compatible metadata envelopes, normalized DOI/ISBN/PMID/PMCID/arXiv/URL identifiers, deterministic local citation keys, metadata-completeness observations, and JSON/BibTeX/RIS exports.
- Preserves the existing Python/PostgreSQL provenance citation service as durable citation-edge authority; the workspace never creates durable citation edges automatically.
- Duplicate candidates remain human-review objects and are never auto-merged; citation presence/counts and metadata completeness are never treated as quality, evidence, or truth scores.
- Synchronized Library 6.17.0 / backend 3.17.0 / web 2.17.0 / SDK 1.17.0.
- No database migration; WordPress remains optional.

## 6.16.0 — Research Annotation & Scholarly Notes

- Added dedicated `/research/notes` workspace for annotations across records, primary sources, historical events, timelines, datasets, publications, and other explicit research targets.
- Added locator-aware annotation objects, scholarly note types, tags, references, human-asserted note relationships, notebook composition, unresolved-question preservation, and deterministic JSON export.
- Added browser-local note continuity while explicitly keeping browser storage non-authoritative and server-side note persistence disabled in this release.
- Notes, quotes, locators, tags and relationships do not automatically become verified source content, evidence, claims, citations, truth status, or Platform Core objects.
- Synchronized Library 6.16.0 / backend 3.16.0 / web 2.16.0 / SDK 1.16.0.
- No database migration; WordPress remains optional.

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
