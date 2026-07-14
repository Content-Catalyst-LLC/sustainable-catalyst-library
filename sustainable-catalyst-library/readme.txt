=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, knowledge-graph, relationships, provenance, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.2.0
License: GPLv2 or later

A unified WordPress Living Knowledge System for public discovery, research workspaces, institutional operations, preservation, APIs, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v2.0.1 repairs the plugin-owned topics, relationships, and pathways discovery interface while v2.0.0 unifies the complete v1.x platform into three coordinated layers: Public Knowledge, Research Workspace, and Institutional Operations. It adds a unified public portal, a research-workspace gateway, checksummed system manifests, privacy-aware cross-module activity, public system-status APIs, developer schemas, and portable Living Knowledge System entities while preserving every specialist Library tool.


= Unified Living Knowledge System =

* Public portal shortcode `[sc_library_living_system]` with complete, public, research, and institutional modes.
* Unified research gateway `[sc_library_unified_workspace]` for Notebook, Research Librarian, graph, books, editorial review, and portability.
* Public aggregate component `[sc_library_system_status]`.
* Living Knowledge System administration workspace under SC Library.
* Checksummed `sc-library-system-manifest/1.0` records and privacy-aware `sc-library-system-event/1.0` activity.
* Public system status, capabilities, activity, and manifest routes.
* Developer API schema and `system.manifest.created` webhook event.
* Portable export schema `sc-library-portable-export/3.0` with `system_manifests` and `system_events`.
* WordPress remains canonical; no automatic publication, approval, or scheduling.

= Accessibility, mobile, performance, and security hardening =

* Production Readiness dashboard under SC Library.
* Public status shortcode `[sc_library_readiness_status]` and public summary API.
* High-visibility keyboard focus, skip links, polite live announcements, responsive table regions, and reduced-motion behavior.
* Minimum 44-pixel touch targets, mobile-safe forms, one-column narrow-screen actions, and print fallbacks.
* Bounded cache for an explicit allowlist of unauthenticated public GET routes; private and authenticated endpoints are never cached.
* Anonymous route-specific rate limiting, security response headers, and cache invalidation on content and taxonomy changes.
* Runtime, index, cron, API-key storage, remote-media, preservation, PDF extraction, and backup-boundary diagnostics.
* Daily readiness evaluation and one-click maintenance-schedule repair.
* Portable export schema `sc-library-portable-export/3.0` with normalized `readiness_runs`, `system_manifests`, and `system_events`.

= Foundation Documents and full-text PDF indexing =

* Native `sc_foundation_doc` record type under SC Library.
* Media Library PDF attachment selection with explicit Open PDF and Download PDF controls.
* Bundled PDF.js inline viewer with mobile fallback.
* Browser-local page extraction into normalized WordPress tables; PDFs are not sent to a third-party service.
* Page-aware Library search results and Research Librarian recommendations.
* Extraction status, retry, failure diagnostics, version history, related records, and BibTeX/RIS/CSL/plain citations.
* Migration tool for existing direct-download Foundation PDF links.
* Public shortcode `[sc_foundation_document id="123"]`.

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

* Portable export schema `sc-library-portable-export/3.0`.
* Preservation entities `preservation_snapshots`, `integrity_checks`, and `authority_history`.
* Existing Foundation Document, API, webhook, workspace, graph, multimedia, editorial, planning, and orchestration entities remain portable.
* API-key hashes, encrypted signing secrets, full webhook payloads, delivery signatures, and unrelated private workspace data remain excluded from public exports.

== Installation ==

1. Upload and activate the plugin, choosing **Replace current with uploaded** during an upgrade.
2. Clear WordPress, page-builder, Cloudflare, and browser caches.
3. Open SC Library → Living Knowledge System.
4. Create or locate the draft portal page, review it, and publish it manually.
5. Create the first checksummed system manifest.
6. Open SC Library → Production Readiness and run the complete readiness report.
7. Confirm Index Tools, Knowledge Graph, Foundation Documents, archive, API, and workspace status.
8. An index rebuild is not required solely for this upgrade.

== Shortcodes ==

* `[sc_library_living_system]`
* `[sc_library_unified_workspace]`
* `[sc_library_system_status]`

* `[sc_library_readiness_status]`
* `[sc_library_institutional_archive]`
* `[sc_library_integrity_status]`
* `[sc_foundation_document id="123"]`
* `[sc_library_developer_portal]`
* `[sc_research_librarian_orchestrator]`
* `[sc_library_knowledge_graph]`
* `[sc_library_editorial_workflow]`
* `[sc_library_multimedia_studio]`
* `[sc_library]`
* `[sc_library_registry mode="public"]`
* `[sc_library_portability]`

== REST API ==

* `/wp-json/sustainable-catalyst/v1/library/discovery`
* `/wp-json/sustainable-catalyst-library/v1/discovery`
* `/wp-json/sustainable-catalyst/v1/library/system/status`
* `/wp-json/sustainable-catalyst/v1/library/system/capabilities`
* `/wp-json/sustainable-catalyst/v1/library/system/activity`
* `/wp-json/sustainable-catalyst/v1/library/system/manifest`
* `/wp-json/sustainable-catalyst-library/v1/system`

* `/wp-json/sustainable-catalyst/v1/library/readiness/status`
* `/wp-json/sustainable-catalyst/v1/library/readiness/report`
* `/wp-json/sustainable-catalyst/v1/library/readiness/run`
* `/wp-json/sustainable-catalyst/v1/library/preservation/status`
* `/wp-json/sustainable-catalyst/v1/library/archive`
* `/wp-json/sustainable-catalyst/v1/library/archive/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/archive/{uuid}/manifest`
* `/wp-json/sustainable-catalyst-library/v1/archive`
* `/wp-json/sustainable-catalyst-library/v1/status`
* `/wp-json/sustainable-catalyst-library/v1/records`
* `/wp-json/sustainable-catalyst-library/v1/records/{id}`
* `/wp-json/sustainable-catalyst-library/v1/relationships`
* `/wp-json/sustainable-catalyst-library/v1/graph`
* `/wp-json/sustainable-catalyst-library/v1/roadmap`
* `/wp-json/sustainable-catalyst-library/v1/schemas`
* `/wp-json/sustainable-catalyst-library/v1/openapi.json`

== Changelog ==

= 2.2.0 =
* Evolves the existing sc_foundation_doc post type into a PDF-to-Document Knowledge Library.
* Converts text-based PDFs into editable, searchable, revisioned Knowledge Library documents while preserving the original PDF attachment.
* Adds hierarchical Document Families with Foundations as the default family.
* Adds Read Document, View Original PDF, Open PDF, and Download PDF public workflows.
* Adds Media Library Create Knowledge Document actions and bulk PDF record import.
* Adds local PDF.js browser extraction, optional pdftotext server extraction, page maps, checksums, summaries, and extraction status.
* Preserves existing Foundation Document records and redirects legacy /foundations/{slug}/ URLs to /documents/{slug}/.


= 2.1.3 =
* Connects page-based Foundation Documents to the established Foundations Documentation Library shortcode.
* Ensures the Foundations page lists Foundation Document pages rather than ordinary blog posts.
* Replaces the oversized iframe presentation with a native, iframe-free PDF object embed.
* Reuses the existing cc-rl-v2 Sustainable Catalyst page style.
* Replaces rounded document cards with a restrained document-index layout.


= 2.1.2 =
* Prevents Foundation Documents from publishing without a valid Media Library PDF.
* Adds Foundation Docs Health with route diagnostics, PDF attachment checks, and one-click repairs.
* Flags documents that need a PDF in the admin list.
* Adds a visible viewer fallback, accessible PDF controls, file metadata, and mobile improvements.
* Hardens Foundation Document search and pagination.
* Preserves the stable page-style editor regardless of site-wide editor settings.


= 2.1.1 =
* Repairs Foundation Document single-page routing and forces a fresh `/foundations/{slug}/` rewrite flush.
* Removes the query mutation that could turn a valid Foundation Document request into a 404.
* Moves the Select PDF control directly below the title so it cannot be removed with legacy metaboxes.
* Uses the canonical plugin asset URL for the Media Library selector script and styling.
* Keeps Foundation Docs separate from posts, categories, tags, feeds, and unrelated Library results.



= 2.1.0 =
* Adds page-like Foundation Document publishing with a title, optional introduction, Media Library PDF selector, and automatic embedded reader.
* Adds `[sc_foundation_documents]` for a Foundations-only public document listing.
* Removes Foundation Docs from blog categories, tags, archives, feeds, navigation menus, and unrelated public Library queries.
* Preserves advanced PDF extraction and citation controls behind an explicit advanced-editor link.


= 2.0.1 =
* Repaired the native topics, relationships, and pathways discovery interface.
* Added a unified `/library/discovery` endpoint with the `sc-library-discovery/1.0` contract.
* Added plugin-owned responsive discovery CSS insulated from page-level layout overrides.
* Added live counts, loading states, empty states, retry controls, and aggregate/fallback loading.
* Preserved dynamic discovery above search results and the manual topic architecture below the Library page.
* Preserved all v2.0.0 Living Knowledge System capabilities and v1.x compatibility.

= 2.0.0 =
* Unified Public Knowledge, Research Workspace, and Institutional Operations into a Living Knowledge System.
* Added a public portal, unified workspace gateway, and system status shortcode.
* Added checksummed system manifests and privacy-aware cross-module activity.
* Added system REST routes, developer schema, and webhook event.
* Added portable export schema 3.0 with system manifests and events.
* Preserved all v1.20.0 features and specialist tools.


= 1.20.0 =
* Added the Production Readiness dashboard and public status summary.
* Added keyboard focus, skip links, live announcements, reduced-motion, forced-colors, responsive tables, and mobile touch-target hardening.
* Added bounded anonymous public REST caching with content-driven generation invalidation.
* Added route-specific anonymous rate limiting and response-security headers.
* Added runtime, index, cron, privacy, preservation, PDF, and backup-boundary diagnostics.
* Added daily readiness evaluation, maintenance-schedule repair, readiness history, and portable readiness exports in schema 2.1.


= 1.19.0 =
* Added immutable institutional snapshots with SHA-256 source and manifest checksums.
* Added bounded integrity audits for content drift, attachments, authority URLs, supersession chains, and relationships.
* Added append-only authority history, record retention dates, legal holds, and protected cleanup.
* Added public historical browsing, version comparison, canonical-record links, and downloadable preservation manifests.
* Added preservation, integrity, and authority REST routes and webhook events.
* Added PostgreSQL entities `preservation_snapshots`, `integrity_checks`, and `authority_history` in portable schema 2.0.


= 1.18.1 =
* Added the Foundation Document record type and Media Library PDF selector.
* Added bundled PDF.js inline reading with explicit open/download controls and mobile fallback.
* Added page-aware full-text PDF extraction, indexing, search snippets, and diagnostics.
* Added Research Librarian synchronization and exact-page recommendation evidence.
* Added document metadata, version history, related records, and citation exports.
* Added Foundation PDF Migration for existing direct-download links.
* Added public Foundation Document API routes and portable schema 1.9.


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
