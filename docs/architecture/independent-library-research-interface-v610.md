# Knowledge Library v6.1.0 — Independent Library Research Interface

v6.1.0 is the first research-facing application release of the independent 6.x Library line.

## Product generations

- Library: 6.1.0
- Backend: 3.1.0
- Independent Library Web: 2.1.0
- Python/JavaScript/TypeScript SDK: 1.1.0
- API: v1 stable

## Interface composition

The research interface composes existing authoritative services rather than duplicating them:

- retrieval/search -> Python Retrieval Orchestration
- research objects -> Python Catalog Service
- provenance/citations/evidence graph -> Python Provenance Service
- saved projects/collections -> Python Research State Service
- identity/session -> Library-native Identity Service

## Web research workspace

`/research` provides faceted discovery, search modes, sorting, a temporary browser-local working set, and record context with provenance/citation/evidence summaries.

The browser-local working set is convenience state only. It is not authoritative and is not a saved Library project or collection until an explicit API write occurs.

## WordPress

WordPress remains an optional adapter and has no research-interface composition authority.

## Next

v6.2.0 — Unified Discovery & Research Navigation.
