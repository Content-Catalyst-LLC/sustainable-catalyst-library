# Knowledge Library v6.2.0 — Unified Discovery & Research Navigation

v6.2.0 consolidates the first-party Library information architecture around a single Research surface.

## Product generations

- Library: 6.2.0
- Backend: 3.2.0
- Independent Library Web: 2.2.0
- Python/JavaScript/TypeScript SDK: 1.2.0
- API: v1 stable

## Unified navigation

The primary web navigation is Research, System and Account.

Research contains:
- overview;
- discovery pathways;
- faceted search;
- browser-local working set;
- record context;
- handoffs to saved Library research state.

`/search` and `/discover` remain supported compatibility routes. They resolve into the Research surface and are no longer separate top-level navigation products.

## Authority

Navigation is a composition layer. It does not create another search engine, another capability authority, or another saved-research store.

Retrieval remains owned by Python Retrieval Orchestration. Saved projects and collections remain owned by Python Research State. Record provenance/citations/evidence remain owned by the provenance service.

## WordPress

WordPress remains an optional adapter and has no discovery or navigation authority.

## Next

v6.3.0 — Research Projects & Saved Workspaces.
