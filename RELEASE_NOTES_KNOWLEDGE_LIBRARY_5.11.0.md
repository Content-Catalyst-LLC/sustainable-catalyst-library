# Sustainable Catalyst Library v5.11.0 — Private Organizational Knowledge Foundation

Release class: enterprise/private knowledge foundation  
Backend: v2.1.0  
Database: additive PostgreSQL schema migration required  
New credentials: none

## Added

- Dedicated organization-scoped private knowledge tables, separate from the public Library corpus.
- Private source registry with source owner, canonical URL, metadata, and provenance.
- Private record model with original format, access level/scopes, project and department context, retention labels, identifiers, provenance, content hash, and revision.
- Deterministic organization-scoped private record identity.
- Private version history and ingest lineage.
- Privacy-minimized access audit events.
- Signed private ingest/search/read/version/handoff backend routes.
- WordPress organization configuration and authenticated REST proxy.
- `[sc_private_organizational_knowledge]` member-only interface.
- Research Librarian, Workspace, and Lab handoff policy packets.

## Security and governance boundaries

- Private records are not stored in `library_records`.
- Public search code does not query `library_private_*` tables.
- Every data-bearing private backend route requires the existing signed backend credential.
- WordPress supplies organization and actor scope server-side.
- Cross-organization search and identity merging are disabled.
- Restricted/project records require scope intersection.
- The backend API key is never emitted to JavaScript.
- Private records are never automatically published or added to public Research Network surfaces.
- Search audit logs retain a fingerprint, not the raw search query.

## Ingestion scope

v5.11.0 accepts normalized extracted-text packets from internal documents and data sources. Original formats can be recorded as PDF, DOCX, TXT, Markdown, CSV, JSON, HTML, or other. Existing Library conversion/OCR paths can supply extracted text. A new binary parser is not claimed by this release.

## Database migration

The backend startup schema initializer creates new `library_private_*` tables and indexes with `CREATE TABLE/INDEX IF NOT EXISTS`. Existing public Library tables are not rewritten or destructively migrated.

## Compatibility

v5.10.0 Institutional Research Network II, v5.9.x biomedical evidence graph/reliability, v5.8.x biomedical/clinical layers, Dynamic Explorer, homepage research console, and Publications behavior are retained.

## Validation

- 22 new v5.11.0 tests passed.
- 100 retained institutional/biomedical tests passed with 12 historical release-identity deselections.
- 26 retained public-interface tests passed with 3 historical release-identity deselections.
- Python compileall, PHP syntax, and JavaScript syntax gates passed.
