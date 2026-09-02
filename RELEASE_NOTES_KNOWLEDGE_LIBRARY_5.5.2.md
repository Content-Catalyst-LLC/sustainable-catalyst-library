# Sustainable Catalyst Library v5.5.2

## Backend Operations & Recovery

v5.5.2 adds an explicit operations and recovery layer to the Python research-intelligence backend established in v5.5.0 and ingestion-hardened in v5.5.1.

### Added

- signed backend operations status endpoint;
- signed WordPress-vs-backend integrity audit;
- missing-record detection;
- stale-record detection based on authoritative WordPress modification time;
- orphaned-backend-record detection;
- chunkless-record detection for non-empty documents;
- one-click repair of verified missing/stale/chunkless records;
- guarded pruning of exact orphan IDs returned by the last audit;
- targeted WordPress post-ID reindex;
- Library Collection reindex;
- operation IDs and completed-at lineage for bulk syncs;
- last-successful-sync retention;
- recent recovery-operation history;
- backend recent-ingest and index-coverage diagnostics.

### Safety model

WordPress remains authoritative for record existence and source modification time. The backend never guesses what should be deleted. Orphan pruning only accepts explicit record IDs, is source-scoped, and is invoked from a nonce-protected administrator action after an integrity audit.

### Compatibility

- WordPress plugin: **5.5.2**
- Python backend: **1.0.2**
- ingest schema: **sc-library-backend-ingest/1.0** unchanged
- PostgreSQL schema migration: **none**
- database/role: unchanged
- Caddy hostname: unchanged
- localhost port: **127.0.0.1:8087** unchanged
- Docker network: **sc-internal** unchanged
- existing `.env`: preserve in place
