# Release Notes — Sustainable Catalyst Library v1.10.0

## PostgreSQL and Portable Research-Data Export

This release makes Sustainable Catalyst Library data portable beyond WordPress while preserving WordPress as the publishing source of truth.

### Server export scopes

- Complete Library server data
- Complete public registry
- Content Planner and roadmap
- Foundations and documentation records
- Relationships, terms, and resources
- Schema only

### Formats

- PostgreSQL SQL
- CSV ZIP bundle
- JSONL ZIP bundle
- JSON snapshot

### Modes

- Schema and data
- Schema only
- Data only

### Exported normalized entities

- `records`
- `terms`
- `record_terms`
- `relationships`
- `resources`
- `documentation`
- `plans`
- `export_metadata`

### Browser-local workspace tables

- `workspace_collections`
- `workspace_saved_records`
- `workspace_notes`
- `workspace_sources`
- `workspace_matrices`
- `workspace_boards`
- `workspace_annotations`
- `workspace_books`
- `workspace_handoffs`

### Reliability and provenance

- Export schema: `sc-library-portable-export/1.0`
- Workspace schema: `sc-library-workspace/1.6`
- Versioned manifests
- SHA-256 checksums in bundle exports
- Explicit site URL, generation time, plugin version, scope, counts, and source-of-truth declaration
- Internal planning notes excluded unless an administrator explicitly requests private plans
- Browser-local research remains local until the user downloads it

### Restore boundary

The plugin generates portable SQL that can be restored with `psql`. A PostgreSQL custom archive can then be created with `pg_dump -Fc`. WordPress does not directly generate a native PostgreSQL archive.
