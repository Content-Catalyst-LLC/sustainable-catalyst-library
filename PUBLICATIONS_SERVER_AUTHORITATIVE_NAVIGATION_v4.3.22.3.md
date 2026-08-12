# Publications Server-Authoritative Navigation — v4.3.22.3

## Purpose

v4.3.22.3 removes JavaScript interception from major Publications field navigation. The browser URL and WordPress render path are now authoritative for selecting a field.

## Core rule

A field link is a normal anchor containing a field query parameter. Clicking it is never cancelled by JavaScript.

- `[sc_publications]` uses `sc_publications_field` and `sc_publications_map`.
- `[sc_field_spotlights]` uses `sc_publication_field` and `sc_publication_panel`.

The selected field/panel is read by PHP and rendered on the server. The resulting URL can be refreshed, copied, bookmarked, opened in a new tab, or revisited without depending on a browser runtime.

## JavaScript boundary

JavaScript may still provide harmless within-field enhancements such as autoplay, previous/next playback controls, progress state, disclosure controls, and mobile select navigation. It may not cancel direct major-field navigation. Direct Article Map / panel anchors are also left as native server-authoritative links.

## Failure model

If JavaScript is stale, delayed, blocked, or fails entirely:

1. Major field links still work.
2. Direct Article Map / panel links still work.
3. WordPress renders the requested field and selected panel.
4. Global Governance remains only the default when no field is requested.

This eliminates the previous failure mode where JavaScript could leave the shared stage visually trapped on Global Governance.

## Preserved systems

- 14 canonical Publications fields
- 170 Article Maps
- Field Spotlight autoplay and within-field controls
- Publications integrity recovery
- Citation Studio / My Sources
- Open Course Finder and Learning Pathways
- Research Access and Digital Access Resolver
- Research Librarian and Workspace integrations

No Publications page-body or Research Library page-body replacement is required.
