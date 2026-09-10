# Sustainable Catalyst Library

## Energy Systems Intelligence v0.1.0

**v0.1.0 — Sustainable Energy Knowledge Foundation** adds a governed, read-only Energy Systems Intelligence domain while Sustainable Catalyst Library remains on the **v5.11.0** application line and the Python Library backend advances to **v2.7.0**. The foundation contains 75 source-grounded concepts, 63 typed non-inferential relationships, six knowledge domains, six provenance source records, nine module SDG mappings, and seven explicit cross-platform handoffs.

The primary shortcode is `[sc_energy_systems_intelligence]`. It exposes Knowledge Map, Concept Registry, Sources & Provenance, and Platform Handoffs views. Historical conversion factors are registered only as source provenance in v0.1.0; numerical factors, indicator calculation, scenario modeling, technology ranking, and policy recommendation remain intentionally disabled. Existing Carbon & Nature Intelligence v0.5.0 is preserved, with active semantic handoffs for soil carbon and forest/woodland concepts and explicit planned-extension status for biochar, digestate/anaerobic digestion, biomass-to-oil, and CO₂-to-energy until governed Carbon & Nature targets exist.

See `ENERGY_SYSTEMS_INTELLIGENCE_v0.1.0.md`, `RELEASE_NOTES_ENERGY_SYSTEMS_0.1.0.md`, and `DEPLOY_ENERGY_SYSTEMS_BACKEND_v2.7.0.md`.

## Carbon & Nature Intelligence v0.5.0

Carbon & Nature advances to **v0.5.0 — AFOLU Research Librarian Intelligence** while Sustainable Catalyst Library remains on the v5.11.x application line and the Python Library backend advances to **v2.6.0**. The release preserves the v0.1 ontology, v0.2 Measure Registry, v0.3 Evidence & Methodology Graph, and v0.4 Project Object Model & Provenance, then adds deterministic AFOLU research-intent detection, question framing, source-role planning, evidence-gap diagnostics, freshness review, governed handoffs, and Project-Aware Research Librarian packet augmentation.

The primary shortcode remains `[sc_carbon_nature_intelligence]`. v0.5.0 opens on the AFOLU Research Librarian view and retains the prior Project Objects & Provenance, Evidence & Methodology Graph, and Measure Registry views. Research guidance is routing/context intelligence rather than an automatic scientific conclusion, suitability decision, methodology approval, current-rule assertion, verification, certification, or credit issuance.

See `CARBON_NATURE_INTELLIGENCE_v0.5.0.md`, `RELEASE_NOTES_CARBON_NATURE_0.5.0.md`, and `DEPLOY_CARBON_NATURE_BACKEND_v2.6.0.md`.

## Current release

## Carbon & Nature Intelligence v0.4.0

Carbon & Nature advances to **v0.4.0 — Carbon Project Object Model & Provenance** while Sustainable Catalyst Library remains on the v5.11.x application line and the Python Library backend advances to **v2.5.0**. The release preserves the v0.1 ontology, v0.2 Measure Registry, and v0.3 Evidence & Methodology Graph while adding ten governed project object types, nine provenance-event types, nine explicit project link predicates, deterministic integrity fingerprints, a packet template, and bounded stateless project-packet validation.

**v5.11.0 — Private Organizational Knowledge Foundation** adds a separate organization-scoped private knowledge plane alongside the public Library. Backend v2.1.0 stores private organizations, sources, records, version history, ingest events, and access events in dedicated PostgreSQL tables; every private read is server-signed, organization-scoped, and access-scope filtered. Public Library search, the institutional network, and biomedical evidence surfaces do not query the private tables. Controlled handoff packets preserve the private boundary for Research Librarian, Workspace, and Lab.

# Sustainable Catalyst Library

## v5.11.0 — Private Organizational Knowledge Foundation

v5.11.0 establishes the enterprise-private side of Library without weakening the public research architecture. It accepts normalized extracted-text records from internal documents and data sources, preserves source ownership/provenance and deterministic version lineage, supports organization/restricted/project access levels, records privacy-minimized audit events, and exposes private search/detail/version/handoff endpoints only through signed server-to-server requests. PDF/DOCX binary parsing is not newly claimed; existing Library conversion/OCR paths can feed normalized text into the private ingest API.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.11.0.md` and `PRIVATE_ORGANIZATIONAL_KNOWLEDGE_FOUNDATION_v5.11.0.md`.

## v5.10.0 — Institutional Research Network II

v5.10.0 adds governed cross-repository institutional research discovery while preserving heterogeneous repository protocols and rights state. DSpace@MIT and Dataverse sources use native search surfaces; Research Repository UCD uses a bounded OAI-PMH metadata harvest and explicitly reports that limitation. Cross-source consolidation is exact-DOI-first and never title-only.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.10.0.md` and `INSTITUTIONAL_RESEARCH_NETWORK_II_v5.10.0.md`.

## v5.9.1 — Biomedical Evidence Graph Reliability & Provenance Repair

v5.9.1 makes biomedical evidence graphs reproducible and auditable. Exact identifiers drive consolidation; identical logical edges aggregate provenance; every node/edge is covered by a provenance ledger; graph output is deterministically sorted and fingerprinted; and incomplete upstream coverage is explicitly contained and reported. No PostgreSQL migration or new credentials are required.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.9.1.md` and `BIOMEDICAL_EVIDENCE_GRAPH_RELIABILITY_PROVENANCE_v5.9.1.md`.

## v5.9.0 — Biomedical Evidence Graph & Evidence Synthesis

v5.9.0 adds a governed biomedical evidence graph plus descriptive synthesis, including exact ClinicalTrials.gov PMID links, trial-condition/intervention/outcome edges, terminology candidate context, regulatory evidence-class preservation, integrity-signal propagation, and Research Librarian/Lab-ready handoff structure. No PostgreSQL migration or new credentials are required.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.9.0.md` and `BIOMEDICAL_EVIDENCE_GRAPH_SYNTHESIS_v5.9.0.md`.

## v5.8.4 — Biomedical Evidence Grading & Study Design Intelligence

v5.8.4 adds metadata-derived study-design classification, evidence-body mapping, integrity signals, certainty-domain readiness, and human-review handoffs. Library backend is v1.7.0. Formal certainty grades and formal risk-of-bias judgments are not generated automatically.


## v5.8.1.1 — Release Console Version Identity & Runtime Synchronization Repair

v5.8.1.1 repairs public release identity drift without relabeling historical module provenance. The homepage/research console now reads its visible Library release directly from the canonical `SC_LIBRARY_VERSION`, exposes a no-store `/wp-json/sc-library/v1/runtime/release` runtime contract, and displays Library and backend versions separately. Backend v1.4.0 is unchanged; no backend redeploy or PostgreSQL migration is required. FDA, biomedical, Johns Hopkins, Explorer, and Publications behavior are preserved.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.8.1.1.md` and `RELEASE_CONSOLE_RUNTIME_SYNC_v5.8.1.1.md`.

## v5.8.1 — FDA Drug & Regulatory Intelligence

v5.8.1 extends the biomedical evidence foundation with a governed FDA regulatory layer backed by openFDA. Drugs@FDA, drug labeling, the NDC Directory, FAERS adverse-event reports, drug recall enforcement reports, drug shortages, and the Orange Book are normalized as distinct regulatory evidence classes rather than flattened into clinical literature. The Library backend advances to v1.4.0 and adds FDA-specific and combined biomedical+regulatory search routes. A new `[sc_fda_regulatory_intelligence]` WordPress surface exposes the capability while preserving explicit research-only and adverse-event causality guardrails. No PostgreSQL migration is required.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.8.1.md` and `FDA_DRUG_REGULATORY_INTELLIGENCE_v5.8.1.md`.

## v5.8.0 — Biomedical & Clinical Evidence Intelligence Foundation

v5.8.0 establishes governed biomedical discovery across PubMed, PMC, ClinicalTrials.gov, MeSH 2026, and RxNorm. The backend adds normalized evidence/concept objects and a unified biomedical search route, while WordPress adds `[sc_biomedical_evidence]`. No PostgreSQL migration is required.

## v5.6.0 — Dynamic Library Explorer & Progressive Discovery

v5.6.0 moves the public Library front door onto the hardened Python/PostgreSQL read model. It adds a compact Explorer, bounded 12-record discovery pages, topic/type/source/year filters, URL-preserved search state, load-more retrieval, progressive quick-view drawers, related-record discovery, provenance, record timelines, and a WordPress-local fallback. The release also includes a compact `RESEARCH_LIBRARY_PAGE_v5.6.0.html` that reduces the former 500+ line public page to a focused Explorer plus research-tool handoffs.

The v5.5 ingestion-hardening and backend-operations/recovery contracts remain intact. No PostgreSQL migration is required.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.6.0.md` and `DYNAMIC_LIBRARY_EXPLORER_v5.6.0.md`.

## v5.5.2 — Backend Operations & Recovery

v5.5.2 adds a signed operations and recovery layer to the Python research-intelligence backend: WordPress-vs-backend integrity audits, missing/stale/orphan/chunkless detection, targeted repairs, verified orphan pruning, post-ID and Library Collection reindexing, operation lineage, and backend ingest/coverage diagnostics. WordPress remains authoritative for editorial state and record existence. No PostgreSQL migration is required.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.5.2.md` and `LIBRARY_BACKEND_OPERATIONS_RECOVERY_v5.5.2.md`.

## v4.5.0 — Knowledge Graph & Evidence Intelligence

v4.5.0 adds a private, account-scoped graph projection over the canonical research environment. `[sc_knowledge_graph_evidence_intelligence]` composes explicit Research Project links, Source Bundles, project-attached Reading Notebooks, notes, annotations, Evidence Matrices, claims, evidence sources, and Open Learning II routes into one bounded graph without creating a replacement graph database.

Evidence Intelligence reuses the deterministic v4.3.32 matrix diagnostics to summarize support, contradiction, qualification, context, source diversity, unresolved references, and quote/locator verification gaps. It never scores truth, infers a semantic relationship from private text, changes claim status or user-declared confidence, publishes research, or writes to Workspace. The public Knowledge Graph and Publications ↔ Research Graph remain separate public projections.

## v4.4.0 — Unified Personal Research Environment

v4.4.0 consolidates the signed-in research experience without consolidating the underlying data stores. `[sc_personal_research_environment]` reads the canonical My Library, Saved Research, Research Projects/Source Bundles, Reading Notebooks, Evidence Matrices, Open Learning II routes, Workspace continuity, Research Librarian II, and portability lineage and presents one private research home with counts, project context, and resume links.

The release is composition-only: no record migration, no duplicate project/notebook/evidence store, no automatic evidence promotion or publication, no private-context remote synthesis, and no automatic Workspace write. The v4.3.40 production-hardening gate is retained and version-aligned to v4.4.0.

## v4.3.40 — 4.3 Branch Production Hardening

This release certifies the complete v4.3 research branch using the existing Production Readiness engine. A dedicated first-party release gate verifies runtime/version alignment, the isolated extension bootstrap, critical v4.3 modules and assets, canonical `/knowledge-libraries/` routing, shared Library/Workspace account continuity, and authenticated private REST surfaces. Third-party provider health is non-blocking and readiness diagnostics do not inspect private research content.

Public summary: `/wp-json/sc-library/v1/runtime/production-readiness`  
Admin-only detail: `/wp-json/sc-library/v1/runtime/production-readiness/details`  
Public status shortcode: `[sc_library_readiness_status]`

The v4.3.27–v4.3.39 research capabilities remain intact; v4.3.40 is a stabilization and certification release rather than a new research-data system.

## Historical release notes

## v4.2.0 — Twelve-Topic Two-Tier Homepage Spotlight

This release expands the Knowledge Library Homepage Spotlight into a twelve-topic editorial surface while preserving the established five-article page format. Eight primary topics remain visible in the opening navigation, and four additional fields are available through a restrained secondary tier within the same console.

The recommended topic structure is:

- Primary: Sustainable Development, Planetary Boundaries, International Law, Biology, Systems Thinking, Economics, Artificial Intelligence, and Physics.
- Secondary: Embedded & Edge Systems, Psychology, Decision Science, and Data Systems & Analytics.

Automatic rotation stays within the primary tier until the additional fields are opened. Existing topic pages without tier metadata remain primary, and no articles are populated or backfilled automatically.

See `RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.2.0.md` and `HOMEPAGE_SPOTLIGHT_TWO_TIER_GUIDE_v4.2.0.md`.


## Carbon & Nature Intelligence v0.2.0

Carbon & Nature Intelligence is a Library-native subsystem with its own release line. v0.2.0 adds the Carbon Sequestration Measure Registry on the v0.1.0 AFOLU & Nature-Based Solutions Knowledge Foundation while the Library application remains v5.11.0. Backend v2.3.0 exposes structured measure discovery, measure detail, bounded non-ranking comparison, and measure-aware Research Librarian context packets. No PostgreSQL migration or new credential is required. See `CARBON_NATURE_INTELLIGENCE_v0.2.0.md`.
