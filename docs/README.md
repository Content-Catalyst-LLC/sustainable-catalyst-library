# Knowledge Library Documentation

`docs/` contains **living documentation and machine-readable contracts for the current codebase**. Historical per-release documents are preserved by Git tags rather than accumulated in the root of `main`.

## Current documentation areas

- `architecture/` — current system architecture and responsibility boundaries.
- `schemas/` — JSON schemas for Library contracts and research objects.
- `examples/` — client and integration examples.
- `foundations/` — Foundation document schemas and vocabulary.
- `openapi.json` — API contract snapshot.
- `postgresql-schema.sql` — database schema reference.

## Historical release documents

To retrieve a document from a historical release, address the tag directly:

```bash
git show v5.48.0:CROSS_LANGUAGE_ENTITY_NAME_HISTORICAL_TOPONYM_RESOLUTION_v5.48.0.md
git show v5.47.0:RELEASE_NOTES_KNOWLEDGE_LIBRARY_5.47.0.md
```

This keeps `main` readable while preserving the exact release record.
