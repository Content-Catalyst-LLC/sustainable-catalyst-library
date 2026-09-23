# Knowledge Library v5.14.0 — Citation Graph & Scholarly Lineage

## Architectural boundary

Knowledge Library owns source citation acquisition, parsing/import, identifier normalization, exact local resolution, unresolved-reference preservation, citation graph indexing, and retrieval. Platform Core owns governed research lineage, evidence/provenance semantics, scholarly packages, synthesis, reasoning, and cross-product research objects.

## Backend v2.25.0

The backend adds `library_citations`, a stable SHA-256 citation identity, exact DOI/PMID/PMCID/ISBN/ISSN resolution, metadata citation import, bounded neighborhood traversal, Core object enrichment on resolved citation nodes, and explicit Platform Core scholarly-citation promotion.

### Public read contracts

- `GET /v1/citations/readiness`
- `GET /v1/citations/{record_id}?direction=outgoing|incoming|both`
- `GET /v1/citations/{record_id}/graph?depth=1..4&include_core=true|false`

### Signed write contracts

- `POST /v1/citations`
- `POST /v1/citations/{record_id}/import-metadata`
- `POST /v1/citations/core-handoff`

The Core handoff only queues the allowlisted operation `scholarly-citation.create` to `/v1/research/scholarly-packages/citations` using the existing backend-only Core write credential.

## Resolution policy

A citation is considered locally resolved only when it explicitly identifies another Library record or an exact persistent identifier matches a declared Library identifier. Unresolved citations are preserved as unresolved references. The Library does not resolve by title similarity and does not create citation edges from generative-model inference.

## Platform Core leverage

v5.14.0 requires the existing Core bridge capability discovery for `research_lineage` and `scholarly_interoperability`. Citation graph nodes can include current durable Core bindings. Promotion requires an operator-supplied Core scholarly package and project reference, and Platform Core becomes authoritative for the governed scholarly citation after promotion.
