=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, relationships, library, series, concepts, search, rest-api, indexing
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later

A native relationship-aware WordPress knowledge base for Sustainable Catalyst publications, series, concepts, resources, and research navigation.

== Description ==

Sustainable Catalyst Library v1.1.0 turns the compact v1.0.1 navigator into a structured knowledge system.

Features:
* Dedicated relationship table with typed directional relationships
* Library Series taxonomy with ordered previous and next navigation
* Library Concepts taxonomy
* Stable Library record identifiers
* Primary domain and evidence-status metadata
* GitHub, dataset, video, and Workbench resource metadata
* Editor-side Library Relationships panel
* Compact search-first public interface
* Nested topic navigation with index-aware counts
* Series and concept browsing
* Rich record panels with hierarchy, sequence, resources, and relationship groups
* Workbench handoff URLs carrying record identifiers
* Suggested related knowledge based on series, concepts, and categories
* Public REST endpoints for records, relationships, series, concepts, and pathways
* Upgrade-safe schema migration from v1.0.1

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Confirm the public post types to include.
4. Save settings and click Rebuild Library Index.
5. Assign Library Series and Library Concepts from the publication editor.
6. Use the Library Relationships panel to add placement, resources, evidence status, and typed connections.
7. Add the shortcode to a dedicated Shortcode block:
   [sc_library mode="compact" initial_results="0" show_header="false"]

== REST API ==

* /wp-json/sustainable-catalyst/v1/library/status
* /wp-json/sustainable-catalyst/v1/library/categories
* /wp-json/sustainable-catalyst/v1/library/series
* /wp-json/sustainable-catalyst/v1/library/concepts
* /wp-json/sustainable-catalyst/v1/library/pathways
* /wp-json/sustainable-catalyst/v1/library/items
* /wp-json/sustainable-catalyst/v1/library/items/{id}
* /wp-json/sustainable-catalyst/v1/library/items/{id}/related

== Changelog ==

= 1.1.0 =
* Added a dedicated typed relationship table.
* Added Library Series and Library Concepts taxonomies.
* Added publication-editor controls for primary domain, series order, evidence status, code, datasets, videos, Workbench tools, and explicit relationships.
* Expanded the index with stable identifiers, domain placement, series, concepts, and resource flags.
* Added series, concepts, pathways, and related-record REST endpoints.
* Rebuilt record panels with breadcrumbs, series navigation, resource links, Workbench handoffs, typed relationship groups, and suggested related knowledge.
* Added series and concept browsing to the public knowledge-base interface.

= 1.0.1 =
* Rebuilt the public interface as a compact knowledge-base navigator.

= 1.0.0 =
* Initial structured Library release.
