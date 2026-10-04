# Library v6.16.0 — Research Annotation & Scholarly Notes

Library v6.16.0 adds a general research annotation layer above the independent Library research interface. It is intentionally broader than the archive-only v6.12–v6.15 sequence: annotations can target Library records, primary sources, historical events, timelines, research projects, datasets, publications, external sources, or other explicitly identified research objects.

## Capabilities

- Canonical standalone workspace: `/research/notes`
- Python/FastAPI annotation authority with API v1 endpoints
- Source- and research-object anchored scholarly notes
- Page, section, paragraph, character-offset, timecode, fragment, selector and quoted-text anchors
- Note types for observation, summary, question, critique, interpretation, method, citation note, evidence note, contradiction, context, follow-up and marginalia
- Tags, references and explicit human-asserted note relationships
- Target-grouped scholarly notebook composition
- Unresolved-question preservation
- Browser-local continuity in the web client plus deterministic JSON export packages
- First-party Python and JavaScript client methods

## Authority and persistence boundary

This release does **not** introduce a new PostgreSQL table or server-side note store. The Python service is authoritative for annotation normalization, notebook composition, relationship normalization, and export contracts. The web client may retain working notes in browser local storage for continuity, but browser storage is explicitly non-authoritative. JSON export is a portable preview/package, not an automatic import or evidence promotion pathway.

## Guardrails

- An annotation is not source content, source metadata, evidence truth, or a citation merely because it exists.
- A selected quote is preserved as user-provided text and is not automatically verified against the target.
- Locator presence does not prove quote accuracy.
- Note type and tags do not imply evidentiary strength or truth status.
- Annotation relationships are human assertions and are not automatically inferred.
- Notes are not automatically promoted to claims, evidence, citations, truth status, or Platform Core.
- No database migration is introduced.
- WordPress remains optional and non-authoritative.

## Generation

- Library 6.16.0
- Backend 3.16.0
- Web 2.16.0
- Python SDK 1.16.0
- JavaScript/TypeScript client 1.16.0
- API v1 remains stable

Next planned release: **Library v6.17.0 — Citation Workspace & Bibliographic Intelligence**.
