=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, documentation, research, notebook, book-builder, annotation, sources
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.8.0
License: GPLv2 or later

A native WordPress knowledge base with relationships, research workspaces, custom books, and a living institutional documentation library.

== Description ==

Sustainable Catalyst Library v1.8.0 adds the Foundations Documentation Library. It presents a compact, searchable institutional documentation interface while preserving the complete Library record, relationships, annotations, sources, Notebook objects, Translation Matrices, Whiteboards, Chalkboards, connected-tool handoffs, and custom books.

The documentation interface does not use an iframe and does not duplicate the Publications archive. It treats current webpages, repository documentation, methodology pages, release records, PDFs, and archives as different source types with explicit authority and version rules.

= Foundations Documentation Library =

* Search titles, descriptions, metadata, keywords, and indexed document text.
* Filter by documentation category, status, responsible area, archive visibility, and update order.
* Feature living documentation and current institutional references.
* Expand document panels without immediately opening a PDF.
* Open the current webpage, full Library record, PDF snapshot, repository, release record, document history, or correction route.
* Show version, last-updated, last-reviewed, responsible-area, and authoritative-source information.
* Show related documents, dependencies, supersedes, and superseded-by records.

= Documentation authority =

* Living documentation
* Current
* PDF snapshot
* Draft
* Superseded
* Archived

Authority types include current public webpage, repository documentation, methodology page, repository release record, published PDF, archived document, and custom source.

= Administration =

The Documentation Authority panel stores document status, type, version, responsible area, source authority, webpage, repository, PDF, release, review date, review interval, dependencies, history, and correction route.

The SC Library dashboard reports missing authority, missing category, missing area, published drafts, overdue reviews, missing replacements, and circular references.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Enable the Foundations Documentation Library.
4. Confirm the main Research Library page URL.
5. Save settings and rebuild the Library index.
6. Assign records to the Foundations Documentation Library collection.
7. Assign Documentation Categories and complete the Documentation Authority panel.
8. Add `[sc_library collection="foundations" mode="documentation"]` to a dedicated Shortcode block.

== Shortcodes ==

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

= 1.8.0 =

* Added the Foundations Documentation Library and curated collection view.
* Added Library Collections and Documentation Categories taxonomies.
* Added document status, version, responsible area, authority, review, dependency, and history metadata.
* Added featured living documentation and expandable public document panels.
* Added current-page, repository, release, PDF, history, full-record, and correction actions.
* Added source-of-truth indicators and warnings for snapshots, drafts, superseded records, archives, and repository-governed technical behavior.
* Added documentation administration diagnostics.
* Added documentation REST endpoints and the `[sc_foundations_library]` shortcode.
* Added `collection="foundations" mode="documentation"` support to `[sc_library]`.
* Extended relationship types for documentation governance and history.

= 1.7.0 =

* Added the local-first Custom Book Builder and browser PDF workflow.

= 1.6.0 =

* Added the local-first Annotation Studio and handwriting layers.
