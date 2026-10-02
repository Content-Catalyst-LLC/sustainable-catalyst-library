# Knowledge Library v5.74.0 — Python Provenance, Citation & Evidence Graph Service

Python/FastAPI is the authoritative service boundary for Library provenance, citations, and evidence graph assembly. This release reuses the existing PostgreSQL record, version, citation, edge, normalization-lineage, and Platform Core binding structures instead of creating a parallel graph store.

## Authority

- Record/version provenance and ingestion lineage: Python/PostgreSQL.
- Citation creation, exact identifier resolution, unresolved citation preservation, citation traversal: Python.
- Evidence graph assembly across explicit Library relationships and citations: Python.
- Platform Core bindings remain governed by Platform Core and are descriptive enrichment in Library graph responses.
- WordPress remains presentation and API-client compatibility only.

## Guardrails

Citation confidence is not a truth probability. A graph relationship does not imply causality. Unresolved references are preserved rather than guessed. Citation edges are declared or imported, not silently inferred by an LLM. Provenance lineage describes origin and transformation history; it is not a truth certificate. No automatic Platform Core promotion occurs.
