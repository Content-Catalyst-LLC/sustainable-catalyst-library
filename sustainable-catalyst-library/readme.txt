=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, content-planner, roadmap, dependencies, release-management, postgresql
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.11.0
License: GPLv2 or later

A native WordPress knowledge base with planning analytics, dependencies, release coordination, a public registry, documentation authority, research workspaces, custom books, and PostgreSQL portability.

== Description ==

Sustainable Catalyst Library v1.11.0 adds planning analytics, dependency intelligence, and release coordination.

= Planning Analytics =

* Active, completed, blocked, overdue, unscheduled, and high-priority totals.
* Due-soon and recent-publication metrics.
* Planned-versus-actual timing variance and on-time rate.
* Average planning completeness.
* Workload and progress by area, product, owner, status, type, and release group.
* Coverage-gap diagnostics for missing sources, areas, products, responsible areas, release windows, artifacts, questions, and article-map links.

= Dependencies =

* Dependency policies for all, any, or informational relationships.
* Resolved and unresolved dependency counts.
* Missing dependency detection.
* Circular-dependency detection.
* Native dependency graph without third-party libraries.

= Release Coordination =

* Release groups, tracks, milestones, capacity owners, effort estimates, actual effort, and progress.
* Planned and actual start dates.
* Manual blockers and blocker notes.
* Release-window capacity thresholds.
* Over-capacity, blocked, and overdue release warnings.
* Printable roadmap reports.

= Public Interfaces =

* Aggregate public planning analytics.
* Public release roadmap.
* Existing complete public registry and roadmap tracker.
* Public results include only plans deliberately marked for the public roadmap.

= Portable Data =

* PostgreSQL portable-export schema v1.1.
* Expanded planning fields in the normalized plans table.
* Normalized plan_dependencies table.
* SQL, CSV bundle, JSONL bundle, and JSON export formats remain available.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Rebuild the Library index after replacing an earlier version.
4. Open SC Library → Planning Analytics.
5. Open SC Library → Release Coordination and set a capacity threshold.
6. Edit planned records to add effort, ownership, progress, milestones, and blockers.

== Shortcodes ==

* `[sc_library_planning_analytics]`
* `[sc_library_release_coordination]`
* `[sc_library_registry mode="public"]`
* `[sc_library_planner_tracker mode="public"]`
* `[sc_library_portability]`
* `[sc_library_notebook tab="portability"]`
* `[sc_library mode="registry"]`
* `[sc_library collection="foundations" mode="documentation"]`
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

* `/wp-json/sustainable-catalyst/v1/library/planning/analytics`
* `/wp-json/sustainable-catalyst/v1/library/planning/dependencies`
* `/wp-json/sustainable-catalyst/v1/library/planning/releases`
* `/wp-json/sustainable-catalyst/v1/library/planning/coordination-schema`
* `/wp-json/sustainable-catalyst/v1/library/export/formats`
* `/wp-json/sustainable-catalyst/v1/library/export/postgresql-schema`

== Changelog ==

= 1.11.0 =

* Added planning analytics, workload summaries, velocity metrics, and completeness reports.
* Added release groups, tracks, milestones, capacity owners, effort, progress, and start dates.
* Added dependency policies, blocker state, dependency graph, and cycle detection.
* Added release-window capacity thresholds and coordination warnings.
* Added planned-versus-actual timing and on-time-rate analytics.
* Added printable administrator reports and public planning shortcodes.
* Added planning analytics, dependency, release, and schema REST endpoints.
* Added CSV and JSON planning analytics exports.
* Expanded PostgreSQL export schema to sc-library-portable-export/1.1.
* Added normalized plan_dependencies export table.

= 1.10.0 =

* Added PostgreSQL and portable research-data export.

= 1.9.0 =

* Added Content Planner, Article Map Planner, public registry, and roadmap tracker.
