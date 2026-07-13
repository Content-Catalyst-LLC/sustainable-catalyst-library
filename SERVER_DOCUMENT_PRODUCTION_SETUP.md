# Library v1.13.3 — Server Document Production Setup

Library v1.13.3 adds an optional server-side book and PDF pipeline. WordPress remains the source of truth for publications, users, permissions, job history, frozen edition records, and final files. Render performs the computationally expensive PDF rendering.

## What works without Render

The existing Book Builder remains available and can still:

- create browser previews;
- download portable HTML;
- export editable book JSON; and
- use the browser's Print / Save as PDF workflow.

Render is required only for queued server PDFs, deterministic pagination, server diagnostics, frozen edition manifests, checksums, and automatic WordPress Media Library import.

## Deploy or upgrade the Render service

The repository contains `render-workspace-service/`, which now serves both persistent workspaces and document production.

Required environment variables:

```text
DATABASE_URL
SC_LIBRARY_SYNC_API_KEY
SC_LIBRARY_MAX_WORKSPACE_MB=8
SC_LIBRARY_MAX_DOCUMENT_REQUEST_MB=8
SC_LIBRARY_MAX_PDF_MB=20
SC_LIBRARY_DOCUMENT_MAX_ATTEMPTS=3
SC_LIBRARY_ALLOW_REMOTE_IMAGES=true
```

The included `render.yaml` declares these settings.

## Configure WordPress

Open **SC Library** and locate **Server-side document production**.

1. Enable server-side document production.
2. Enter the Render service URL.
3. Enter the same server key used by `SC_LIBRARY_SYNC_API_KEY`.
4. Enable automatic Media Library import for the initial test.
5. Save settings.

When the document URL and key fields are left empty, v1.13.0 reuses the v1.12 workspace synchronization URL and key.

## Test sequence

1. Sign in to WordPress.
2. Open the Library Book Builder.
3. Create a small book with one or two short sections. Add a Matrix, board, or annotated page for a structured-artifact test.
4. Open **PDF / Export**.
5. Select **Create server-rendered PDF**.
6. Open **SC Library → Document Production**.
7. Refresh the job until it becomes **Imported**.
8. Open the frozen PDF from the dashboard.
9. Compare the content hash, PDF checksum, section count, and edition manifest.

## Data and storage boundaries

The Render service temporarily stores queued request packets and generated PDF bytes in PostgreSQL. WordPress can import the completed file into the Media Library. The WordPress edition record stores:

- book and job identifiers;
- source-content hash;
- PDF SHA-256 checksum;
- renderer version;
- frozen edition manifest;
- WordPress attachment ID; and
- generation timestamp.

The portable-data export includes job metadata and edition manifests, but not PDF binary data.

## Accessibility boundary

The renderer includes document title, author, subject, language, source notes, alt-text notes, structured Matrix tables, scaled board diagrams, vector handwriting and shapes, handwriting transcriptions, and text-based indexes. It does not claim full PDF/UA conformance. A future accessibility hardening release should validate tagged-PDF structure with dedicated assistive-technology testing.

## Failure recovery

The Document Production dashboard supports:

- manual status refresh;
- retry within the configured attempt limit;
- diagnostic inspection;
- browser PDF fallback; and
- deletion of stale job records without deleting imported PDF editions.

An unavailable Render service does not take the Library, Notebook, Book Builder, or browser PDF workflow offline.
