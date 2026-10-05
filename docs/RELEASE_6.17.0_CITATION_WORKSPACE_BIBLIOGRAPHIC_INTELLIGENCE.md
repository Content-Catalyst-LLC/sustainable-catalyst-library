# Library v6.17.0 — Citation Workspace & Bibliographic Intelligence

## Generation

- Library 6.17.0
- Backend 3.17.0
- Web 2.17.0
- Python SDK 1.17.0
- JavaScript/TypeScript client 1.17.0
- API v1 stable
- Database migration: none
- WordPress: optional/non-authoritative

## Canonical workspace

`/research/citations`

## Purpose

v6.17 adds a researcher-facing citation and bibliography workspace without creating a second durable citation authority. The existing Python/PostgreSQL provenance and citation service remains authoritative for durable Library citation edges. The new workspace composes bibliographic metadata, identifier normalization, duplicate review, bibliography sets, and portable exports above that foundation.

## Capabilities

- Normalize bibliographic items into a CSL-compatible metadata envelope.
- Normalize DOI, ISBN, ISSN, PMID, PMCID, arXiv and URL identifiers.
- Generate deterministic local citation keys while explicitly not claiming global uniqueness.
- Preserve links from bibliographic items to Library records and v6.16 annotation IDs.
- Compose research bibliographies with deterministic display ordering.
- Detect duplicate candidates using exact identifiers and a conservative title/first-author/year rule.
- Export CSL-compatible JSON, BibTeX and RIS.
- Preview a handoff to the existing durable citation authority without writing automatically.

## Guardrails

Citation presence does not imply evidentiary support. Citation counts are not quality scores. Identifier presence does not establish validity, and DOI presence does not establish peer review. Metadata completeness is descriptive, not evaluative. Duplicate candidates are never automatically merged. Bibliography order does not imply rank. Annotation references are not automatically promoted to citations. Export does not imply import, and the workspace does not automatically create durable citation edges, evidence, truth status, or Platform Core objects.

## Persistence

The browser collection is a convenience layer and is non-authoritative. No new database table or migration is introduced. Existing durable citation authority is preserved unchanged.

## Next release

Library v6.18.0 — Corpus & Computational Linguistics Workspace.
