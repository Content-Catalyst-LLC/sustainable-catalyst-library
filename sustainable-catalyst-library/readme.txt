=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, notebook, whiteboard, chalkboard, translation-matrix, research, sources, citations, relationships, collections, search, rest-api
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPLv2 or later

A native relationship-aware WordPress knowledge base with a local-first Research Notebook, sources, Technical Translation Matrices, Whiteboards, and Chalkboards.

== Description ==

Sustainable Catalyst Library v1.4.0 adds visual Whiteboards and technical Chalkboards to the local-first research workspace.

Features:
* Compact search-first public knowledge-base interface
* Structured WordPress publication index
* Library Series and Library Concepts taxonomies
* Typed directional knowledge relationships
* Rich record panels with hierarchy, sequence, resources, and Workbench handoffs
* Browser-local Research Notebook
* Named collections and Research Inbox
* Notes attached to Library records, outside sources, translation matrices, or visual boards
* External website, journal, report, dataset, video, podcast, interview, archive, and custom-source records
* Physical books and book chapters with ISBN, edition, page, chapter, and location fields
* APA, MLA, Chicago, Harvard, plain-text, BibTeX, RIS, and CSL JSON citations
* Configurable Technical Translation Matrices with cell-level validation and provenance
* Editable Whiteboards for concept maps, evidence maps, systems maps, and synthesis
* Editable Chalkboards for equations, derivations, code logic, validation, and interpretation
* Draggable Concept, Note, Question, Claim, Evidence, Source, Publication, Matrix, Equation, Code, and Result cards
* Typed labeled connectors between board cards
* Pen, highlighter, eraser, and stylus-ready handwriting
* Board links to Library records, notes, outside sources, matrices, and collections
* Record-panel and result-row Whiteboard and Chalkboard actions
* Board JSON, SVG, PNG, and landscape print/PDF-ready exports
* Versioned workspace JSON import and export with v1.2 and v1.3 migration
* Standalone Notebook, Matrix, Whiteboard, Chalkboard, and combined Boards shortcodes
* Public REST discovery endpoints for matrix and board templates

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Confirm the public post types to include.
4. Keep Research Notebook, Technical Translation Matrix, and Whiteboards and Chalkboards enabled.
5. Save settings and click Rebuild Library Index.
6. Add the Library shortcode to a dedicated Shortcode block:
   [sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]
7. Optionally add a standalone Notebook page with:
   [sc_library_notebook]
8. Optionally add a standalone Matrix Studio page with:
   [sc_library_translation_matrix]
9. Optionally add standalone visual board launchers with:
   [sc_library_whiteboard]
   [sc_library_chalkboard]
   [sc_library_boards]

== Local Storage ==

Research Notebook, matrix, Whiteboard, and Chalkboard data are private to the current browser profile in v1.4.0. They are not stored in WordPress. The release keeps the v1.2 local-storage key and migrates compatible sc-library-workspace/1.0 and sc-library-workspace/1.1 exports into sc-library-workspace/1.2. Export a JSON backup before clearing browser data or changing devices.

== Whiteboards and Chalkboards ==

Whiteboards support concept maps, evidence maps, systems maps, source organization, claims, questions, and visual synthesis. Chalkboards provide a dark technical canvas for equations, derivations, code, algorithms, validation checks, and systems interpretation.

Board capabilities:
* Draggable and resizable typed cards
* Labeled directional relationships
* Pen, highlighter, and eraser tools
* Mouse, touch, and stylus pointer input
* Saved-record, outside-source, note, and matrix cards
* Collection membership and provenance attachments
* JSON export with complete editable board data
* SVG export for vector reuse
* PNG export for documents and presentations
* Landscape print view suitable for Save as PDF

== Technical Translation Matrix ==

A matrix can translate a Library concept across plain language, mathematical notation, algorithms, programming languages, relational data logic, systems interpretation, assumptions, validation, and source provenance. Matrices can be moved into Whiteboards and Chalkboards as visual research cards.

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
* /wp-json/sustainable-catalyst/v1/library/board-templates

== Changelog ==

= 1.4.0 =
* Added editable Whiteboards and Chalkboards as first-class Notebook records.
* Added Concept Map, Evidence Map, Systems Map, Equation Workbench, and Technical Derivation templates.
* Added draggable typed research cards and labeled directional relationships.
* Added pen, highlighter, eraser, handwriting, and stylus-ready pointer input.
* Added Library record, note, source, matrix, and collection handoffs.
* Added Whiteboard and Chalkboard actions to Library results and record panels.
* Added JSON, SVG, PNG, and landscape print/PDF-ready board exports.
* Added [sc_library_whiteboard], [sc_library_chalkboard], and [sc_library_boards] shortcodes.
* Added the board-templates REST endpoint.
* Updated workspace schema to sc-library-workspace/1.2 with v1.2 and v1.3 import migration.

= 1.3.0 =
* Added the Technical Translation Matrix, reusable templates, cell review states, source references, and matrix exports.

= 1.2.0 =
* Added the local-first Research Notebook, collections, notes, outside sources, citations, and portable workspace export.

= 1.1.0 =
* Added typed relationships, Library Series, Library Concepts, record panels, and Workbench handoffs.

= 1.0.1 =
* Rebuilt the public interface as a compact knowledge-base navigator.

= 1.0.0 =
* Initial structured Library release.
