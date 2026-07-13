# Library v1.18.0 — Public API, Webhooks, and Developer Documentation

This release adds production-oriented access to the Sustainable Catalyst Library as shared platform infrastructure.

## Included

- Versioned public REST namespace
- Public record, relationship, graph, roadmap, schema, status, and OpenAPI routes
- Scoped API keys stored as keyed hashes
- Per-key and public rate limits
- Exact-origin opt-in CORS
- Signed HTTPS webhooks with encrypted secrets
- Bounded webhook retries and delivery logs
- Event bridges for records, plans, documentation, graph rebuilds, workspaces, reviews, documents, and media clips
- Native admin Developer API workspace
- Public developer portal shortcode
- Static OpenAPI, JSON Schema, JavaScript, Python, and PHP verification examples
- PostgreSQL-ready developer metadata without secrets
- Portable export schema `sc-library-portable-export/1.8`

## Boundaries

The release does not make private workspace, editorial, planning, credential, or provider data public. API keys cannot publish or approve content. The protected reindex endpoint is explicit, scoped, and intended for trusted service automation only.

## Public multimedia metadata

The versioned API includes paginated public evidence-reel routes. Private assets, clips, jobs, rights notes, and internal processing metadata remain excluded.
