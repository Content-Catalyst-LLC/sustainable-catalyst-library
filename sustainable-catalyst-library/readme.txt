=== Sustainable Catalyst Library ===
Contributors: contentcatalyst
Tags: library, search, filters, rest-api, indexing
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later

Structured WordPress indexing, REST API, category navigation, search, and filters for the Sustainable Catalyst Library.

== Description ==

Sustainable Catalyst Library v1.0.0 transforms WordPress publications into a structured, searchable Library interface.

Features:
* Dedicated database index for published content
* Automatic reindexing on post and taxonomy changes
* Full-library search
* Category navigation
* Sorting and pagination
* REST API endpoints
* Configurable public post types
* Admin index health and rebuild controls
* Responsive institutional interface
* Shortcode: [sc_library]

== Installation ==

1. Upload and activate the plugin.
2. Open SC Library in WordPress administration.
3. Select the public post types to include.
4. Click Rebuild Library Index.
5. Add [sc_library] to the Library page.

== REST API ==

* /wp-json/sustainable-catalyst/v1/library/status
* /wp-json/sustainable-catalyst/v1/library/categories
* /wp-json/sustainable-catalyst/v1/library/items

== Changelog ==

= 1.0.0 =
* Initial structured Library release.
* Added WordPress indexing, REST API, categories, search, filters, sorting, pagination, admin controls, and public shortcode interface.
