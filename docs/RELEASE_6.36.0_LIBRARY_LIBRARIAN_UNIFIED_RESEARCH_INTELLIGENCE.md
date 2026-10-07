# Sustainable Catalyst Library v6.36.0 — Library–Librarian Unified Research Intelligence

## Purpose

v6.36.0 creates the governed research-intelligence bridge between the Sustainable Catalyst Knowledge Library and Research Librarian AI. It does not collapse the two products into a single hidden authority. Instead, the Library composes explicit research context packets, prepares explicit Librarian request envelopes, receives advisory outputs through a non-mutating preview, audits grounding against declared context sources, and turns suggestions into proposed actions that still require explicit execution.

## Generations

- Library: 6.36.0
- Backend: 3.36.0
- Web: 2.36.0
- SDK: 1.36.0
- API: v1 stable

## Surface

- Web: `/research/intelligence`
- API: `/api/library/v1/research-intelligence`

## Operations

- `context-packet`
- `validate-context`
- `librarian-request`
- `advisory-preview`
- `grounding-audit`
- `action-plan`
- `export`

## Context coverage

A context packet may carry explicit references to:

- Library research projects
- Collaborative Research Rooms
- Working sets
- Research objects
- Citations
- Provenance
- Research packages
- Publication drafts
- Portable research-object exchanges

The packet preserves originating schemas and authorities. Inclusion in a context packet does not transfer authority to the Librarian.

## Authority boundaries

The Library remains authoritative for Library research objects, research state, and provenance. Research Librarian output is advisory only. This release performs no automatic external transport, search execution, source inclusion, working-set mutation, project mutation, room mutation, review decision, publication, persistence, truth promotion, evidence promotion, claim promotion, or Platform Core promotion.

Grounding coverage measures whether an advisory references declared context sources. It is not a truth score and does not imply context completeness.

## Database / WordPress

No database migration is required. WordPress remains optional and non-authoritative.

## Next mapped release

Library v6.37.0 — Cross-Library / Cross-Institution Research Federation.
