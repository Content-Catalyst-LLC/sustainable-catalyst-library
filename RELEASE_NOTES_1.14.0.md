# Library v1.14.0 — Multimedia Studio and Video Snippet Production

## Added

- Native WordPress Multimedia Studio administration interface
- Media asset registry for video and audio
- WordPress attachment and controlled public HTTPS source support
- Rights, license, permission, provenance, citation, and accessibility fields
- Transcript, WebVTT, caption URL, and poster-time records
- Non-destructive clip definitions with millisecond boundaries
- Clip annotations, transcript excerpts, and caption text
- Evidence reels with explicit public/private visibility
- Public evidence-reel shortcode and REST representation
- Optional signed Render media-processing jobs
- Bounded FFmpeg clip and poster generation through `imageio-ffmpeg`
- WordPress Media Library import for completed outputs
- Job state, diagnostics, checksums, attempts, and errors
- Portable PostgreSQL/CSV/JSONL/JSON media entities
- PDF media links, selected segments, transcript excerpts, and QR fallbacks

## Data schemas

- `sc-library-media-asset/1.0`
- `sc-library-media-clip/1.0`
- `sc-library-media-reel/1.0`
- `sc-library-media-job/1.0`
- `sc-library-workspace/1.8`
- `sc-library-portable-export/1.4`

## Security and integrity

- Processing requires verified rights status.
- Render requests remain signed server-to-server.
- Render credentials are not exposed to browser JavaScript.
- Media downloads are size limited and HTTPS-only.
- Private, loopback, link-local, reserved, and redirect destinations are rejected.
- FFmpeg is invoked with an argument array and no shell.
- Source media is never overwritten.
- Public reels require deliberate public visibility.

## Boundaries

- The processor does not bypass DRM, paywalls, logins, or platform download controls.
- Streaming-platform page URLs are preserved as linked references unless an authorized direct media URL is supplied.
- Caption records are preserved; v1.14.0 does not claim universal burned-caption compatibility across every FFmpeg build.
- PDF output uses durable links and QR access instead of claiming interactive embedded playback.
- Live WordPress and Render processing require deployment verification after installation.
