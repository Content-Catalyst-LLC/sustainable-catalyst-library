=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, knowledge-graph, relationships, provenance, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.18.0
License: GPLv2 or later

A native WordPress knowledge system with a versioned public API, signed webhooks, developer documentation, Research Librarian orchestration, a provenance-aware graph, editorial collaboration, multimedia, workspaces, planning, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v1.18.0 adds a versioned public API, scoped service keys, signed webhooks, OpenAPI 3.1, JSON Schemas, and a public developer portal while retaining the complete v1.17.0 Library platform.

= Public API and developer portal =

* Public namespace `/wp-json/sustainable-catalyst-library/v1`.
* Public records, relationships, graph neighborhoods, roadmap data, schemas, status, and OpenAPI routes.
* Protected export, reindex, and webhook-test operations using scoped administrator-issued keys.
* Keyed API-key hashes, per-key rate limits, expiration, revocation, and last-used timestamps.
* Exact-origin opt-in CORS rather than wildcard access.
* Public shortcode `[sc_library_developer_portal]`.

= Signed webhooks =

* HTTPS-only endpoints with private-network and unsafe-URL safeguards.
* Event subscriptions for publication, plans, documentation, graph rebuilds, workspaces, editorial transitions, books, and media clips.
* Timestamped HMAC SHA-256 delivery signatures.
* Bounded retries, delivery history, response summaries, tests, pause, delete, and redelivery controls.
* Encrypted signing secrets shown only once at creation.

= Portable Data =

* Portable export schema `sc-library-portable-export/1.8`.
* New normalized entities `api_keys`, `webhooks`, and `webhook_deliveries`.
* Secret hashes, encrypted signing secrets, full delivery payloads, and delivery signatures are excluded from portable exports.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library → Developer API.
3. Configure public and keyed rate limits.
4. Create a WordPress page with `[sc_library_developer_portal]`.
5. Save the page URL in Developer API settings.
6. Create the minimum scoped key needed for each integration.
7. Add one HTTPS webhook and send a test delivery.

== Shortcodes ==

* `[sc_library_developer_portal]`
* `[sc_research_librarian_orchestrator]`
* `[sc_library_knowledge_graph]`
* `[sc_library_editorial_workflow]`
* `[sc_library_multimedia_studio]`
* `[sc_library]`
* `[sc_library_registry mode="public"]`
* `[sc_library_portability]`

== REST API ==

* `/wp-json/sustainable-catalyst-library/v1/status`
* `/wp-json/sustainable-catalyst-library/v1/records`
* `/wp-json/sustainable-catalyst-library/v1/records/{id}`
* `/wp-json/sustainable-catalyst-library/v1/relationships`
* `/wp-json/sustainable-catalyst-library/v1/graph`
* `/wp-json/sustainable-catalyst-library/v1/roadmap`
* `/wp-json/sustainable-catalyst-library/v1/schemas`
* `/wp-json/sustainable-catalyst-library/v1/openapi.json`

== Changelog ==

= 1.18.0 =
* Added a dedicated versioned public API namespace.
* Added public record, relationship, graph, roadmap, schema, status, and OpenAPI endpoints.
* Added hashed scoped API keys, rate limits, expiration, revocation, and last-used tracking.
* Added signed HTTPS webhooks with encrypted secrets, bounded retries, delivery logs, tests, and redelivery.
* Added publication, plan, documentation, graph, workspace, review, document, and media event bridges.
* Added a native public developer portal and admin Developer API workspace.
* Added OpenAPI 3.1, JSON Schemas, JavaScript/Python clients, and webhook-verification examples.
* Added portable developer metadata entities and export schema 1.8 without exporting secrets.

= 1.17.0 =
* Added site-scoped Research Librarian Workspace Orchestration.
* Added indexed retrieval, Knowledge Graph expansion, and transparent recommendation reasons.
* Added user-confirmed actions for Notebook collections, records, notes, matrices, boards, books, tool handoffs, editorial packets, and exports.
* Added routes to Workbench, Decision Studio, Site Intelligence, and Sustainable Catalyst Lab.
* Added optional remote synthesis constrained to supplied Library records.
* Added saved account sessions and attributed action events.
* Added focused Ask Research Librarian links to Library records.
* Added portable orchestration entities and export schema 1.7.


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
