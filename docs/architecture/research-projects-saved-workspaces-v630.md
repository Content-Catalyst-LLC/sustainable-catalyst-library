# Knowledge Library v6.3.0 — Research Projects & Saved Workspaces

v6.3.0 turns the temporary browser working set into an explicit handoff to persistent Library research state.

## Product generations

- Library: 6.3.0
- Backend: 3.3.0
- Independent Library Web: 2.3.0
- Python/JavaScript/TypeScript SDK: 1.3.0
- API: v1 stable

## Persistence

No new database model is introduced.

The release composes the existing Python Research State Service and its PostgreSQL tables for projects, project references, source bundles, saved searches, collections, queue items, and watchlists.

## Browser security

The independent Web application never receives a service API key.

Saved-workspace browser routes:
- require the Library service session cookie;
- bind ownership to `session.identity.identity_id`;
- require `X-SC-CSRF-Token` for mutations.

The service layer overwrites any caller-supplied owner identity before delegating to Research State.

## Working set

The browser working set remains non-authoritative local convenience state. Records become persistent only after the user explicitly saves them to a Library research project.

Saving a record to a project does not imply evidence quality, truth, endorsement, importance, or Platform Core promotion.

## WordPress

WordPress remains an optional adapter and has no project-state or saved-workspace authority.

## Next

v6.4.0 — Project Detail, Source Bundles & Collections.
