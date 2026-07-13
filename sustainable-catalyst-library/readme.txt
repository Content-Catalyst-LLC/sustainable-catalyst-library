=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, knowledge-graph, relationships, provenance, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.16.0
License: GPLv2 or later

A native WordPress knowledge system with a provenance-aware graph, editorial collaboration, multimedia, persistent workspaces, server-rendered documents, planning, notebooks, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v1.16.0 adds Knowledge Graph and Relationship Intelligence while retaining the complete v1.15.0 Library platform.

= Knowledge Graph =

* Build a normalized graph projection from the current Library index and WordPress metadata.
* Connect publications, concepts, domains, tags, article maps, plans, methods, tools, datasets, sources, claims, evidence, places, organizations, and events.
* Preserve relationship type, direction, confidence, provenance, evidence notes, visibility, attribution, and verification.
* Explore a native responsive SVG graph with search, entity filters, relationship filters, rooted neighborhoods, and an accessible relationship list.
* Preserve manual and board-promoted entities across generated graph rebuilds.

= Relationship Intelligence =

* Detect orphaned Library records.
* Identify possible duplicate concept groups.
* Detect Content Planner dependency cycles.
* Find provenance gaps, low-confidence relationships, and unverified relationships.
* Exclude private and organization-only relationships from signed-out public responses.
* Provide timeline and place-relationship endpoints.
* Promote deliberate Whiteboard and Chalkboard entities into the graph without altering the original board.

= Retained systems =

* Editorial reviews, participant roles, comments, suggestions, approvals, locks, and attribution.
* Public record-card responsive repair from v1.14.1.
* Multimedia assets, clips, evidence reels, transcripts, rights, and optional Render processing.
* Cursor-based large-library indexing and database inventory.
* Persistent account workspaces and optional Render/PostgreSQL synchronization.
* Server-side book and PDF production.
* Content Planner, release coordination, Notebook, boards, annotations, books, and portable exports.

= Portable Data =

* Workspace schema `sc-library-workspace/1.8`.
* Editorial workflow schema `sc-library-editorial-workflow/1.0`.
* Knowledge graph schema `sc-library-knowledge-graph/1.0`.
* Portable export schema `sc-library-portable-export/1.6`.
* New normalized entities `graph_nodes` and `graph_edges`.

== Installation ==

1. Upload and activate the plugin.
2. Confirm the existing Library index is healthy under SC Library → Index Tools.
3. Open SC Library → Knowledge Graph.
4. Choose a batch size and select Start resumable graph rebuild.
5. Review orphan, duplicate-concept, dependency-cycle, provenance, confidence, and verification diagnostics.
6. Add a graph shortcode only after reviewing public visibility and confidence settings.

== Shortcodes ==

* `[sc_library_knowledge_graph]`
* `[sc_library_knowledge_graph root="record:123" depth="2" limit="250"]`
* `[sc_library_relationship_intelligence]`
* `[sc_library_editorial_workflow]`
* `[sc_library_multimedia_studio]`
* `[sc_library_evidence_reel id="REEL-UUID"]`
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

* `/wp-json/sustainable-catalyst/v1/library/graph/schema`
* `/wp-json/sustainable-catalyst/v1/library/graph`
* `/wp-json/sustainable-catalyst/v1/library/graph/diagnostics`
* `/wp-json/sustainable-catalyst/v1/library/graph/timeline`
* `/wp-json/sustainable-catalyst/v1/library/graph/places`
* `/wp-json/sustainable-catalyst/v1/library/graph/rebuild`
* `/wp-json/sustainable-catalyst/v1/library/graph/rebuild/start`
* `/wp-json/sustainable-catalyst/v1/library/graph/rebuild/continue`
* `/wp-json/sustainable-catalyst/v1/library/graph/rebuild/status`
* `/wp-json/sustainable-catalyst/v1/library/graph/board-promotions`
* `/wp-json/sustainable-catalyst/v1/library/graph/edges`

* `/wp-json/sustainable-catalyst/v1/library/editorial/schema`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews`
* `/wp-json/sustainable-catalyst/v1/library/media/status`
* `/wp-json/sustainable-catalyst/v1/library/media/assets`
* `/wp-json/sustainable-catalyst/v1/library/documents/status`
* `/wp-json/sustainable-catalyst/v1/library/workspaces`

== Changelog ==

= 1.16.0 =
* Added normalized graph-node and graph-edge WordPress tables.
* Added publication, concept, domain, series, method, tool, dataset, source, claim, evidence, place, organization, event, and other graph entities.
* Added confidence, confidence basis, provenance, evidence, visibility, attribution, and verification fields.
* Added rebuildable graph projection from the Library index, taxonomies, explicit relationships, planner dependencies, and metadata.
* Added orphaned-record, duplicate-concept, dependency-cycle, provenance-gap, low-confidence, and verification diagnostics.
* Added native SVG graph, accessible relationship list, inspectors, filters, rooted neighborhoods, timeline, and place views.
* Added explicit Whiteboard and Chalkboard promotion into the graph.
* Added graph REST endpoints and public shortcodes.
* Added `graph_nodes` and `graph_edges` to portable export schema 1.6.
* Added public relationship privacy filtering for non-public edges.

= 1.15.0 =
* Added native editorial review records and workflow states.
* Added Observer, Reviewer, Editor, and Approver participant roles.
* Added existing-account and expiring email invitations.
* Added comments, resolution, suggested edits, decisions, and attribution history.
* Added revision conflict detection and expiring editor locks.
* Added workspace-role synchronization for workspace-linked reviews.
* Added editorial REST endpoints, admin dashboard, shortcode, and responsive interface.
* Added five normalized editorial entities to portable export schema 1.5.


= 1.14.1 =
* Rebuilt public record cards as a single-column responsive grid so action controls cannot collapse title and excerpt columns.
* Added semantic excerpt and responsive-card hooks to the public renderer.
* Normalized horizontal writing mode, word breaking, inline sizing, and flex/grid minimum widths.
* Made resource badges and action controls wrap safely on desktop and tablet.
* Added compact two-column and one-column mobile action layouts.
* Added print rules that hide interactive controls and preserve readable full-width titles and excerpts.
* Added static and browser-layout regression tests for long titles, long excerpts, and expanded action sets.

= 1.14.0 =
* Added the native Multimedia Studio.
* Added video/audio asset, clip, evidence-reel, and processing-job schemas and database tables.
* Added rights, license, provenance, citation, transcript, caption, poster, annotation, and accessibility fields.
* Added non-destructive timestamp-based clip definitions.
* Added public evidence-reel shortcode and REST representation.
* Added optional signed Render media processing with bounded FFmpeg clip and poster generation.
* Added automatic WordPress Media Library import, diagnostics, retries, and SHA-256 checksums.
* Added portable PostgreSQL, CSV, JSONL, and JSON media entities.
* Added PDF media links, selected segments, transcript excerpts, and QR fallbacks.
* Updated workspace schema to 1.8 and portable export schema to 1.4.

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
