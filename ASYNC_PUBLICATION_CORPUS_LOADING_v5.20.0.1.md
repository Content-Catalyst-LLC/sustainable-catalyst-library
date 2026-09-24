# Knowledge Library v5.20.0.1 — Asynchronous Publication Corpus Loading & Transport Repair

v5.20.0.1 changes the delivery path for the canonical Publication Library corpus without changing the v5.20 analytical model.

## Problem repaired

v5.20.0 assembled the full corpus during WordPress shortcode rendering and transported the canonical publication manifest to Python in a GET query string. As the corpus and 4D/linked-view payload grew, that path became vulnerable to page-render timeouts, URL-length limits, and silent empty-graph fallback.

## New transport

1. `[sc_library_knowledge_landscape]` renders its scientific shell immediately in corpus mode.
2. Browser JavaScript POSTs a compact request to the public WordPress REST proxy.
3. WordPress resolves the canonical `SC_Library_Publications` manifest at request time.
4. WordPress POSTs the manifest as JSON to `/v1/publication-knowledge-maps/corpus` on the Python backend.
5. Python returns the same governed corpus analytical payload used by v5.20.0.
6. The browser hydrates metrics, semantic state, accessible graph data, linked scientific views, and the 4D terrain.

Single-publication mode remains synchronous for compatibility.

## User-visible states

The scientific shell now distinguishes connecting/loading/ready/error states and exposes a retry action. Backend and transport failures are no longer converted silently into an empty graph.

## Boundaries preserved

The patch does not change topic-region computation, 4D terrain semantics, linked visual query, citation handling, semantic-similarity interpretation, or Platform Core responsibility boundaries.
