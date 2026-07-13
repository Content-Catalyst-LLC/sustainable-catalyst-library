=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, research-workspace, synchronization, postgresql, render, notebook
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.12.0
License: GPLv2 or later

A native WordPress knowledge base with persistent research workspaces, planning, documentation, notebooks, books, PostgreSQL portability, and optional Render synchronization.

== Description ==

Sustainable Catalyst Library v1.12.0 adds persistent WordPress account workspaces and optional Render/PostgreSQL synchronization while preserving browser-local research mode.

= Persistent Workspaces =

* Local, account, and hybrid storage modes.
* Local-to-account migration and cross-device loading.
* Explicit saves, optional autosave, revisions, content hashes, and recovery history.
* Private, shared, and public visibility.
* Viewer and editor collaborators tied to WordPress accounts.
* Conflict detection prevents silent overwrites.

= Render Synchronization =

* Optional FastAPI/PostgreSQL service.
* Signed server-to-server requests.
* Independent online, pending, synced, conflict, and error states.
* WordPress remains the account and permission authority.
* Browser clients never receive the Render API key or database URL.

= Portable Data =

* Workspace schema `sc-library-workspace/1.7`.
* Portable export schema `sc-library-portable-export/1.2`.
* PostgreSQL-ready account workspace, revision, collaborator, and sync-log tables.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library and rebuild the index.
3. Enable persistent workspaces.
4. Test account save and load before enabling autosave.
5. Optionally deploy the included Render service and configure its server key.

== Shortcodes ==

* `[sc_library_account_workspaces]`
* `[sc_library_notebook tab="sync"]`
* `[sc_library_notebook]`
* `[sc_library]`
* `[sc_library_registry mode="public"]`
* `[sc_library_planning_analytics]`
* `[sc_library_release_coordination]`
* `[sc_library_portability]`
* `[sc_library_book_builder]`
* `[sc_library_annotation_studio]`
* `[sc_library_translation_matrix]`
* `[sc_library_whiteboard]`
* `[sc_library_chalkboard]`

== REST API ==

* `/wp-json/sustainable-catalyst/v1/library/account/status`
* `/wp-json/sustainable-catalyst/v1/library/workspaces`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/history`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/share`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/sync`
* `/wp-json/sustainable-catalyst/v1/library/workspaces/render/status`

== Changelog ==

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
