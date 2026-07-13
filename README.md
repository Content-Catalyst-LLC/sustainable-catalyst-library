# Sustainable Catalyst Library v1.13.2

Library v1.13.2 repairs the **Index Scanner administration route** from v1.13.1 while retaining the complete resumable scanner and all v1.13.0 server-side document-production capabilities.

The Library now has a dedicated resumable scanner with per-post-type diagnostics, missing and outdated record repair, single-record reindexing, relationship repair, stale cleanup, and downloadable scan logs. Indexing remains entirely WordPress-local and does not depend on Render, PostgreSQL, workspaces, or document production.

## Index Scanner

Open:

```text
SC Library → Index Scanner
```

Included scanner capabilities:

- Complete safe rebuild
- Missing-only and outdated-only scans
- Resumable batch progress stored in WordPress
- Per-post-type counts and freshness diagnostics
- Single-record repair by ID or URL
- Stale-record and relationship cleanup
- Full-text and daily-reconciliation health checks
- Downloadable JSON scan logs
- Synchronous rebuild fallback on the main settings page

See `INDEX_SCANNER_SETUP.md`.

## Retained v1.13.0 document production
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

## Scanner REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/scanner/status`
- `/wp-json/sustainable-catalyst/v1/library/scanner/start`
- `/wp-json/sustainable-catalyst/v1/library/scanner/step`
- `/wp-json/sustainable-catalyst/v1/library/scanner/pause`
- `/wp-json/sustainable-catalyst/v1/library/scanner/resume`
- `/wp-json/sustainable-catalyst/v1/library/scanner/cancel`
- `/wp-json/sustainable-catalyst/v1/library/scanner/repair`
- `/wp-json/sustainable-catalyst/v1/library/scanner/record`

## Render service

The optional service remains in `render-workspace-service/` and now handles both workspace synchronization and document production.

See:

- `SERVER_DOCUMENT_PRODUCTION_SETUP.md`
- `WORKSPACE_SYNC_SETUP.md`
- `render-workspace-service/README.md`


## Index Scanner Administration Route Repair

The SC Library parent menu is registered before the scanner submenu, preventing WordPress from rejecting `/wp-admin/admin.php?page=sc-library-scanner` as an unregistered page.
