# Sustainable Catalyst Library v4.0.2

## Research Library Fatal Recovery

The supplied page capture shows that WordPress renders the site header and
breadcrumb, then terminates while processing the Research Library content. That
locates the failure in the Library shortcode/runtime path rather than in the
theme or general page layout.

v4.0.2 adds two containment layers:

1. The 25 optional production extensions are loaded and constructed through a
   guarded bootstrap. A failure is recorded and isolated instead of terminating
   the request.
2. `[sc_library]` is executed inside a `Throwable` recovery boundary. If its
   interactive renderer fails, WordPress immediately serves a searchable,
   paginated, server-rendered Research Library using core post and taxonomy
   queries.

The recovery catalog includes published articles, pages, Foundation Documents,
and PDF Documents. The exact recovered error is stored in
`sc_library_last_public_runtime_error` and displayed to administrators.

This release does not delete or rewrite Library content, taxonomies, PDFs,
Foundation Documents, or search-index records.
