# Sustainable Catalyst Library v6.13.0 — Historical Archives Research Workspace

Library v6.13.0 turns the v6.12 Historical Archive & Primary-Source Intelligence services into a first-class standalone research workspace at `/research/archives`.

## Generations

- Library: 6.13.0
- Python backend: 3.13.0
- Standalone web: 2.13.0
- Python SDK: 1.13.0
- JavaScript client: 1.13.0
- API: v1, stable

## Workspace capabilities

The release composes the existing v6.12 archival intelligence layer into user-facing research workflows:

- repository-aware archive search planning and explicit bounded execution;
- primary-source normalization and source-criticism inspection;
- archival hierarchy, collection, series, folder and shelfmark context;
- original/surrogate/OCR/HTR/transcription/translation lineage inspection;
- side-by-side source comparison without automatic disagreement resolution;
- uncertainty-preserving historical timelines;
- deterministic primary-source packet previews;
- explicit ingestion-handoff previews that do not execute automatically;
- standalone browser route `https://library.sustainablecatalyst.com/research/archives`.

## Authority boundaries

Python/FastAPI is authoritative for the workspace. v6.12 remains the primary-source intelligence foundation and Institutional Repository Federation remains the repository execution authority. WordPress remains an optional adapter and is not required for the Library runtime or workspace.

Primary-source labels, repository visibility, source criticism, provenance, OCR confidence, ranking, and comparison do not constitute truth, authenticity certification, accuracy guarantees, or evidence promotion.

No database migration is required.
