# Dynamic Library Explorer v5.6.0

## Design goal

Make the Research Library feel like a research application rather than a long catalog/documentation page.

## Runtime flow

```text
Public Library page
    ↓
small Explorer shell
    ↓
WordPress public Explorer proxy
    ├── Python backend healthy → PostgreSQL research index
    └── backend unavailable → bounded WordPress-local fallback
    ↓
12 results at a time
    ↓
quick view → provenance / related / timeline on demand
```

## Progressive disclosure boundaries

The initial page does **not** load:

- all Library records;
- full record bodies;
- record chunks;
- related-record lists;
- record timelines;
- Research Notebook;
- Citation Studio;
- document builder;
- research rooms;
- team libraries;
- federation console;
- the unified research workspace.

Those capabilities remain available but are entered intentionally instead of all mounting into the landing page.

## Explorer state

Search and filter state is represented in URL parameters:

- `library_q`
- `library_topic`
- `library_type`
- `library_source`
- `library_from`
- `library_to`
- `library_sort`
- `library_record` for an open quick-view record

This makes discovery states shareable and preserves browser back/forward behavior.

## Fallback

The WordPress Explorer proxy tries the Python read API first. On a backend error it performs bounded WordPress-local queries for bootstrap, search, record preview, and related records. The user can also force the existing local interface with `?library_legacy=1`.
