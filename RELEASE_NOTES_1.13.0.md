# Sustainable Catalyst Library v1.13.0

## Server-Side Book, PDF, and Document Production

This release upgrades the v1.7 browser Book Builder with an optional Render-backed production pipeline while preserving browser printing as a fallback.

### WordPress

- queued document-job registry;
- frozen edition registry;
- signed Render requests;
- automatic job polling;
- retry limits and diagnostics;
- automatic PDF import into the Media Library;
- content hashes and PDF SHA-256 checksums;
- stable edition manifests;
- Document Production administration dashboard;
- `[sc_library_document_production]` shortcode; and
- portable PostgreSQL export of jobs and editions without PDF binaries.

### Book Builder

- server-rendered PDF action;
- table of contents option;
- source and citation handling;
- figure, table, equation, and source index option;
- accessibility transcription and alt-text option;
- browser PDF fallback; and
- links to production status and frozen editions.

### Render service

- PostgreSQL-backed job queue;
- ReportLab PDF renderer;
- deterministic page size and margins;
- title pages, front matter, section breaks, headers, footers, and page numbers;
- headings, paragraphs, lists, blockquotes, code blocks, tables, and bounded remote images;
- structured Technical Translation Matrix tables;
- scaled Whiteboard and Chalkboard diagrams with cards, relationships, and vector ink;
- vector annotation strokes, shapes, anchored notes, and accessible handwriting transcriptions;
- source notes, citations, transcriptions, and document indexes;
- edition manifests, diagnostics, output size, and checksums;
- retry and download endpoints; and
- recovery of interrupted processing jobs to the queued state on service startup.

### Schemas

```text
Document job:    sc-library-document-job/1.0
Frozen edition:  sc-library-edition/1.0
Portable export: sc-library-portable-export/1.3
Workspace:       sc-library-workspace/1.7
```

### Important boundaries

- WordPress remains the identity, permission, publishing, and final-file authority.
- Render is optional.
- Video is represented through durable links, timestamps, citations, and descriptions in server PDFs.
- The generated PDF includes accessibility metadata and transcriptions but does not claim full PDF/UA conformance.
