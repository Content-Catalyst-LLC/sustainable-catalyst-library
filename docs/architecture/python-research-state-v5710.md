# Knowledge Library v5.71.0 — Python Research Projects, Collections & Saved Research State

## Authority

Python/FastAPI is the authoritative domain service for identity-owned research projects, project references, source bundles, saved searches, passive watchlists, research queue items, and personal research collections. PostgreSQL is the durable state authority. WordPress/PHP remains a presentation and API-client compatibility layer only.

## Ownership model

Private research state is owned by Sustainable Catalyst Library identities, not WordPress numeric user IDs. WordPress may bridge an authenticated account to a Library identity, but the bridge is not authoritative.

## Reference semantics

Projects, bundles, queue items, and collections store references and metadata rather than copying source content or binary attachments. Source bundles remain references-only. Missing references may remain represented rather than being silently discarded.

## Saved-state semantics

Saved searches preserve a query, scope, filters, and user metadata. Watchlists in this release are passive revisit lists: they do not create background monitoring, alerts, or notifications. Research queue priority is organizational metadata and does not imply evidence quality or research importance.

## Legacy PHP

The legacy PHP project, saved-search/watchlist/queue, and personal/curated collection classes remain present as compatibility and presentation surfaces and stay classified as retire-candidates. No new PHP research-state authority is introduced in v5.71.0.
