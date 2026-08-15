# Metadata Quality & Entity Resolution — v4.3.34

## Purpose

v4.3.34 gives Sustainable Catalyst a reviewable metadata-governance layer across Research Sources and Named Entities without introducing a competing source/entity store.

## Reused canonical systems

Research Source quality reuses Citation Studio v2.5.1 source records, normalized DOI/ISBN/URL fields, completeness metadata, duplicate candidates, canonical source IDs, and provenance. Named Entity resolution reuses the v3.2 `sc_named_entity` authority record, alias list, canonical external URI, entity type, and controlled-vocabulary assignment.

## Deterministic diagnostics

Source quality reports expose field-presence checks, normalized identifiers, existing duplicate matches, and canonical source identity. Named Entity reports expose canonical-label, type, authority URI, alias, and vocabulary coverage plus deterministic candidates based on exact authority URI, exact normalized label, canonical-label/alias equality, or shared normalized alias. The system does not use opaque similarity models to decide identity.

## Non-destructive entity resolution

An authorized reviewer may accept or reject a proposed relationship. Acceptance adds a canonical pointer from the candidate entity to the chosen canonical entity and carries the candidate label/aliases into the canonical alias set. The candidate entity remains in WordPress; historical record assignments are not rewritten; descriptions and private fields are not copied; before-state snapshots and reviewer history remain recorded. Resolution chains are bounded and cycles are rejected.

## Safety boundary

Quality scores are diagnostics, not truth ratings. Candidate scores are match signals, not identity determinations. There is no automatic merge, deletion, assignment rewrite, metadata overwrite, publication, or Workspace write.
