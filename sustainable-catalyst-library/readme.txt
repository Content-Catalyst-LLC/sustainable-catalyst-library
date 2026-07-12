=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, notebook, translation-matrix, research, sources, citations, relationships, collections, search, rest-api
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later

A native relationship-aware WordPress knowledge base with a local-first Research Notebook, source collection, and auditable Technical Translation Matrix.

== Description ==

Sustainable Catalyst Library v1.3.0 adds the Technical Translation Matrix to the local-first research workspace introduced in v1.2.0.

Features:
* Compact search-first public knowledge-base interface
* Structured WordPress publication index
* Library Series and Library Concepts taxonomies
* Typed directional knowledge relationships
* Rich record panels with hierarchy, sequence, resources, and Workbench handoffs
* Browser-local Research Notebook
* Named collections and Research Inbox
* Notes attached to Library records, outside sources, or translation matrices
* External website, journal, report, dataset, video, podcast, interview, archive, and custom-source records
* Physical books and book chapters with ISBN, edition, page, chapter, and location fields
* APA, MLA, Chicago, Harvard, plain-text, BibTeX, RIS, and CSL JSON citations
* Configurable Technical Translation Matrices
* Technical Translation, Equation-to-Code, Programming-Language Comparison, Source Comparison, and Cross-Domain templates
* Editable matrix rows and columns
* Per-cell Draft, Translated, Reviewed, Validated, Warning, and Unsupported states
* Per-cell source and provenance references
* Matrix links to Library records, outside sources, notes, and collections
* Record-panel and result-row “Open Translation Matrix” actions
* Matrix JSON, CSV, and landscape print/PDF-ready exports
* Versioned workspace JSON import and export with v1.2 migration
* Standalone Research Notebook and Translation Matrix shortcodes
* Public REST discovery endpoint for matrix templates and validation states

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Confirm the public post types to include.
4. Keep Research Notebook and Technical Translation Matrix enabled.
5. Save settings and click Rebuild Library Index.
6. Add the Library shortcode to a dedicated Shortcode block:
   [sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
7. Optionally add a standalone Notebook page with:
   [sc_library_notebook]
8. Optionally add a standalone Matrix Studio page with:
   [sc_library_translation_matrix]

== Local Storage ==

Research Notebook and matrix data are private to the current browser profile in v1.3.0. They are not stored in WordPress. The v1.3 release keeps the v1.2 local-storage key and migrates compatible sc-library-workspace/1.0 exports into sc-library-workspace/1.1. Export a JSON backup before clearing browser data or changing devices.

== Technical Translation Matrix ==

A matrix can translate a Library concept across plain language, mathematical notation, algorithms, programming languages, relational data logic, systems interpretation, assumptions, validation, and source provenance. Rows and columns can be added, renamed, or removed. Each cell can carry its own review state and source reference.

Matrix exports:
* JSON record with complete cells, review states, source links, and provenance
* CSV table for spreadsheets and data workflows
* Landscape browser print view suitable for Save as PDF
* Inclusion in the complete Notebook workspace JSON manifest

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
* /wp-json/sustainable-catalyst/v1/library/matrix-templates

== Changelog ==

= 1.3.0 =
* Added the Technical Translation Matrix as a first-class Notebook record.
* Added five reusable matrix templates.
* Added configurable matrix rows and columns.
* Added cell-level translation and validation states.
* Added cell-level source and provenance references.
* Added links from matrices to Library records, outside sources, notes, and collections.
* Added Open Translation Matrix actions to result rows and record panels.
* Added JSON, CSV, and landscape print/PDF-ready matrix exports.
* Added the [sc_library_translation_matrix] shortcode.
* Added the matrix-templates REST endpoint.
* Updated workspace schema to sc-library-workspace/1.1 with v1.2 import migration.

= 1.2.0 =
* Added the local-first Research Notebook, collections, notes, outside sources, citations, and portable workspace export.

= 1.1.0 =
* Added typed relationships, Library Series, Library Concepts, record panels, and Workbench handoffs.

= 1.0.1 =
* Rebuilt the public interface as a compact knowledge-base navigator.

= 1.0.0 =
* Initial structured Library release.
