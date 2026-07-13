# Library v1.18.1 — Embedded Document Records and Full-Text PDF Indexing

## Added

- Native Foundation Document record type
- PDF Media Library attachment selector
- Bundled PDF.js inline viewer
- Explicit open and download controls
- Full-text browser extraction stored page by page
- Exact-page Library search matches
- Research Librarian page-aware synchronization
- Metadata, citation, version, and related-record controls
- Extraction progress, retries, and failure diagnostics
- Mobile reader fallback
- Migration of legacy Foundation PDF links
- Public REST and developer API routes
- PostgreSQL-ready Foundation Document export entities

## Schemas

- Foundation Document: `sc-library-foundation-document/1.0`
- PDF extraction: `sc-library-pdf-extraction/1.0`
- Portable export: `sc-library-portable-export/1.9`

## Data boundary

PDF binaries stay in the WordPress Media Library or at an explicitly recorded source URL. Extracted page text, metadata, and version manifests are portable; PDF binaries are not embedded in PostgreSQL, CSV, JSONL, or JSON exports.
