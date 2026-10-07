# Sustainable Catalyst Library v6.34.0 — Portable Research Object & Exchange Format

Library v6.34.0 introduces a lossless research-object transport layer for moving research state between Sustainable Catalyst products and environments without changing the authority or meaning of the transported object.

## Generations

- Library 6.34.0
- Backend 3.34.0
- Web 2.34.0
- SDK 1.34.0
- API v1 stable

## New surface

- Web: `/research/exchange`
- API: `/api/library/v1/research-object-exchange`

## Capabilities

- portable research-object envelopes
- exact source payload preservation
- source schema, object identity, and authority preservation
- deterministic SHA-256 content fingerprints
- provenance, lineage, and citation carry-forward
- deterministic multi-object exchange manifests
- explicit-only exchange relationships
- object and exchange integrity verification
- compatibility observation without semantic-equivalence claims
- non-mutating import preview
- deterministic JSON export

## Authority boundaries

The exchange layer is a transport/composition layer only. It is not project, package, publication, artifact, evidence, citation, or truth authority. Import does not occur automatically. No object is merged, deduplicated, schema-migrated, persisted, promoted, or rewritten automatically.

An integrity PASS means only that fingerprints match the transported bytes/structure. It does not imply source validity, evidence strength, scientific validity, truth, peer review, or endorsement.

## Persistence and migration

- no database migration
- no server-side exchange persistence authority
- WordPress remains optional
- existing Python/PostgreSQL and domain-specific authorities remain unchanged

## Next release

Library v6.35.0 — Collaborative Research Rooms II.
