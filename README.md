## Current release

**v5.9.0 — Biomedical Evidence Graph & Evidence Synthesis** connects PubMed literature, ClinicalTrials.gov studies/results/publication references, terminology candidates, FDA regulatory context, and v5.8.4 evidence profiles into a provenance-backed graph. The Library backend is v1.8.0. Synthesis is descriptive and does not generate pooled effects, formal GRADE certainty, causal conclusions, comparative-effectiveness rankings, or clinical recommendations.

# Sustainable Catalyst Library

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
