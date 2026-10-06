# Library v6.30.0 — Unified Research Project Workspace

**Generations:** Library 6.30.0 · Backend 3.30.0 · Web 2.30.0 · SDK 1.30.0 · API v1 stable

**Web:** `/research/project`
**API:** `/api/library/v1/research-project-workspace`

The workspace provides one project-centered composition surface across research questions, investigations, sources, claims, evidence, datasets, statistical results, places, events, annotations, citations, corpora, entities, synthesis, packages, publications, graphs, artifacts, Workspace handoffs, and cross-product certification objects.

It is **not** a new persistence or execution authority. PostgreSQL research state and each originating domain service remain authoritative. No database migration is required. The workspace does not automatically persist components, execute another product, import results, promote evidence/claims/truth, or promote objects into Platform Core.

Operations: `compose`, `validate`, `inventory`, `authority-audit`, `dependency-summary`, `handoff-manifest`, `export`.

Next: **v6.31.0 — Research Dependency & Lineage Graph**.
