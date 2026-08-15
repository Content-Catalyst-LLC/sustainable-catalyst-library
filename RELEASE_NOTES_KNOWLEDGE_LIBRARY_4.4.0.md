# Sustainable Catalyst Library v4.4.0 — Unified Personal Research Environment

v4.4.0 is the first consolidation release after the certified 4.3 branch. It adds one authenticated research home over the existing private research stores without migrating their data.

### Added
- `[sc_personal_research_environment]`.
- Authenticated `/wp-json/sc-library/v1/personal-research-environment` state endpoint.
- Private totals for Projects, My Library, saved searches, watchlists, research queue, Reading Notebooks, Evidence Matrices, and learning routes.
- Owned-project context with references, Source Bundles, notebooks, matrices, learning routes, stable project URN, and bounded resume links.
- Mobile/accessibility treatment with 44px controls, focus-visible styling, and reduced-motion compatibility.

### Preserved
All v4.3.27–v4.3.40 storage contracts and privacy boundaries remain intact. No third-party provider availability is used as a release blocker.

### Not introduced
No replacement project store, notebook store, evidence store, personal-library store, automatic migration, automatic evidence promotion, automatic publication, automatic Workspace write, or private-context remote synthesis.
