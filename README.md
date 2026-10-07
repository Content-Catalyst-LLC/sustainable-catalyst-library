## Library v6.35.0 — Collaborative Research Rooms II

Adds explicit collaborative room manifests, membership/role matrices, scoped research-object references, activity streams, human review packets, access audits, portable exchange handoffs, and deterministic room exports.

## Library v6.34.0 — Portable Research Object & Exchange Format

Adds lossless, deterministic, authority-preserving research-object envelopes and explicit multi-object exchange manifests with integrity verification and non-mutating import preview.

## Library v6.33.0 — Research Package Validation & Publication Readiness

Adds transparent structural, completeness, provenance, review, and publication-handoff readiness diagnostics without automatic publication or truth promotion.

# Sustainable Catalyst Library v6.32.0 — Research Review, Revision & Versioning System

# Sustainable Catalyst Library v6.31.0 — Research Dependency & Lineage Graph

# Sustainable Catalyst Knowledge Library

Current release: **v6.12.0 — Historical Archive & Primary-Source Intelligence**. Backend: **v3.12.0** · Python SDK: **v1.12.0** · JS/TS Client: **v1.12.0** · Library Web: **v2.12.0** · API: **v1.0 stable**.

This release makes historical archives and primary sources first-class research objects with archival hierarchy, uncertain-date preservation, original/derived representation lineage, source-criticism observations, cross-source comparison, archive search planning, timelines, reproducible packets, and explicit ingestion handoffs. WordPress remains optional and non-authoritative.

# Sustainable Catalyst Knowledge Library

Current release: **v5.72.0 — Python Source Ingestion & Normalization Service**. Backend: **v2.83.0** · Python SDK: **v0.4.0** · JS/TS Client: **v0.4.0** · Library Web: **v1.2.0** · API: **v1.0**.

This release makes Python/FastAPI authoritative for source ingestion and normalization while preserving the existing PostgreSQL record model, revisioning, chunking, embedding invalidation, provenance, and source identities. WordPress remains an optional upload/configuration/presentation adapter.

# Sustainable Catalyst Knowledge Library

Current release: **v5.71.0 — Python Research Projects, Collections & Saved Research State**. Backend: **v2.82.0** · Python SDK: **v0.3.0** · JS/TS Client: **v0.3.0** · Library Web: **v1.2.0** · API: **v1.0**.

This release moves identity-owned research projects, references-only source bundles, saved searches, passive watchlists, research queue items, and personal research collections to Python/FastAPI with PostgreSQL as the durable state authority. WordPress remains an optional thin presentation and API-client adapter.

# Sustainable Catalyst Knowledge Library

Current release: **v5.70.0 — Python Catalog, Publication & Research Object Service**. Backend: **v2.81.0** · Python SDK: **v0.2.0** · JS/TS Client: **v0.2.0** · Library Web: **v1.2.0** · API: **v1.0**.

This release moves publication/catalog mutation authority into Python/FastAPI, adds canonical research-object envelopes and signed catalog mutations, and keeps WordPress/PHP as a presentation and compatibility client. Existing PostgreSQL record/version/chunk infrastructure remains the single catalog persistence path.

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


## Historical Archives Research Workspace

Library v6.13.0 adds the standalone `/research/archives` workspace for explicit repository search, primary-source inspection, comparison, uncertain timelines, and reproducible packet/handoff previews. WordPress is not required.

## Primary-Source Comparison & Source Criticism Workspace

Library v6.14.0 adds `/research/archives/compare` for structured source criticism, comparison matrices, explicit source relationships, and corroboration/contradiction ledgers without truth or reliability scoring.

## Research Timeline & Historical Event Workspace

Library v6.15.0 adds `/research/archives/timeline` for source-linked historical events, uncertainty-preserving chronologies, event/source coverage, and explicit comparison of competing timelines without automatic reconciliation.

## Research Annotation & Scholarly Notes

Library v6.16.0 adds `/research/notes` for explicit target-anchored scholarly notes, browser-local working continuity, notebook composition, and JSON export without automatic evidence, citation, or truth promotion.

## Citation Workspace & Bibliographic Intelligence

Library v6.17.0 adds `/research/citations` for bibliographic normalization, duplicate review, bibliography composition, and portable JSON/BibTeX/RIS export while preserving the existing Python/PostgreSQL citation service as durable citation authority.

Library v6.17.0.1 repairs citation workspace route precedence so static `/citations/workspace/*` endpoints resolve before the legacy record-citation catch-all. Web 2.17.0 and SDK 1.17.0 are unchanged.

Library v6.18.0 adds `/research/corpus`, a computational-linguistics workspace over the existing v5.47 durable linguistic-corpus authority, with frequency, KWIC, n-gram, co-occurrence, lineage, and export analysis.

Library v6.19.0 adds `/research/entities`, an entity, place, and historical-toponym workspace over the existing v5.48 durable cross-language resolution authority.

Library v6.20.0 adds `/research/synthesis`, a composition workspace for explicit cross-source synthesis, evidence matrices, contradictions, convergence, attribution, gaps, and export packages.

Library v6.21.0 adds `/research/investigation`, a structured research-question and investigation-control workspace with explicit evidence needs, tasks, dependencies, risks, stop conditions, and signed handoff previews.

Library v6.22.0 adds `/research/evidence`, a claim-evidence matrix and support-analysis workspace with explicit provenance, source-dependency, contradiction, qualification, gap, and handoff semantics.

Library v6.23.0 adds `/research/data`, a dataset-discovery and statistical-evidence workspace with explicit variable, design, uncertainty, multiple-comparison, missing-data, effect-size, and quantitative-evidence handoff semantics.

Library v6.24.0 adds `/research/geospatial`, a CRS-aware geospatial and place-based research workspace with spatial relation previews, temporal validity, coverage auditing, and non-inferential evidence/investigation handoffs.

Library v6.25.0 adds `/research/package`, a typed Research Package Composer with composition, completeness/provenance auditing, dependency mapping, and non-executing publishing/reproducibility handoffs.

Library v6.26.0 adds `/research/publication`, a Research Publication Studio that consumes v6.25 package compositions and provides structured publication sections, contributor/asset/citation inventories, editorial/readiness audits, deterministic draft export, and preview-only publishing handoff.

Library v6.27.0 adds `/research/graph`, a Unified Research Knowledge Graph that composes existing research objects and explicit relations into deterministic, provenance-preserving graph snapshots with validation, canonical-chain audits, neighborhood/path traversal, and portable export while preserving every originating authority.

Library v6.28.0 adds `/research/workspace`, a Library ↔ Workspace Research Integration surface for explicit Library-to-Workspace research handoffs, Workspace-result registration previews, authority-preserving round-trip audits, and deterministic exchange export; live cross-product transport remains deferred to v6.29 certification.

Library v6.29.0 adds `/research/integration-certification`, a cross-product research handoff and contract-certification surface for Research Librarian AI, Workspace, Research Lab, Workbench, Site Intelligence, Decision Studio, and Platform Core with explicit compatibility, provenance, authority, signed-write, failure-behavior, and runtime-observation boundaries.

Library v6.30.0 adds `/research/project`, a Unified Research Project Workspace that composes existing research objects, authority lineage, dependencies, and cross-product handoff manifests without replacing existing domain or PostgreSQL research-state authorities.
