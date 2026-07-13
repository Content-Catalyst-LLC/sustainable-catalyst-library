# Sustainable Catalyst Library Render Service v1.14.0

This optional FastAPI/PostgreSQL service supports three bounded Library workloads:

1. persistent account-workspace synchronization;
2. queued server-side book and PDF production; and
3. authorized media clip and poster production.

WordPress remains responsible for identity, permissions, canonical media metadata, rights records, public evidence reels, job history, and final Media Library files.

## Endpoints

### Workspace synchronization

- `PUT /api/v1/workspaces/{uuid}`
- `GET /api/v1/workspaces/{uuid}`
- `DELETE /api/v1/workspaces/{uuid}`
- `GET /api/v1/workspaces/{uuid}/history`

### Document production

- `POST /api/v1/documents/jobs`
- `GET /api/v1/documents/jobs/{uuid}`
- `GET /api/v1/documents/jobs/{uuid}/download`
- `POST /api/v1/documents/jobs/{uuid}/retry`
- `DELETE /api/v1/documents/jobs/{uuid}`

### Media production

- `POST /api/v1/media/jobs`
- `GET /api/v1/media/jobs/{uuid}`
- `GET /api/v1/media/jobs/{uuid}/video`
- `GET /api/v1/media/jobs/{uuid}/poster`
- `POST /api/v1/media/jobs/{uuid}/retry`
- `DELETE /api/v1/media/jobs/{uuid}`

Media jobs use `sc-library-media-job/1.0` and require a verified rights status.

## Request security

WordPress requests require:

- bearer authentication;
- a timestamp within five minutes;
- an HMAC-SHA256 signature over method, path, timestamp, and body; and
- an external owner identifier.

The browser never receives the service key or PostgreSQL connection string.

## Media boundaries

The media processor accepts direct public HTTPS media files. It validates the initial host and every redirect destination, rejects private/reserved addresses, limits source and output sizes, limits clip duration, and invokes FFmpeg without a shell.

It does not bypass authentication, DRM, paywalls, expiring platform controls, or download restrictions. Source files are never overwritten.

## PDF behavior

The ReportLab renderer supports structured matrices, boards, vector annotations, links, media timestamps, transcript excerpts, and QR access fallbacks. It does not claim complete PDF/UA conformance or interactive embedded video playback.

## Development

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements-dev.txt
PYTHONPATH=. pytest -q
uvicorn app.main:app --reload
```
