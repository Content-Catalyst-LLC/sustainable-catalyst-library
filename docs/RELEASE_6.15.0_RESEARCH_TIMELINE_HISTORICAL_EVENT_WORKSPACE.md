# Sustainable Catalyst Library v6.15.0

## Research Timeline & Historical Event Workspace

Library v6.15.0 adds a dedicated historical-event and chronology research surface at:

`/research/archives/timeline`

### Release generations

- Library: 6.15.0
- Backend: 3.15.0
- Independent Web: 2.15.0
- Python SDK: 1.15.0
- JavaScript/TypeScript client: 1.15.0
- API: v1 stable
- Database migration: none
- WordPress: optional, non-authoritative adapter

### Capabilities

- Normalized historical event objects with deterministic fingerprints.
- Exact, circa, before/after, range, decade, century, and unknown date assertions through the existing v6.12 date authority.
- Explicit source-to-event assertions with locators and human-supplied basis.
- Human-asserted event relationships without automatic causal inference.
- Timeline construction that preserves uncertain dates and unknown-date events.
- Temporal-overlap candidate reporting without asserting simultaneity.
- Event/source coverage matrices where counts remain descriptive.
- Competing chronology comparison with explicit shared `event_key` alignment.
- No fuzzy event identity merge and no automatic winning chronology.

### Architectural lineage

- v6.12 remains authoritative for primary-source normalization and historical date assertions.
- v6.13 remains the Historical Archives Research Workspace foundation.
- v6.14 remains authoritative for structured source criticism and corroboration/contradiction analysis.
- v6.15 composes these capabilities into historical-event and chronology reasoning.

### Guardrails

A display order is not treated as a true chronology, temporal adjacency is not causation, overlap is not proof of simultaneity, source counts are not confidence probabilities, and competing chronologies are never auto-reconciled or ranked. No automatic evidence, truth, or Platform Core promotion occurs.
