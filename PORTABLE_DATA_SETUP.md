# Library v1.10.0 Portable Data Setup

## 1. Install and rebuild

1. Upload `sustainable-catalyst-library-v1.10.0.zip`.
2. Replace the existing Library plugin.
3. Open **SC Library**.
4. Enable **PostgreSQL and portable data**.
5. Confirm the default schema name: `sustainable_catalyst_library`.
6. Save settings.
7. Run **Rebuild Library Index**.

## 2. Open the export studio

Go to:

```text
SC Library → Portable Data Export
```

## 3. Choose a scope

- **Complete Library server data** — all indexed server records plus plans and metadata
- **Complete public registry** — only public registry records and public plans
- **Content Planner and roadmap** — planning records only
- **Foundations and documentation records** — documentation records and dependencies
- **Relationships, terms, and resources** — graph and taxonomy data
- **Schema only** — no data

## 4. Choose a format

### PostgreSQL SQL

Best for direct restoration with `psql`.

### CSV bundle

Best for spreadsheet, ETL, and database-import workflows. The ZIP includes:

- One CSV per entity
- `schema.sql`
- `manifest.json`
- `checksums.sha256`
- `README.md`

### JSONL bundle

Best for analytics, data engineering, search indexing, and streaming imports.

### JSON snapshot

Best for inspection and application-to-application exchange.

## 5. Export private planner records carefully

The **Include private plans** option can include drafts, private plans, pending plans, future plans, and internal planning notes. Leave it unchecked for public or shareable exports.

## 6. Export the browser Research Notebook

Private Notebook objects are not stored in WordPress. Use either:

```text
[sc_library_notebook tab="portability"]
```

or:

```text
[sc_library_portability]
```

Available browser exports:

- PostgreSQL workspace SQL
- JSONL
- Versioned JSON manifest
- Schema-only SQL

## 7. Restore PostgreSQL SQL

```bash
createdb sustainable_catalyst_library
psql -X --set ON_ERROR_STOP=on \
  sustainable_catalyst_library \
  < sustainable-catalyst-library-complete.sql
```

## 8. Add browser workspace data

After restoring the server export:

```bash
psql -X --set ON_ERROR_STOP=on \
  sustainable_catalyst_library \
  < sustainable-catalyst-library-workspace.sql
```

The workspace SQL uses the same PostgreSQL schema and populates the dedicated `workspace_*` tables.

## 9. Create a custom PostgreSQL archive

```bash
pg_dump -Fc \
  sustainable_catalyst_library \
  -f sustainable-catalyst-library.backup
```

Restore the custom archive later with:

```bash
createdb sustainable_catalyst_library_restored
pg_restore \
  --dbname=sustainable_catalyst_library_restored \
  sustainable-catalyst-library.backup
```

## 10. Verify checksums

From an extracted CSV or JSONL bundle:

```bash
shasum -a 256 -c checksums.sha256
```

## Data boundary

The export includes normalized metadata and text. It does not automatically copy binary PDFs, images, audio, video, or repository contents. Those remain linked resources with provenance.
