=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: knowledge-base, library, search, filters, rest-api, indexing
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later

A compact native WordPress knowledge base for Sustainable Catalyst publications, topics, pathways, and related research records.

== Description ==

Sustainable Catalyst Library v1.0.1 replaces the archive-style public interface with a compact knowledge-base navigator.

Features:
* Search-first interface with no initial article wall by default
* Expandable nested topic architecture
* Index-aware topic counts
* Broad-domain filtering that includes child categories
* Compact text-based knowledge records
* Context panels with related publications
* Featured pathways
* Recently opened records stored locally in the browser
* Persistent search and topic URLs
* Relevance, updated, date, and alphabetical sorting
* Stale-record cleanup during reconciliation
* REST API endpoints
* Configurable post types and interface settings
* Shortcode modes for compact, full, search, domains, and pathways

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Select the public post types to include.
4. Click Rebuild Library Index.
5. Add the recommended shortcode to a dedicated Shortcode block:
   [sc_library mode="compact" initial_results="0" show_header="false"]

== REST API ==

* /wp-json/sustainable-catalyst/v1/library/status
* /wp-json/sustainable-catalyst/v1/library/categories
* /wp-json/sustainable-catalyst/v1/library/items
* /wp-json/sustainable-catalyst/v1/library/items/{id}

== Changelog ==

= 1.0.1 =
* Rebuilt the public interface as a compact knowledge-base navigator.
* Removed featured-image grids and automatic publication flooding.
* Added hidden initial results, nested topic drawers, index-aware counts, contextual record panels, featured pathways, and recent-record history.
* Added child-category filtering, relevance ordering, stale-record cleanup, interface modes, and new administration controls.

= 1.0.0 =
* Initial structured Library release.
