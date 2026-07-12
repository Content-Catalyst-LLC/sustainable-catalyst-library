=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, postgresql, data-export, content-planner, documentation, research
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.10.0
License: GPLv2 or later

A native WordPress knowledge base with PostgreSQL and portable research-data exports, a public registry, content planner, documentation authority, research workspaces, and custom books.

== Description ==

Sustainable Catalyst Library v1.10.0 adds normalized PostgreSQL and portable research-data export.

WordPress remains the canonical publishing source. The plugin converts Library records into a stable application schema rather than copying raw WordPress tables, revisions, serialized options, or theme metadata.

= PostgreSQL Export =

* Portable plain SQL for restoration with psql.
* Schema-and-data, schema-only, and data-only modes.
* Complete Library, public-registry, planner, documentation, relationship, and schema scopes.
* Normalized records, terms, record-term assignments, relationships, resources, documentation, plans, and export metadata.
* Optional full article text in JSONB payloads.
* Optional administrator export of private planning records and internal planning notes.

= Portable Bundles =

* CSV ZIP bundles with one file per entity.
* JSONL ZIP bundles for analytics and migration workflows.
* Single-file JSON snapshots.
* schema.sql, manifest.json, README, and SHA-256 checksums.

= Browser Research Workspace =

* Export local collections, saved records, notes, sources, Translation Matrices, Whiteboards, Chalkboards, annotations, custom books, and application handoffs.
* PostgreSQL workspace SQL.
* JSONL workspace export.
* Versioned JSON workspace manifest.
* Dedicated workspace tables in the same normalized PostgreSQL schema.
* Private Notebook data remains in browser storage until the user exports it.

= Existing Knowledge-Base Capabilities =

* Search-first public knowledge base.
* Knowledge relationships and record panels.
* Research Notebook and source collection.
* Technical Translation Matrix.
* Whiteboards and Chalkboards.
* Workbench, Decision Studio, and Site Intelligence handoffs.
* Annotation Studio and handwriting.
* Custom Book Builder and browser PDF generation.
* Foundations Documentation Library and authority model.
* Content Planner, public registry, and roadmap tracker.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Enable PostgreSQL and portable data.
4. Save settings and rebuild the Library index.
5. Open SC Library → Portable Data Export.
6. Test a schema-only PostgreSQL export before exporting complete data.

== Shortcodes ==

* `[sc_library_portability]`
* `[sc_library_notebook tab="portability"]`
* `[sc_library_registry mode="public"]`
* `[sc_library_planner_tracker mode="public"]`
* `[sc_library mode="registry"]`
* `[sc_library collection="foundations" mode="documentation"]`
* `[sc_foundations_library mode="public"]`
* `[sc_library]`
* `[sc_library_notebook]`
* `[sc_library_book_builder]`
* `[sc_library_translation_matrix]`
* `[sc_library_whiteboard]`
* `[sc_library_chalkboard]`
* `[sc_library_boards]`
* `[sc_library_integrations]`
* `[sc_library_annotation_studio]`

== REST API ==

* `/wp-json/sustainable-catalyst/v1/library/export/formats`
* `/wp-json/sustainable-catalyst/v1/library/export/postgresql-schema`
* `/wp-json/sustainable-catalyst/v1/library/export/manifest` — administrator only

== Changelog ==

= 1.10.0 =

* Added normalized PostgreSQL schema and plain SQL export.
* Added schema-and-data, schema-only, and data-only modes.
* Added complete, registry, planner, documentation, relationship, and schema scopes.
* Added CSV ZIP and JSONL ZIP bundles.
* Added JSON snapshots, manifests, restore notes, and SHA-256 checksums.
* Added browser-local Notebook export to PostgreSQL SQL and JSONL.
* Added workspace schema migration to sc-library-workspace/1.6.
* Added standalone portability shortcode and administrator export studio.
* Added public export format and PostgreSQL schema endpoints.
* Added administrator-only export manifest endpoint.
* Documented psql restore and pg_dump custom-archive workflow.

= 1.9.0 =

* Added Content Planner, Article Map Planner, public registry, and roadmap tracker.

= 1.8.0 =

* Added the Foundations Documentation Library and authority model.

= 1.7.0 =

* Added the local-first Custom Book Builder and browser PDF workflow.

= 1.6.0 =

* Added the local-first Annotation Studio and handwriting layers.
