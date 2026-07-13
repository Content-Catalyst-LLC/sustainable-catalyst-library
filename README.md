# Sustainable Catalyst Library v1.13.4

Library v1.13.4 repairs the remaining large-library scope problem by comparing the saved scan configuration against a raw WordPress database inventory and providing both browser-driven and server-side reconciliation paths.

Published discovery now comes directly from the WordPress posts table. The scanner is not affected by `pre_get_posts`, theme archive rules, front-end query limits, or search customizations, and it never stores the complete post-ID list in a WordPress option.

## Large-Library Index Scanner

Open:

```text
SC Library → Index Scanner
```

Included scanner capabilities:

- Direct database discovery by ascending WordPress post ID
- Bounded cursor batches from 25 to 500 records
- Automatic discovery of Posts, Pages, and editorial custom post types
- Recommended-type selection and configuration persistence
- Complete, missing-only, outdated-only, and repair modes
- Pause, resume, cancel, and scanner-state reset
- Dedicated scan audit table with every post ID and outcome
- Explicit exclusion reasons separated from failures
- Per-post-type published, eligible, excluded, indexed, missing, and outdated counts
- Completion accounting that must reconcile before a clean completion
- Full JSON audit report
- Cursor-based synchronous fallback rebuild
- No dependency on Render, PostgreSQL, workspaces, or document production

Scanner state schema: `sc-library-index-scan/2.0`  
Scan report schema: `sc-library-index-scan-log/2.0`

See `INDEX_SCANNER_SETUP.md` and `RELEASE_NOTES_1.13.4.md`.

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
- `/wp-json/sustainable-catalyst/v1/library/scanner/reset`
- `/wp-json/sustainable-catalyst/v1/library/scanner/repair`
- `/wp-json/sustainable-catalyst/v1/library/scanner/record`

## Render service

The optional service remains in `render-workspace-service/` and now handles both workspace synchronization and document production.

See:

- `SERVER_DOCUMENT_PRODUCTION_SETUP.md`
- `WORKSPACE_SYNC_SETUP.md`
- `render-workspace-service/README.md`

