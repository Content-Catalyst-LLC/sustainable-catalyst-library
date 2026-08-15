# Library API, Embeds & Interoperability — v4.9.0

## Purpose

v4.9.0 adds a stable public integration facade over records that are already public in Sustainable Catalyst Library. It does not create a second public-record database and it does not convert private research into API content.

## Canonical authorities reused

- WordPress publish status and canonical record URLs remain authoritative for public records.
- v3.9 Public API / Export / Federation remains the token, export, peer, quarantine, and federation authority.
- v4.8 Global Research Federation remains authoritative for explicitly published federation manifests.
- `/knowledge-libraries/` remains the canonical public Library route.

## Public object profile

`sc-library-public-object/1.0` returns only normalized fields needed for interoperability: stable object identity, public type, title, bounded summary, canonical URL, publish/update dates, language, provenance, and API/manifest links. Arbitrary post meta is never copied into the public payload.

Supported first-party profiles include Foundation Documents, Publications, Knowledge Pathways, public Research Sources, Named Entities, and Concepts. The profile registry is filterable so later public object types can be added without changing the envelope schema.

## Embeds

Local WordPress embed:

`[sc_library_embed type="publication" id="123"]`

External embed loader uses the same normalized GET endpoint. External origins must be explicitly included in `sc_library_api_embed_allowed_origins` (or the corresponding filter). The loader uses `credentials: omit` and receives no authenticated governance capability.

## CORS boundary

CORS is applied only to the v4.9 public GET facade and only when the request origin is on the explicit allowlist. No wildcard write CORS is introduced. `Access-Control-Allow-Credentials` remains false.

## Privacy boundary

The facade does not expose My Library, Research Projects, Reading Notebook bodies, Evidence Matrix bodies, Research Room membership, Team Library membership, private federation governance, credentials, tokens, source binaries, or Workspace state. It performs no cross-site writes, automatic publication, federation acceptance, evidence promotion, or Workspace write.
