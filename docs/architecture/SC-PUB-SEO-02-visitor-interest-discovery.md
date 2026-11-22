# SC-PUB-SEO-02 — Visitor-Interest Publications Discovery (Python-first)

**Owner:** Sustainable Catalyst Library  
**Status:** development prototype — feature disabled by default; production release NOT authorized by this branch alone  
**Starting baseline:** Library main after 7.0.1, PUB-00 checkpoint `13b5644` remains on separate branch `codex/sc-pub-seo-01`  
**Related cleanup issue:** #13 (dead links / orphan review; no WordPress post status changes).

## Product direction

Publications should help each reader quickly discover editorial content relevant to **their stated interests**. The entry experience should prioritize a visible search box, a field selector, and optional interest terms. It should also show a complete, ordinary HTML list of the 14 field archives, irrespective of Python availability. Topic relevance and publication relationships should be computed by the Library's existing Python catalog and research infrastructure, rather than creating a second WordPress search engine.

The public, indexable default remains `/publications/` and canonical field/article/Article Map pages. Search/filter state URLs are **not** intended as new indexable destinations. Rank Math remains site editorial SEO metadata provider. A visitor's interests are expressed in request parameters only. No visitor identity or preference profile is stored by these new components; ordinary server or proxy access logs still require their own retention/privacy review.

## What this branch actually implements

- `library-backend/app/publication_discovery_service.py`: read-only bounded PostgreSQL catalog scan, explicit editorial/visibility metadata gates, deterministic topic/title/tag/field relevance, canonical URL validation, deduplication and bounded candidate response. Does not create or modify content, persist searches, invoke an LLM, generate SEO text or assert scientific quality.
- `GET /api/library/v1/publications/discovery`: optional `q`, `field`, `topic`, `interests`, `limit`. Returns **404** unless `SC_PUBLICATIONS_DISCOVERY_API_ENABLED=1`. Experimental addition; existing API v1 unchanged.
- `templates/publications-interest-discovery.php`: **staging/local only**, behind `SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW`. Provides accessible search/field/interest form, server-side backend call with a 2-second timeout, and WordPress re-verification of exact post identity, public status, password state and permalink before output. If Python is unavailable, only the standard field archive navigation remains.
- `templates/field-spotlights.php`: optional inclusion of the new preview. The existing 14-field and 170-map stack is unchanged.
- Scoped CSS and tests for privacy filtering, deterministic ranking, URL handling and request boundaries.

## Important current limitation: data handshake is NOT complete

The existing WordPress-to-Python packet in `class-sc-library-python-backend.php` currently sends `wordpress_post_type` and `wordpress_status` metadata. It **does not** yet send the required:
- `publication_discovery_approved` boolean
- `wordpress_public_verified` boolean
- `wordpress_password_protected` boolean
- `publication_field` stable field key

The service intentionally fails closed when these fields are absent. **Do not enable the API on a public origin until the approved, atomic publication manifest and removal/visibility invalidation protocol are implemented and tested.** Without this work, production results are expected to be empty; inventing default approvals to populate results would weaken privacy.

Follow-up contract requirements:
1. Derive eligibility from the existing Publications editorial registry, actual published post ID, current permalink and privacy state; not from Google, sitemap presence, raw category names or inferred semantic proximity alone.
2. Ingest signed, revisioned updates, including tombstones for unpublished, password-protected, removed and changed canonical records; deny output on source desynchronization.
3. Associate Article Map keys and field IDs with published WordPress objects; record relationship provenance and confidence. Treat missing curated Spotlight slots as *unknown editorial relation*, not as orphan proof.
4. Reuse Library search/graph and semantic retrieval when backend evidence and tests support it; this pilot uses deterministic lexical tags/title/field matching. A 200-row most-recent scan is a limited candidate pool, **not** complete topic discovery across the entire corpus. Improve bounded pagination/indexed retrieval before public launch.
5. Reverify each surfaced result against the current WordPress record, even when metadata claims approval. Do not expose raw private catalogs, drafts, preview URLs, protected posts, or admin diagnostics.
6. Handle stale caches, synchronization failure and backend timeout gracefully; default to verified server-rendered field links.

## SEO and accessibility acceptance

- A simple anonymous GET of `/publications/` delivers the existing editorial structure and static, crawlable field links without JavaScript or Python.
- A visit selecting a field or searching a topic can return only verified public canonical URLs, with meaningful labels and stable ranking. Forms use visible labels and keyboard focus.
- Every rendered article link is a conventional `<a href>`. No client JavaScript is required to traverse it.
- Query-parameter states have one intentional hub canonical; do not add param URLs to sitemaps or emit duplicate CollectionPage/Article graphs.
- Rank Math owns page title, description, OpenGraph and Article/BlogPosting metadata. Backend relevance scores do not determine `rel=canonical`, `noindex` or sitemap membership.
- Dead or redirected registry links must be fixed from confirmed canonical evidence; do not automatically change existing 13 redirects or 3 404s. Keep issue #13 separate.
- Mobile and no-JS behavior, accessibilty, and cold/warm page timing must be measured with production-equivalent staging using the real theme and plugins. Two seconds of backend timeout is a ceiling, not a performance acceptance threshold.

## Safe staging smoke-test

1. Deploy branch to isolated staging, with a matching WordPress clone and isolated Python API; never activate flags in production.
2. On **staging only**, set `SC_PUBLICATIONS_DISCOVERY_API_ENABLED=1` and `SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW=true`.
3. Start with synthetic approved records and synthetic withdrawn/private/password-protected records. Verify the former appear and the latter cannot appear, including after cache warming and mutation.
4. Verify field navigation fallback when API returns 404/500, times out or metadata is missing.
5. Capture initial HTML with and without JavaScript and compare canonical/meta output, titles, query states, field anchors and links.
6. Verify WordPress post publishing, status transitions, permalink edits and cache invalidation; run PHP lint, Python unit tests, benchmark and accessibility tests.
7. Keep flags off by default; open a release-gate review and deployment plan only after source synchronization, clone parity, results and rollback are certified.

## Explicit non-goals for this phase

No live production activation; no automatic article publishing, paraphrasing, or SEO copy generation; no personalized tracking/profiles; no post status changes; no new database migrations; no separate Publications application; no claim that interest-driven rankings equal verified truth; no schema changes to the existing Library API contract.

## Next build after prototype

**SC-PUB-SEO-03 — Publication Manifest & Revalidation Bridge**

Build a source-of-truth manifest from existing WordPress editorial records, with signed reconciliation to the Python catalog, stable Article Map/field relations, tombstones, replay/rollback and public rendering tests. Add a paginated, indexed retrieval path and canonical cleanup before the first live navigation release. This is the production release dependency.
