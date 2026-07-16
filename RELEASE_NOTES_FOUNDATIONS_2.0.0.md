# Sustainable Catalyst Foundations v2.0.0

## Canonical Foundation Document System

This release turns the existing Foundation Document record into a governed institutional-document format while preserving Knowledge Library as the single source of truth.

### Added

- Stable Foundation Document identifiers.
- Institutional Standard, Policy and Legal Record, and Product and System Brief variants.
- Controlled authority, status, canonical-record, ownership, review, supersession, correction, and revision metadata.
- Custom singular and archive templates.
- Automatic table of contents.
- Document authority statement.
- Citation, PDF, print, correction, related-record, and revision-history controls.
- `[sc_foundations_catalog]` for the public Foundations page.
- Public Foundations catalog REST endpoint.
- Responsive, accessible, print-ready Foundation Document styles.
- Foundation Document 2.0 JSON Schema and controlled vocabulary.

### Preserved

- Native `sc_foundation_doc` post type.
- Existing PDF attachments and PDF.js viewer.
- Full-text PDF extraction and page indexing.
- Knowledge Library search and Research Librarian synchronization.
- Existing citation exports.
- Relationships and Knowledge Graph projection.
- Preservation snapshots, integrity checks, and historical authority records.
- WordPress as the canonical publishing authority.

### Version boundary

`SC_LIBRARY_FOUNDATIONS_VERSION` is `2.0.0`. The installer does not downgrade or overwrite the Knowledge Library plugin version because Foundations is a coordinated subsystem release within the existing Library repository.
