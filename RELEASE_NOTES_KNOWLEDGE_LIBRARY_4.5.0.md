# Release Notes — Sustainable Catalyst Library v4.5.0

## Knowledge Graph & Evidence Intelligence

v4.5.0 adds a private, account-scoped research graph over the Unified Personal Research Environment. The graph composes explicit relationships already stored in Research Projects, Source Bundles, Reading Notebooks, Evidence Matrices, and Open Learning II routes.

### Added

- `[sc_knowledge_graph_evidence_intelligence]`
- authenticated project catalog and graph REST endpoints
- deterministic node/edge projection with bounded output
- browser-rendered SVG graph with an accessible relationship record
- project-level Evidence Intelligence summaries derived from v4.3.32 matrix diagnostics
- release-certification coverage for the new module, route, and assets

### Preserved

- v4.4.0 Unified Personal Research Environment
- v4.3.40 production-hardening lineage
- public Knowledge Graph and Publications ↔ Research Graph boundaries
- existing project, notebook, matrix, learning-route, personal-library, saved-research, and Workspace-continuity stores

### Explicit non-goals

No semantic relationship is inferred from private text. The release does not score truth, generate claims, change claim status or confidence, auto-resolve entities, auto-publish private research, or write to Workspace.
