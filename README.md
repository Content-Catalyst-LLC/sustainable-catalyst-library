# Sustainable Catalyst Library

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
