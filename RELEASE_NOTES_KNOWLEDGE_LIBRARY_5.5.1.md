# Sustainable Catalyst Library v5.5.1

## Ingestion Hardening & Adaptive Reindex Recovery

v5.5.1 is a focused production-hardening release for the Python research-intelligence backend introduced in v5.5.0.

### Fixed

- fixed bulk reindex failure when a fixed 100-record payload exceeded the backend request-body limit;
- prevents HTTP 413 from terminating or poisoning an otherwise valid reindex;
- separates `changed`, `unchanged`, `completed`, and `failed` counts;
- avoids blind retries for payload-size failures;
- preserves failed record IDs for targeted resume.

### Added

- adaptive record-count batching;
- encoded payload-byte preflight;
- recursive preflight splitting;
- recursive server-signaled 413 splitting;
- bounded transient retry policy;
- resumable failed-record checkpoint;
- compact single-record transport fallback;
- backend-generated chunk fallback;
- ingest-limit reporting in backend health;
- request-limit response headers;
- detailed sync telemetry in WordPress admin.

### Compatibility

- WordPress plugin: **5.5.1**
- Python backend: **1.0.1**
- ingest schema: **sc-library-backend-ingest/1.0** unchanged
- database schema migration: **none**
- reverse-proxy hostname/port: unchanged
- PostgreSQL database/role: unchanged
- existing backend `.env`: preserve in place

### Default client safeguards

- target leaf batch: 25 records
- target encoded payload: 6 MB
- transient retries: 2

The server request ceiling remains independently configurable. The client is intentionally more conservative than the server.
