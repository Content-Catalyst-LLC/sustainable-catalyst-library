# Production Checklist — Knowledge Library v3.2.0

## Installation and migration

- Confirm version 3.2.0 and the Topics and Relationships workspace.
- Confirm Concepts, Named Entities, and Vocabularies appear.
- Confirm Concept and Entity archives resolve.
- Run migration to Complete, interrupt/resume it, and verify retained Source Topics/tags remain.

## Semantic records

- Create parent/child Topics with labels, scope notes, URIs, and vocabulary links.
- Create representative Concepts, Entities, and vocabularies.
- Assign Topics, Concepts, and Entities to every supported record type.
- Confirm private records remain private.

## Relationships

- Test every relationship type, inverse label, note, weight, and public state.
- Confirm self-relations are rejected and duplicate rows collapse.
- Test document continuations, translations, summaries, companions, containment, and methodology.
- Delete a destination record and confirm stale relationships are removed.

## Public discovery and coverage

- Test document, Source, Claim, Concept, Entity, and vocabulary output.
- Test all three shortcodes and the Relationship Browser.
- Test library/project gap reports and cache invalidation.
- Test keyboard, mobile, and print behavior.

## API, CLI, and regression

- Test all REST and WP-CLI interfaces, migration locking, and permissions.
- Run the explicit v3.2.0 release manifest.
- Confirm Source Integrity, Production Validation, citations, connectors, holdings, OCR, Evidence, Claims, Projects, PDFs, and repository systems remain operational.
