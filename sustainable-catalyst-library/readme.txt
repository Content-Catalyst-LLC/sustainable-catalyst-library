=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, research, notebook, workbench, decision-support, site-intelligence
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.5.0
License: GPLv2 or later

A native WordPress knowledge base with relationships, notebooks, sources, matrices, visual boards, and cross-application research handoffs.

== Description ==

Sustainable Catalyst Library v1.5.0 connects Library records and local-first Notebook objects to Workbench, Decision Studio, and Site Intelligence through compact contextual panels and versioned handoff packets.

The release intentionally avoids iframes and duplicate full application interfaces. The Library prepares context; the connected application performs deeper calculation, decision analysis, or geographic investigation.

= Connected research tools =

* Library Workbench: equations, methods, variables, datasets, technical questions, validation, graphs, and analytical reports.
* Library Decision Studio: evidence synthesis, claims, assumptions, uncertainty, tradeoffs, research gaps, and decision canvases.
* Library Site Intelligence: countries, places, indicators, maps, events, datasets, source registry records, and freshness context.

= Handoff model =

* Versioned `sc-library-handoff/1.0` packet.
* Record-specific public context endpoint.
* Portable Notebook handoff JSON.
* Compact cross-origin URL-fragment payload.
* Stable Library identifiers and provenance.
* Service-specific launch URLs and health endpoints.

= Privacy and storage =

Personal Notebook data and saved handoffs remain in browser-local storage in v1.5.0. They are not written to public WordPress tables. Export a JSON backup before clearing browser data or changing devices.

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library.
3. Configure Workbench, Decision Studio, and Site Intelligence URLs plus optional health endpoints.
4. Select indexed post types and save settings.
5. Rebuild the Library index.
6. Add `[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]` in a Shortcode block.

== Shortcodes ==

* `[sc_library]`
* `[sc_library_notebook]`
* `[sc_library_translation_matrix]`
* `[sc_library_whiteboard]`
* `[sc_library_chalkboard]`
* `[sc_library_boards]`
* `[sc_library_integrations]`

== Changelog ==

= 1.5.0 =

* Added Workbench, Decision Studio, and Site Intelligence integration targets.
* Added `sc-library-handoff/1.0` context packet schema.
* Added record-level connected-tool panels and Notebook handoff builder.
* Added service launch and optional health endpoint configuration.
* Added cached independent connection status checks.
* Added editor metadata for technical questions, decision questions and methods, places, indicators, and source IDs.
* Added REST integration registry, status, schema, and record handoff endpoints.
* Added standalone `[sc_library_integrations]` shortcode and Notebook Connected Tools tab.
* Updated browser-local workspace schema to `sc-library-workspace/1.3` while preserving earlier exports.

= 1.4.0 =

* Added editable Whiteboards and Chalkboards, typed cards and connectors, drawing tools, and visual exports.
