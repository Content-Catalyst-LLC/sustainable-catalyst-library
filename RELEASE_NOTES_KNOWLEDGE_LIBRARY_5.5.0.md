# Sustainable Catalyst Library v5.5.0
## Python Research Intelligence Backend & Knowledge Evolution Foundation

v5.5.0 adds the first dedicated Python backend to Sustainable Catalyst Library without replacing WordPress as the editorial or identity authority.

The new `library-backend` service is FastAPI + PostgreSQL and supplies weighted full-text search, trigram title matching, record chunks, provenance, sources, graph relationships, related-record discovery, facets, immutable revision snapshots, and knowledge-evolution timelines. Writes are authenticated with a bearer credential plus timestamped HMAC request signatures.

WordPress receives a contained backend bridge with a configuration/status page, public backend health/search proxy routes, automatic post-save indexing, publication-state removal, deletion propagation, and an administrator bulk reindex action. The API key remains server-side.

The release is intentionally additive. Existing Library pages, shortcodes, curated spaces, federation, account workspaces, publication routing, and the legacy workspace/document/media helper are not removed.
