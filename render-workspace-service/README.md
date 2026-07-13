# Sustainable Catalyst Library Render Service v1.13.0

This optional FastAPI/PostgreSQL service supports two bounded Library workloads:

1. persistent account-workspace synchronization; and
2. queued server-side book and PDF production.

WordPress remains responsible for user identity, permissions, canonical publications, job history, and final Media Library files. The service never receives a WordPress database password.

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

Document jobs use the `sc-library-document-job/1.0` schema. Completed PDFs return an `sc-library-edition/1.0` manifest, SHA-256 checksum, diagnostics, source list, accessibility notes, and renderer version.

## Security

Requests from WordPress require:

- bearer authentication;
- a timestamp within five minutes;
- an HMAC-SHA256 signature over method, path, timestamp, and body; and
- an external owner identifier.

Use the same generated `SC_LIBRARY_SYNC_API_KEY` in the WordPress Library settings. A separate document-service key may be configured in WordPress when desired.

## Rendering boundaries

The renderer uses ReportLab for deterministic PDF generation. It supports headings, paragraphs, lists, code blocks, tables, source notes, remote images with size limits, transcriptions, basic indexes, metadata, page numbers, and frozen edition manifests.

It includes accessibility metadata and transcriptions but does **not** claim full PDF/UA conformance. Video is represented by durable links, timestamps, citations, and descriptive text rather than executable embedded media.

## Development

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements-dev.txt
PYTHONPATH=. pytest -q
uvicorn app.main:app --reload
```
