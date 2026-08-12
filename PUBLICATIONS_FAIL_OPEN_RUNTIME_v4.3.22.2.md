# Publications Fail-Open Runtime — v4.3.22.2

## Problem

The Publications registry and server-rendered selector could be complete while the shared stage remained on Global Governance. Both Publications runtimes enhanced real links with JavaScript, but the click handlers cancelled normal navigation before proving the in-place switch had completed. A runtime exception therefore stranded the interface on the first server-rendered field.

## v4.3.22.2 contract

1. Field and panel controls remain real server-authoritative links.
2. Modified clicks are never intercepted.
3. JavaScript attempts an in-place switch first.
4. The new field/panel is verified against runtime state and rendered DOM.
5. Only after verification does JavaScript call `preventDefault()`.
6. Any exception or verification failure leaves native navigation intact.
7. A failed runtime removes enhanced-only presentation state and records a diagnostic `data-*-runtime-state="fallback"` marker.
8. Core Publications and Field Spotlight share the v4.3.22.2 asset boundary.

## Server fallbacks

Core Publications uses `sc_publications_field` and `sc_publications_map`.
Field Spotlight uses `sc_publication_field` and `sc_publication_panel`.
The respective PHP templates consume those parameters before rendering the initial shared stage.

## Preserved boundaries

The patch does not reset the 14-field/170-Article-Map registry, editorial copy, panel ordering, curated article selections, Citation Studio, Research Access, Course Finder, Research Librarian, or Workspace data.
