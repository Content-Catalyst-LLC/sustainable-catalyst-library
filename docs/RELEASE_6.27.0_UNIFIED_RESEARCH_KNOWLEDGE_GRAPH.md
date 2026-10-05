# Sustainable Catalyst Library v6.27.0 — Unified Research Knowledge Graph

Library v6.27.0 adds a unified, provenance-preserving composition graph across the research objects already produced by the Library research workspaces.

## Research chain

The graph can connect:

`Research Question → Investigation → Source → Claim → Evidence → Dataset → Statistical Result → Place → Event → Annotation → Citation → Synthesis → Research Package → Publication`

The graph does **not** replace the originating object services. Each node retains an explicit authority, version, object reference, metadata, and provenance envelope. Edges are explicit research relations; their presence does not establish truth, causality, evidence strength, semantic equivalence, or importance.

## New surface

- Web route: `/research/graph`
- API base: `/api/library/v1/research-knowledge-graph`
- Library: `6.27.0`
- Backend: `3.27.0`
- Web: `2.27.0`
- SDK: `1.27.0`

## API operations

- `GET /research-knowledge-graph`
- `GET /research-knowledge-graph/readiness`
- `GET /research-knowledge-graph/bootstrap`
- `POST /research-knowledge-graph/build`
- `POST /research-knowledge-graph/validate`
- `POST /research-knowledge-graph/chain-audit`
- `POST /research-knowledge-graph/neighborhood`
- `POST /research-knowledge-graph/path`
- `POST /research-knowledge-graph/export`

## Authority boundaries

- Source authority remains the catalog/source-ingestion layer.
- Claim/evidence authority remains the evidence workspaces and services.
- Dataset/statistical-result authority remains the statistical evidence workspace.
- Place authority remains the geospatial workspace.
- Event authority remains the timeline workspace.
- Annotation authority remains the scholarly notes workspace.
- Citation authority remains the citation service/workspace.
- Synthesis authority remains the synthesis workspace.
- Research Package Composer v6.25 remains package composition authority.
- Research Publication Studio v6.26 remains publication-draft authority.
- v6.27 is a deterministic **composition and traversal layer** over those objects.

## Guardrails

- No automatic source, claim, evidence, dataset, citation, package, or publication promotion.
- No automatic entity merge or semantic merge.
- No automatic external fetch.
- No server-side graph persistence.
- No database migration.
- WordPress remains optional and non-authoritative.
- Graph connectivity, centrality, and path existence are structural signals only.

## Next architecture release

`6.28.0 — Library ↔ Workspace Research Integration`
