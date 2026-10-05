# Sustainable Catalyst Library v6.28.0 — Library ↔ Workspace Research Integration

## Purpose

Library v6.28.0 establishes a versioned, provenance-preserving research exchange boundary between Sustainable Catalyst Library and Workspace.

The Library can compose an explicit handoff packet from existing Library research objects for a Workspace project, and can accept a Workspace result as a **registration preview** that can later be persisted only through the existing Library authorities. The release also provides round-trip consistency auditing and deterministic portable exchange export.

## New surface

- Web route: `/research/workspace`
- API base: `/api/library/v1/workspace-integration`
- Contract: `sc-library-workspace-research-integration/1.0`
- Library 6.28.0 / backend 3.28.0 / web 2.28.0 / SDK 1.28.0

## Operations

- Library → Workspace handoff packet
- Handoff validation
- Workspace result → Library registration preview
- Library/Workspace round-trip correlation audit
- Deterministic exchange export

## Authority boundaries

Library v6.28.0 does **not** become Workspace execution or persistence authority. Workspace does **not** become Library catalog, research-state, evidence, citation, package, or publication authority. Existing originating authorities remain authoritative.

No automatic Workspace project creation, execution, push delivery, result import, artifact persistence, claim/evidence/truth promotion, or Platform Core promotion occurs.

## Certification boundary

v6.28 certifies the Library-side exchange contracts and same-origin Web/API surface. Live cross-product transport and end-to-end execution certification are intentionally deferred to **v6.29.0 — Cross-Product Research Handoff & Contract Certification**.

No database migration is required. WordPress remains optional.
