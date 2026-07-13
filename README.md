# Sustainable Catalyst Library v1.13.0

Library v1.13.0 adds **Server-Side Book, PDF, and Document Production** to the Sustainable Catalyst knowledge system.

The existing browser Book Builder remains available. Signed-in users can now submit normalized book editions to the optional Render service, monitor queued rendering, retry failures, inspect diagnostics, import completed PDFs into the WordPress Media Library, and preserve frozen edition manifests and checksums.

## Included

- Browser and server PDF production paths
- WordPress document-job registry
- Frozen edition registry
- Signed Render job submission and polling
- Automatic Media Library import
- Stable pagination and page numbering
- Cover, front matter, table of contents, chapters, conclusion, and manifest
- Headings, lists, code blocks, blockquotes, tables, and bounded remote images
- Structured Translation Matrix tables, Whiteboard and Chalkboard diagrams, and vector annotation ink
- Source notes, citations, accessibility transcriptions, and basic document indexes
- SHA-256 content and output checksums
- Renderer diagnostics and retry controls
- Portable export schema v1.3 with document-job and edition entities
- Optional FastAPI/PostgreSQL rendering service

## WordPress administration

Open:

```text
SC Library → Document Production
```

Configure the service under **SC Library → Server-side document production**.

## Shortcodes

```text
[sc_library_book_builder]
[sc_library_document_production]
```

The normal Library and Notebook shortcodes continue to work.

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/documents/status`
- `/wp-json/sustainable-catalyst/v1/library/documents/jobs`
- `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}`
- `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/refresh`
- `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/retry`
- `/wp-json/sustainable-catalyst/v1/library/documents/editions`

## Render service

The optional service remains in `render-workspace-service/` and now handles both workspace synchronization and document production.

See:

- `SERVER_DOCUMENT_PRODUCTION_SETUP.md`
- `WORKSPACE_SYNC_SETUP.md`
- `render-workspace-service/README.md`
