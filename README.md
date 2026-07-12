# Sustainable Catalyst Library v1.10.0

Library v1.10.0 adds the **PostgreSQL and Portable Research-Data Export** layer to the Sustainable Catalyst knowledge base.

WordPress remains the canonical publishing and editorial source. The export system translates Library records into a normalized, application-oriented schema rather than copying raw WordPress tables, serialized options, revisions, or theme metadata.

## Included

- PostgreSQL-compatible plain SQL exports
- Schema-and-data, schema-only, and data-only modes
- Complete Library, public-registry, planner, documentation, relationship, and schema scopes
- CSV ZIP bundles with one file per normalized entity
- JSONL ZIP bundles for analytics and migration workflows
- Single-file JSON snapshots
- SHA-256 checksum manifests
- Restore and custom-archive instructions
- Normalized records, terms, record-term links, relationships, resources, documentation, and plans
- Optional administrator export of private/draft planning records
- Optional normalized full article content in record payloads
- Browser-local Research Notebook export to PostgreSQL SQL, JSONL, and versioned JSON
- Dedicated PostgreSQL tables for collections, notes, sources, matrices, boards, annotations, books, saved records, and application handoffs
- Public schema and format REST endpoints
- Administrator-only export manifest endpoint
- Standalone `[sc_library_portability]` interface
- Research Notebook portability controls

## Architectural boundary

Server-side exports include canonical WordPress and Library data:

- Published and indexed records
- Public registry state
- Content Planner records
- Documentation authority metadata
- Taxonomies and record-term assignments
- Typed relationships
- Repository, dataset, video, Workbench, Decision Studio, and Site Intelligence resource references

Private Notebook content remains in browser storage and is never silently uploaded to WordPress. It is exported from the Notebook **Import / Export** tab or the standalone portability shortcode.

## WordPress installation

Upload `sustainable-catalyst-library-v1.10.0.zip`, replace the existing plugin, activate it, open **SC Library**, enable PostgreSQL and portable data, save settings, and rebuild the Library index.

Then open:

```text
SC Library → Portable Data Export
```

Recommended first validation:

1. Export **Schema only** as PostgreSQL SQL.
2. Export the **Complete public registry** as PostgreSQL SQL.
3. Export the **Complete Library server data** as a CSV bundle.
4. Open the Research Notebook and export the browser workspace as PostgreSQL SQL.

## Shortcodes

Standalone portability studio:

```text
[sc_library_portability]
```

Standalone Research Notebook opened to portability:

```text
[sc_library_notebook tab="portability"]
```

The main Library shortcode remains:

```text
[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
```

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/export/formats`
- `/wp-json/sustainable-catalyst/v1/library/export/postgresql-schema`
- `/wp-json/sustainable-catalyst/v1/library/export/manifest` — administrator only

## Restore workflow

Plain SQL export:

```bash
createdb sustainable_catalyst_library
psql -X --set ON_ERROR_STOP=on \
  sustainable_catalyst_library \
  < sustainable-catalyst-library.sql
```

Create a compressed PostgreSQL custom archive after restore:

```bash
pg_dump -Fc \
  sustainable_catalyst_library \
  -f sustainable-catalyst-library.backup
```

The plugin does not claim to generate a native `pg_dump` custom archive from WordPress. That format is created by PostgreSQL after the portable SQL is restored.
