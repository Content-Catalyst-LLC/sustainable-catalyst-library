=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, research, notebook, annotation, handwriting, whiteboard, sources
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.6.0
License: GPLv2 or later

A native WordPress knowledge base with relationships, notebooks, sources, matrices, visual boards, handwritten annotations, and connected research tools.

== Description ==

Sustainable Catalyst Library v1.6.0 adds a local-first Annotation Studio for handwritten notes, highlights, shapes, anchored comments, transcriptions, and reusable research annotations.

Annotations remain separate from canonical WordPress publications. They can be attached to Library records, Notebook notes, external or physical sources, Technical Translation Matrices, Whiteboards, Chalkboards, video references, book pages, and custom material.

= Annotation Studio =

* Pen, pencil, highlighter, eraser, rectangle, ellipse, arrow, and typed-note tools.
* Mouse, touch, and stylus input with pointer-pressure support where available.
* Separate handwriting, highlight, shape, and note layers.
* Layer visibility controls, undo, redo, and active-layer clearing.
* Reader, margin, lined, dot-grid, graph-paper, Cornell, blank, and dark technical page styles.
* Anchors for passages, sections, pages, figures, equations, and timestamps.
* Accessible handwriting transcription and private editorial notes.
* JSON, SVG, PNG, and print/PDF-ready exports.

= Notebook and source integration =

* Create annotations from Library result rows and record panels.
* Annotate saved publications, personal notes, outside sources, matrices, Whiteboards, and Chalkboards.
* Store annotations in Notebook collections.
* Create Notebook notes linked to annotation records.
* Include annotations in full-workspace and collection JSON exports.
* Use annotation records as context for connected-tool handoffs.

= Privacy and storage =

Personal annotations remain in browser-local storage in v1.6.0. They are not written to public WordPress tables and do not alter the original publication. Export a JSON backup before clearing browser data or changing devices.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Enable Annotation Studio and choose a default page style.
4. Confirm indexed post types and save settings.
5. Rebuild the Library index.
6. Add `[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]` in a Shortcode block.

== Shortcodes ==

* `[sc_library]`
* `[sc_library_notebook]`
* `[sc_library_notebook tab="annotations"]`
* `[sc_library_translation_matrix]`
* `[sc_library_whiteboard]`
* `[sc_library_chalkboard]`
* `[sc_library_boards]`
* `[sc_library_integrations]`
* `[sc_library_annotation_studio]`

== Changelog ==

= 1.6.0 =

* Added the local-first Annotation Studio and `sc-library-annotation/1.0` schema.
* Added pressure-aware pen, pencil, highlighter, and eraser input.
* Added rectangle, ellipse, arrow, movable note, and anchor tools.
* Added handwriting, highlight, shape, and typed-note layers.
* Added multiple reader and notebook page styles.
* Added accessible handwriting transcription and private editorial notes.
* Added annotation actions for publications, notes, sources, matrices, Whiteboards, and Chalkboards.
* Added JSON, SVG, PNG, and print/PDF-ready annotation exports.
* Added the `[sc_library_annotation_studio]` shortcode and Notebook Annotations tab.
* Added the `/library/annotation-schema` REST endpoint.
* Updated browser-local workspace schema to `sc-library-workspace/1.4` while preserving earlier exports.
* Preserved annotation objects through Board and connected-tool workspace saves.

= 1.5.0 =

* Added Workbench, Decision Studio, and Site Intelligence integration targets.

= 1.4.0 =

* Added editable Whiteboards and Chalkboards, typed cards and connectors, drawing tools, and visual exports.
