=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, content-planner, public-registry, roadmap, documentation, research
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.9.0
License: GPLv2 or later

A native WordPress knowledge base with a content planner, complete public registry, roadmap tracker, documentation authority, research workspaces, and custom books.

== Description ==

Sustainable Catalyst Library v1.9.0 adds the Content Planner, Complete Public Registry, and Roadmap Tracker.

The plugin can register planned articles and documentation, scan article maps, bulk-create missing planned entries, display planned records in Library results, track optional release expectations, convert plans into WordPress drafts, and reconcile them when the canonical post publishes.

The public registry combines enabled published articles, pages, living documentation, planned content, active development, scheduled records, PDF snapshots, superseded documents, and archives. Each state remains visibly distinct.

= Content Planner =

* Native WordPress planned-content records with revisions and REST support.
* Idea, Proposed, Planned, Researching, Drafting, In Review, Scheduled, Published, Deferred, Cancelled, and Superseded states.
* Article, article map, documentation, product brief, methodology, dataset, calculator, code, video, pathway, PDF, release, policy, and custom types.
* Optional expected release date, month, quarter, year, product release, or no date.
* Area, product, responsibility, audience, research questions, sources, dependencies, and expected artifacts.
* Public and private planning boundaries.

= Article Map Planner =

* Scan headings and links from existing article maps.
* Detect published, draft, planned, and missing entries.
* Create selected missing entries in bulk.
* Inherit compatible taxonomies and sequence information.

= Complete Public Registry =

* Search published posts, documentation, planned content, active development, and historical records.
* Filter by state, type, area, product, collection, article map, archive visibility, and expected release.
* Export registry results as CSV or JSON.
* Use Foundations-filtered views without duplicating records.

= Roadmap Tracker =

* Count records by area, product, status, content type, and article map.
* Show published/current, in-development, planned, historical, and total counts.
* Warn about missing areas, overdue expectations, scheduled items without drafts, and published plans without canonical posts.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Enable Content Planner and public registry.
4. Save settings and rebuild the Library index.
5. Create planned records or scan an article map.
6. Add `[sc_library_registry]` or `[sc_library_planner_tracker]` to dedicated Shortcode blocks.

== Shortcodes ==

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

== Changelog ==

= 1.9.0 =

* Added native planned-content records and planning metadata.
* Added optional expected release windows.
* Added Article Map Planner scanning and bulk planned-entry creation.
* Added planned-to-draft and planned-to-published workflows.
* Added the complete public registry and public roadmap tracker.
* Added counts by area, product, status, type, and article map.
* Added registry search, filters, CSV export, and JSON export.
* Added planned records to normal Library search when public.
* Added public/private planning boundaries and historical-state labels.

= 1.8.0 =

* Added the Foundations Documentation Library and authority model.

= 1.7.0 =

* Added the local-first Custom Book Builder and browser PDF workflow.

= 1.6.0 =

* Added the local-first Annotation Studio and handwriting layers.
