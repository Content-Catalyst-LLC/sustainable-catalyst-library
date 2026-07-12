=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, notebook, research, sources, citations, relationships, collections, search, rest-api
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later

A native relationship-aware WordPress knowledge base with a local-first Research Notebook, source collection, citations, and portable research-data export.

== Description ==

Sustainable Catalyst Library v1.2.0 adds a personal research layer to the relationship-aware knowledge base introduced in v1.1.0.

Features:
* Compact search-first public knowledge-base interface
* Structured WordPress publication index
* Library Series and Library Concepts taxonomies
* Typed directional knowledge relationships
* Rich record panels with hierarchy, sequence, resources, and Workbench handoffs
* Browser-local Research Notebook
* Working Save to Notebook and Write note actions
* Named collections and Research Inbox
* Notes attached to Library records or outside sources
* External website, journal, report, dataset, video, podcast, interview, archive, and custom-source records
* Physical books and book chapters with ISBN, edition, page, chapter, and location fields
* Source duplicate detection
* APA, MLA, Chicago, Harvard, plain-text, BibTeX, RIS, and CSL JSON citations
* Versioned JSON workspace import and export
* Clear local-data controls
* Standalone Research Notebook shortcode
* Public REST discovery endpoints for source types, citation formats, and source templates
* Upgrade-safe migration from v1.1.0

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Confirm the public post types to include.
4. Keep Research Notebook enabled.
5. Save settings and click Rebuild Library Index.
6. Add the Library shortcode to a dedicated Shortcode block:
   [sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
7. Optionally add a standalone Notebook page with:
   [sc_library_notebook]

== Local Storage ==

Research Notebook data is private to the current browser profile in v1.2.0. It is not stored in WordPress. Use the Import / Export tab to create a portable JSON backup before clearing browser data or changing devices.

== REST API ==

* /wp-json/sustainable-catalyst/v1/library/status
* /wp-json/sustainable-catalyst/v1/library/categories
* /wp-json/sustainable-catalyst/v1/library/series
* /wp-json/sustainable-catalyst/v1/library/concepts
* /wp-json/sustainable-catalyst/v1/library/pathways
* /wp-json/sustainable-catalyst/v1/library/items
* /wp-json/sustainable-catalyst/v1/library/items/{id}
* /wp-json/sustainable-catalyst/v1/library/items/{id}/related
* /wp-json/sustainable-catalyst/v1/library/source-types
* /wp-json/sustainable-catalyst/v1/library/citation-formats
* /wp-json/sustainable-catalyst/v1/library/source-template

== Changelog ==

= 1.2.0 =
* Added the local-first Research Notebook workspace.
* Added working save-record and record-linked note actions.
* Added named collections and a default Research Inbox.
* Added typed personal notes connected to saved Library records and source records.
* Added external and physical source records, including books, chapters, reports, datasets, videos, podcasts, interviews, and archival materials.
* Added DOI, ISBN, edition, page, chapter, timestamp, and physical-location fields.
* Added duplicate source detection.
* Added APA, MLA, Chicago, Harvard, plain text, BibTeX, RIS, and CSL JSON citation generation.
* Added versioned JSON import/export and local reset controls.
* Added the standalone [sc_library_notebook] shortcode.
* Added source-types, citation-formats, and source-template REST endpoints.

= 1.1.0 =
* Added typed relationships, Library Series, Library Concepts, record panels, and Workbench handoffs.

= 1.0.1 =
* Rebuilt the public interface as a compact knowledge-base navigator.

= 1.0.0 =
* Initial structured Library release.
