# Multimedia Studio Setup — Library v1.14.0

## 1. Install the WordPress plugin

Upload `sustainable-catalyst-library-v1.14.0.zip` through **Plugins → Add New Plugin → Upload Plugin** and choose **Replace current with uploaded**.

Open **SC Library** and confirm:

- Multimedia Studio is enabled.
- Remote HTTPS media references remain disabled unless they are needed.
- Maximum source size and clip duration match the site’s hosting limits.
- Automatic output import is enabled only when completed clips should enter the WordPress Media Library.

The Multimedia Studio works as a metadata and linked-excerpt system without Render.

## 2. Open the studio

Navigate to:

```text
SC Library → Multimedia Studio
```

The studio has four areas:

1. **Assets** — source media, rights, transcripts, captions, accessibility, and provenance.
2. **Clips** — non-destructive start/end definitions, poster time, transcript excerpts, and annotations.
3. **Evidence Reels** — ordered groups of clips for public or private presentation.
4. **Processing Jobs** — optional Render job state, output, diagnostics, and checksums.

## 3. Register an authorized asset

Prefer a WordPress Media Library attachment. Select the attachment and complete:

- title and description;
- video or audio type;
- known duration;
- rights status;
- rights holder and license information;
- source citation;
- transcript or WebVTT when available;
- caption URL;
- poster time and accessibility description;
- private or public visibility.

Processing is blocked while rights status is **Rights not yet verified**.

Valid processing statuses are:

- Owned by Sustainable Catalyst
- Licensed for this use
- Permission granted
- Public domain
- Creative Commons
- Documented fair-use excerpt

Rights metadata is a recordkeeping aid, not legal advice.

## 4. Create a clip definition

Choose an asset, then enter:

- clip title and description;
- start time;
- end time;
- poster-frame time;
- transcript excerpt;
- caption text;
- structured annotations;
- visibility.

The clip definition does not alter or duplicate the source media. It remains useful even when no server rendering is configured.

## 5. Build an evidence reel

Create a reel, select clips in order, choose linked or rendered presentation, and set visibility.

For a deliberately public reel, copy its generated shortcode:

```text
[sc_library_evidence_reel id="REEL-UUID"]
```

Private reels and standalone private clips are not available through the public reel endpoint.

## 6. Optional Render deployment

The repository includes `render-workspace-service/`, which now supports workspaces, PDFs, and bounded media processing.

Required environment variables:

```text
DATABASE_URL
SC_LIBRARY_SYNC_API_KEY
SC_LIBRARY_ALLOW_REMOTE_MEDIA=true
SC_LIBRARY_MEDIA_MAX_SOURCE_MB=500
SC_LIBRARY_MEDIA_MAX_OUTPUT_MB=500
SC_LIBRARY_MEDIA_MAX_CLIP_MINUTES=30
SC_LIBRARY_MEDIA_MAX_ATTEMPTS=3
```

Optional document and workspace variables remain supported.

In WordPress, enter the Render service URL and matching key under the Multimedia Studio settings. Leaving the media-specific fields empty reuses the configured Library document/workspace service.

## 7. Supported processing input

The Render processor expects an authorized, direct, publicly reachable HTTPS media file.

It does not:

- log in to third-party services;
- bypass DRM, paywalls, tokens, or access controls;
- scrape streaming pages;
- download from YouTube, Vimeo, or similar platforms merely from a page URL;
- modify the source asset.

External platform pages remain useful as linked clips with timestamps, citations, transcript excerpts, and QR/PDF fallbacks.

## 8. PDF behavior

Server books and PDFs preserve media as links rather than executable embedded players. Media sections can contain:

- source URL;
- selected segment;
- transcript excerpt;
- accessibility note;
- QR access fallback.

## 9. Portable export

Open **SC Library → Portable Data Export** and select **Multimedia assets, clips, reels, and processing jobs**.

Available formats:

- PostgreSQL SQL
- CSV bundle
- JSONL bundle
- JSON snapshot

The export preserves metadata but not video/audio binary payloads.

## 10. Recommended first test

1. Register a short video attachment you own.
2. Mark its rights status as owned.
3. Create a 10–20 second clip definition.
4. Create a private evidence reel containing that clip.
5. Confirm the linked reel works without Render.
6. Deploy or update Render.
7. Process the clip.
8. Confirm the MP4 and poster appear in the WordPress Media Library.
9. Export the multimedia scope as JSON and verify all four entities.
