=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, pdf, document-production, render, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.13.4
License: GPLv2 or later

A native WordPress knowledge base with persistent workspaces, server-rendered books and PDFs, planning, documentation, notebooks, PostgreSQL portability, and optional Render services.

== Description ==

Sustainable Catalyst Library v1.13.4 adds raw database inventory, automatic legacy-scope expansion, stable Index Tools routing, and a server-side reconciliation fallback so large Libraries cannot remain trapped in an old configured subset.


= Large-Library Index Scanner =

* Direct WordPress database discovery immune to front-end query filters.
* Cursor batches using the last processed WordPress post ID.
* No unbounded WP_Query and no complete ID queue stored in options.
* Automatic Posts, Pages, and editorial custom-post-type discovery.
* Recommended post-type selection and saved Library configuration.
* Complete, missing-only, outdated-only, and repair modes.
* Per-post-type published, eligible, excluded, indexed, missing, and outdated counts.
* Dedicated audit table containing every processed post ID and outcome.
* Explicit exclusion and failure reasons in downloadable JSON reports.
* Strict processed/indexed/excluded/failed completion accounting.
* Pause, resume, cancel, and reset controls.
* No dependency on Render, PostgreSQL, account workspaces, or document production.

= Server Document Production =

* Queued Render PDF jobs with progress, retries, and diagnostics.
* Stable page size, margins, headers, footers, and page numbers.
* Cover, front matter, table of contents, chapters, references, indexes, and manifests.
* Code blocks, tables, source notes, remote-image safeguards, and handwriting transcriptions.
* Frozen edition records with source-content and PDF SHA-256 checksums.
* Automatic import into the WordPress Media Library.
* Render remains optional; browser PDF output continues to work.

= Persistent Workspaces =

* Local, account, and hybrid workspace modes.
* Revision history, conflict protection, collaboration, and optional Render/PostgreSQL synchronization.

= Portable Data =

* Workspace schema `sc-library-workspace/1.7`.
* Portable export schema `sc-library-portable-export/1.3`.
* PostgreSQL-ready document-job and frozen-edition tables without embedded PDF binaries.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library and rebuild the index.
3. Browser PDF generation works immediately from the Book Builder.
4. Optionally deploy or upgrade the included Render service.
5. Configure the service URL and key under Server-side document production.
6. Create a short test book and verify Media Library import.

== Shortcodes ==

* `[sc_library_book_builder]`
* `[sc_library_document_production]`
* `[sc_library_account_workspaces]`
* `[sc_library_notebook]`
* `[sc_library]`
* `[sc_library_registry mode="public"]`
* `[sc_library_planning_analytics]`
* `[sc_library_release_coordination]`
* `[sc_library_portability]`
* `[sc_library_annotation_studio]`
* `[sc_library_translation_matrix]`
* `[sc_library_whiteboard]`
* `[sc_library_chalkboard]`

== REST API ==

* `/wp-json/sustainable-catalyst/v1/library/documents/status`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/refresh`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/retry`
* `/wp-json/sustainable-catalyst/v1/library/documents/editions`
* `/wp-json/sustainable-catalyst/v1/library/workspaces`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}`

== Changelog ==

= 1.13.4 =
* Added raw published inventory independent of saved Library post-type settings.
* Added separate counts for standard Posts, all editorial records, selected scope, and global indexed rows.
* Automatically expands the legacy Posts-only scope when additional editorial records are present.
* Selects all recommended editorial post types by default on Index Tools.
* Added database-only post-type discovery for conditionally registered content types.
* Added a bounded server-side reconciliation fallback for stalled REST/JavaScript scans.
* Added the stable SC Library → Index Tools route while preserving the legacy scanner alias.
* Switched scanner API calls to WordPress-relative REST paths.

= 1.13.3 =

* Replaced the large candidate queue with cursor-based direct database scanning.
* Added direct published counts immune to pre_get_posts and theme query filters.
* Added automatic discovery of Posts, Pages, and editorial custom post types.
* Added recommended-type selection and configuration persistence.
* Added a scan audit table with every post ID, outcome, and reason.
* Added explicit exclusion counts separate from failed records.
* Added strict completion accounting and incomplete/error states.
* Added scanner-state reset and complete JSON audit reports.
* Protected other configured post types during subset scans.
* Converted the synchronous fallback rebuild to bounded cursor batches.

= 1.13.2 =

* Fixed the Index Scanner admin page registration order.
* Registered the SC Library parent menu before all Library submenus.
* Registered the Index Scanner after its parent menu exists.
* Corrected the scanner admin route and asset hook resolution.
* Retained the resumable scanner, diagnostics, repairs, and downloadable logs from v1.13.1.

= 1.13.1 =

* Added a dedicated resumable Index Scanner.
* Added complete, missing, outdated, and repair scan modes.
* Added per-post-type diagnostics and index freshness reporting.
* Added targeted record reindexing by ID or URL.
* Added stale-record, relationship, and identifier repair.
* Added downloadable scan logs and saved scan state.
* Corrected synchronous rebuild eligibility handling for public planned content.

= 1.13.0 =

* Added queued server-side PDF and document production.
* Added ReportLab rendering through the optional Render service.
* Added document-job and frozen-edition registries.
* Added automatic WordPress Media Library import.
* Added content and output checksums, manifests, diagnostics, and retries.
* Added server-rendering controls to the Book Builder.
* Added document production REST endpoints and shortcode.
* Upgraded portable export schema to 1.3.

= 1.12.0 =

* Added persistent WordPress account workspaces.
* Added local, account, and hybrid storage modes.
* Added local-to-account migration and cross-device loading.
* Added revision history, content hashes, and optimistic concurrency.
* Added viewer and editor collaborator roles.
* Added optional Render FastAPI/PostgreSQL synchronization.
* Added health, sync, conflict, and recovery diagnostics.
* Added account workspace REST endpoints and shortcodes.
* Upgraded workspace schema to 1.7 and portable export schema to 1.2.

= 1.11.0 =

* Added planning analytics, dependency intelligence, and release coordination.
