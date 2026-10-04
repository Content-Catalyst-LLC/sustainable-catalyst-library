# Sustainable Catalyst Library v6.12.0 — Historical Archive & Primary-Source Intelligence

Library **6.12.0** / backend **3.12.0** / Web **2.12.0** / SDK **1.12.0** / API **v1 stable**.

This release makes historical archives and primary sources first-class Library research objects without collapsing archival description, digitization, transcription, translation, or historical interpretation into a single undifferentiated document.

## Capability added

The Python backend now provides:

- a historical source-type registry for government records, personal papers, manuscripts, periodicals, oral histories, photographs, maps, audiovisual material, ephemera, historical registers/datasets, artifact documentation, and other candidate primary sources;
- a canonical primary-source object that preserves repository identity, archival hierarchy, fonds/collection/series/folder/item context, shelfmarks, persistent identifiers, original language/script, rights/access metadata, and digital-surrogate metadata;
- explicit historical date assertions that preserve qualifiers such as exact, circa, before, after, range, decade, century, and unknown rather than manufacturing false precision;
- original-object → surrogate → OCR/HTR → transcription → translation → editorial derivation lineage;
- source-criticism observations and research questions without authenticity certification, truth scoring, or automated interpretive conclusions;
- cross-source comparison that exposes differences without automatically resolving disagreement;
- historical archive search plans composed over the v6.11 Institutional Repository Federation with archive-specific local filters and explicit protocol limitations;
- uncertainty-preserving primary-source timelines;
- reproducible primary-source research packets with deterministic SHA-256 fingerprints;
- explicit ingestion handoffs into the Library source-ingestion authority.

## Authority boundaries

Python/FastAPI remains authoritative for historical archive and primary-source semantics. Institutional Repository Federation remains the repository execution authority. OCR/HTR/Transcription Lineage remains the authority for machine- and human-derived text transformations. Original-language source representations remain canonical; translations are derived representations.

Archive visibility does **not** imply authenticity, truth, accuracy, endorsement, rights clearance, or evidentiary weight. A “primary source” label does **not** establish that a claim is correct. OCR, HTR, transcription, and translation output is never represented as identical to the original object or original text. Cross-source disagreement is surfaced, not auto-resolved.

No external archive is harvested automatically. No record is automatically imported, promoted to evidence, promoted to truth, or promoted to Platform Core.

## API v1 surfaces

- `GET /api/library/v1/historical-archives`
- `GET /api/library/v1/historical-archives/readiness`
- `GET /api/library/v1/historical-archives/schemas`
- `GET /api/library/v1/historical-archives/source-types`
- `POST /api/library/v1/historical-archives/dates/normalize`
- `POST /api/library/v1/historical-archives/primary-sources/normalize`
- `POST /api/library/v1/historical-archives/primary-sources/provenance`
- `POST /api/library/v1/historical-archives/primary-sources/analyze`
- `POST /api/library/v1/historical-archives/primary-sources/compare`
- `POST /api/library/v1/historical-archives/search/plan`
- `POST /api/library/v1/historical-archives/timeline`
- `POST /api/library/v1/historical-archives/packets`
- `POST /api/library/v1/historical-archives/handoff`

No database migration is required. WordPress remains an optional adapter and receives no historical-archive domain authority.
