# Sustainable Catalyst Library v6.10.0 — Research Package Publishing & Reproducible Exports

## Release generation

- Library: 6.10.0
- Python/FastAPI backend: 3.10.0
- Independent Web: 2.10.0
- Python SDK / JavaScript client: 1.10.0
- Optional WordPress adapter: 6.10.0
- Database migration: none

## Purpose

v6.10.0 adds a publishing and portable-export composition layer over the existing Python Research Package & Reproducibility Service and the content-addressed artifact store. It does not create a second package store, a second artifact authority, or an external publishing service.

## Capabilities

- deterministic portable research ZIP exports;
- canonical `manifest.json` with SHA-256 file identities;
- `SHA256SUMS` checksum index;
- JSON and NDJSON record exports;
- JSON and CSV dataset exports;
- scientific-literature JSON export;
- existing research-package manifest inclusion;
- generated `CITATION.cff` and `README.md`;
- fixed ZIP timestamps, sorted paths, fixed permissions and ZIP_STORED mode for reproducible archive bytes;
- stateless preview and export APIs;
- explicit signed persistence through the existing artifact store;
- independent Web working-set preview and portable-ZIP download.

## API v1

- `GET /api/library/v1/research-package-publishing`
- `GET /api/library/v1/research-package-publishing/readiness`
- `GET /api/library/v1/research-package-publishing/formats`
- `POST /api/library/v1/research-package-publishing/preview`
- `POST /api/library/v1/research-package-publishing/export`
- `POST /api/library/v1/research-package-publishing/validate`
- `POST /api/library/v1/admin/research-package-publishing/persist` — signed write

## Architectural guardrails

- The existing Python research-package service remains package-manifest authority.
- The existing content-addressed artifact store remains persisted-byte authority.
- Previewing or generating an export does not publish it externally.
- Persisting an export stores it only in the existing Library artifact system.
- Export integrity does not establish truth, evidence strength, source validity, causality, or endorsement.
- No automatic Platform Core promotion.
- WordPress is optional and not authoritative.

## Additional consistency repair

The independent research-interface composition contract is synchronized to the current 6.10/3.10/Web 2.10/SDK 1.10 generation so unified-navigation readiness no longer compares the current navigation generation against the historical 6.4 interface marker. This changes version identity only; the established retrieval, catalog, provenance and research-state services remain the underlying authorities.
