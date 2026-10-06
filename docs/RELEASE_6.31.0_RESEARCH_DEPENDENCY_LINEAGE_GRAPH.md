# Library v6.31.0 — Research Dependency & Lineage Graph

**Library:** 6.31.0
**Backend:** 3.31.0
**Web:** 2.31.0
**SDK:** 1.31.0
**API:** v1 stable

## Purpose
Build an explicit, provenance-preserving dependency and lineage graph across the components assembled by the v6.30 Unified Research Project Workspace.

## Surfaces
- Web: `/research/project/lineage`
- API: `/api/library/v1/research-lineage-graph`

## Operations
- build
- validate
- lineage
- neighborhood
- path
- impact-analysis
- provenance-audit
- export

## Graph rules
Edges come only from explicit project dependency fields, explicit provenance references, or explicit supplied relation objects. v6.31 does not infer semantic, causal, or evidentiary relationships. External references remain boundary references unless they are explicitly present as project nodes. Dependency cycles are surfaced for review and never silently repaired.

## Authority boundary
The graph is a composition and inspection layer. It does not become a project persistence authority, domain object authority, execution authority, evidence authority, citation authority, or truth store. Existing domain authorities and PostgreSQL research state remain authoritative.

## Web deployment repair
The Library Web compose default is changed from port 8092 to the established production bind port 8095. Deployment scripts also explicitly preserve `SC_LIBRARY_WEB_BIND_PORT=8095` unless an operator supplies another value.

## Persistence
No database migration. No automatic graph persistence. No automatic relationship inference, remote execution, object import, claim promotion, evidence promotion, or truth promotion.

## Next
v6.32.0 — Research Review, Revision & Versioning System
