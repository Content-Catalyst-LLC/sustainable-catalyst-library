# Sustainable Catalyst Library v1.14.1

Library v1.14.1 repairs public record-card sizing and responsive rendering so long titles, excerpts, resource badges, and expanded research actions remain readable on desktop, tablet, mobile, and printed/PDF output. It retains the complete v1.14.0 Multimedia Studio and all earlier Library capabilities.


## Public record-card repair

The public Knowledge Records interface now uses a full-width content column and a separate wrapping footer for resource badges and research actions. Long titles and excerpts remain horizontal and readable, expanded action sets cannot squeeze the content column, mobile controls reflow into compact grids, and print output suppresses interactive buttons.

WordPress remains the canonical store for media metadata, rights, permissions, public reel presentation, job history, and imported output files. The included FastAPI/PostgreSQL service is optional and is used only when an editor explicitly requests a rendered clip.

## Multimedia Studio

Open:

```text
SC Library → Multimedia Studio
```

Core capabilities:

- Register WordPress Media Library video or audio attachments
- Register approved public HTTPS source references when enabled
- Record ownership, license, permission, public-domain, Creative Commons, or documented fair-use status
- Preserve source citations, provenance, accessibility descriptions, transcripts, WebVTT, caption URLs, and poster timing
- Define clips by start, end, and poster timestamps without changing the source asset
- Add clip descriptions, transcript excerpts, captions, and structured annotations
- Build ordered evidence reels
- Publish deliberate public read-only reels through a shortcode
- Submit authorized direct-media sources to the optional Render processor
- Import completed MP4 clips and poster frames into the WordPress Media Library
- Track job progress, retries, diagnostics, checksums, and errors

Schemas:

- `sc-library-media-asset/1.0`
- `sc-library-media-clip/1.0`
- `sc-library-media-reel/1.0`
- `sc-library-media-job/1.0`
- `sc-library-workspace/1.8`
- `sc-library-portable-export/1.4`

## Shortcodes

```text
[sc_library_multimedia_studio]
[sc_library_evidence_reel id="REEL-UUID"]
```

The studio shortcode requires an editor account. The evidence-reel shortcode renders only reels explicitly marked public.

## Media-processing boundary

The processor accepts direct, publicly reachable HTTPS media files. It does not bypass paywalls, authentication, streaming-platform protections, DRM, or download restrictions. YouTube, Vimeo, podcast pages, and similar web pages can be preserved as linked source records and timestamped references, but they are not treated as downloadable media files unless the user supplies an authorized direct file URL.

The source asset is never overwritten. Processing creates a new bounded clip and optional poster frame.

## PDF and book editions

Frozen server PDFs represent media with:

- durable clickable links;
- selected time ranges;
- descriptions and source notes;
- transcript excerpts; and
- QR access fallbacks.

The PDF renderer does not claim that interactive playback is embedded in a printed document.

## Portable data

The PostgreSQL, CSV, JSONL, and JSON export studio now includes normalized:

- `media_assets`
- `media_clips`
- `media_reels`
- `media_jobs`

Media binaries are not embedded in portable research-data exports. WordPress attachment IDs, public URLs, checksums, rights metadata, transcripts, and processing history are preserved.

## Retained systems

v1.14.1 retains:

- Large-Library Index Tools and cursor reconciliation
- Persistent workspaces and account revisions
- Content Planner and release coordination
- Server book and PDF production
- Research Notebook, matrices, boards, annotations, and books
- PostgreSQL and portable export

See `PUBLIC_RECORD_LAYOUT_REPAIR_SETUP.md`, `RELEASE_NOTES_1.14.1.md`, `MULTIMEDIA_STUDIO_SETUP.md`, and `render-workspace-service/README.md`.
