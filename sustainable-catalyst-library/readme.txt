=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, multimedia, video, audio, evidence-reels, pdf, render, research-workspace, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.15.0
License: GPLv2 or later

A native WordPress knowledge system with editorial collaboration, reviews, suggested edits, multimedia, persistent workspaces, server-rendered books and PDFs, planning, notebooks, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v1.15.0 adds a native collaboration, review, and editorial workflow layer while retaining the complete v1.14.1 Library, Multimedia Studio, large-library scanner, persistent workspaces, server document production, and portable data systems.

= Collaboration and Editorial Workflow =

* Create editorial reviews linked to posts, workspaces, books, boards, documents, plans, or multimedia records.
* Invite Observers, Reviewers, Editors, and Approvers.
* Support existing WordPress users and expiring email invitations.
* Add threaded comments with open and resolved states.
* Record suggested edits with accepted, rejected, withdrawn, and pending states.
* Coordinate intake, drafting, review, fact-check, accessibility, approval, scheduling, publication, and archive states.
* Protect concurrent editing through revision checks and expiring record locks.
* Preserve attributed activity and decision history.
* Synchronize accepted workspace-review roles with persistent workspace access.
* Export editorial records through PostgreSQL, CSV, JSONL, and JSON.

= Retained systems =

* Public record-card responsive repair from v1.14.1.
* Multimedia assets, clips, evidence reels, transcripts, rights, and optional Render processing.
* Cursor-based large-library indexing and database inventory.
* Persistent account workspaces and optional Render/PostgreSQL synchronization.
* Server-side book and PDF production.
* Content Planner, release coordination, Notebook, boards, annotations, books, and portable exports.

= Portable Data =

* Workspace schema `sc-library-workspace/1.8`.
* Editorial workflow schema `sc-library-editorial-workflow/1.0`.
* Portable export schema `sc-library-portable-export/1.5`.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library and rebuild the index.
3. Open SC Library → Editorial Workflow.
4. Create a private test review linked to a post or workspace.
5. Invite an existing WordPress user as Reviewer or Editor.
6. Test a comment, suggested edit, edit lock, and approval transition.
7. Configure Multimedia Studio or Render services separately when needed.

== Shortcodes ==

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

* `/wp-json/sustainable-catalyst/v1/library/editorial/schema`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/transition`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/comments`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/suggestions`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/participants`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/lock`
* `/wp-json/sustainable-catalyst/v1/library/editorial/reviews/{uuid}/activity`

* `/wp-json/sustainable-catalyst/v1/library/media/status`
* `/wp-json/sustainable-catalyst/v1/library/media/assets`
* `/wp-json/sustainable-catalyst/v1/library/media/clips`
* `/wp-json/sustainable-catalyst/v1/library/media/clips/{uuid}/process`
* `/wp-json/sustainable-catalyst/v1/library/media/reels`
* `/wp-json/sustainable-catalyst/v1/library/media/reels/public/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/media/jobs`
* `/wp-json/sustainable-catalyst/v1/library/media/jobs/{uuid}/refresh`
* `/wp-json/sustainable-catalyst/v1/library/media/jobs/{uuid}/retry`

* `/wp-json/sustainable-catalyst/v1/library/documents/status`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/refresh`
* `/wp-json/sustainable-catalyst/v1/library/documents/jobs/{uuid}/retry`
* `/wp-json/sustainable-catalyst/v1/library/documents/editions`
* `/wp-json/sustainable-catalyst/v1/library/workspaces`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}`

== Changelog ==

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
