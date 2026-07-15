# Topic Migration Guide — v3.2.0

## Purpose

v3.2.0 creates one canonical hierarchical Topic taxonomy while preserving existing Research Source Topics and Foundation Document post tags.

## Stages

1. Copy `sc_source_topic` terms into `sc_library_topic`, reusing matching slugs and recording legacy IDs.
2. Add canonical Topic assignments to Research Sources from retained Source Topic slugs.
3. Add canonical Topic assignments to Foundation Documents from retained post tags.

## Guarantees

The migration does not delete or unregister Source Topics, remove Source assignments, delete document tags, rename records, change URLs, alter citations, or modify Claims/Evidence Notes.

## Resumability

State stores version, status, step, cursor, totals, processed count, failures, error, and timestamps. Source and document stages use stable post-ID cursors. The process is idempotent and resumes after timeouts or deployments.

Run from `SC Library → Topics and Relationships`, REST, or:

```bash
wp sc-library knowledge migrate-topics
```

After completion, review duplicate labels, create Topic hierarchies, add scope notes, align vocabularies, and run coverage analysis.

The retained Source Topic taxonomy is non-hierarchical, so migration does not invent parent-child relationships.
