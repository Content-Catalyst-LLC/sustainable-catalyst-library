# Knowledge Library v5.63.0 — Public Routing, SEO & Embed Bridge

The independent Library Web application is the canonical public application origin. The default origin is `https://library.sustainablecatalyst.com`, configurable through `SC_LIBRARY_PUBLIC_ORIGIN`.

## Canonical routes
- `/` home
- `/search` search (noindex)
- `/record/{record_id}` record reader (indexable)
- `/discover` capability discovery
- `/system` runtime status (noindex)
- `/account` service-native account (noindex)

WordPress remains a thin public bridge. It may publish launch links, canonical/public metadata and sandboxed embeds, but it is not required in the application request path and owns no research state, credentials, sessions, jobs, artifacts, pipelines, federation state or Platform Core promotion.

Record SEO and embed descriptors are published from the authoritative Library API so web clients and public adapters consume the same canonical route contract.
