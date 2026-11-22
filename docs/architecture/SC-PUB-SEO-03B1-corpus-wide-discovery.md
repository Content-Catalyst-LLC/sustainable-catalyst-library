# SC-PUB-SEO-03B1 — Corpus-Wide Intent Filtering & Deployment Safety

**Owner:** Sustainable Catalyst Library · **Status:** development-only · **Base:** `sc-pub-seo-03a-publication-manifest` (stacked on draft PR #16, itself based on #15)

## What this increment delivers

The production site currently presents **14 fields and 170 Article Maps** as a field-first browsing surface. Existing server-rendered article and field links must remain intact. The intended interest-first experience should surface relevant public publications and verified Article Map links without creating duplicate indexable URL states.

The 03B1 Python read path removes the main limitation in 03A: selecting **only the most recently updated 200 records before filtering**. It now applies an interest/topic/text predicate across **all eligible catalog rows before LIMIT**, yielding a bounded candidate set for deterministic application ranking. Explicit text search does not fall back to unrelated pages. Interest-only browsing may backfill from verified recent records if too few matches exist. All SQL values are parameters; the filtered query repeats privacy and editorial gates. No migrations or content mutations.

**Precision:** this is **full-corpus filtering, not exhaustive global relevance ranking**. The SQL result set remains capped at 200 candidate rows and ordered by update time before Python rescoring. Particularly broad topics could still omit older relevant matches. Future staging performance evidence should determine whether an indexed relevance-first query or cursor-based retrieval is required.

The Python endpoint now returns HTTP 404 on any production runtime even if `SC_PUBLICATIONS_DISCOVERY_API_ENABLED=1` is mistakenly configured. It operates only with explicit opt-in and `SC_LIBRARY_ENVIRONMENT` equal to `staging`, `local`, `development`, or `test`.

## Public visibility and authority

- WordPress remains source of editorial publication status, canonical permalink, map selection and password protection.
- Signed WordPress indexing packets carry explicitly approved discovery metadata only when staging manifest sync is enabled.
- Python is read-only and returns **candidates**, never a release-approved public directory or metadata owners.
- WordPress preview rechecks each candidate's current public post, canonical permalink and associated map URL before rendering a link.
- No orphan Article Maps are moved to Private. No automatic article creation, publication, redirects or canonical changes.
- The public site remains on the existing UI; no production flags have been changed.

## Acceptance matrix

| Gate | Requirement | Status |
| --- | --- | --- |
| A | Field, text and interest predicates apply to all eligible rows before bounded LIMIT | Implemented; unit test added |
| B | SQL inputs parameterized, no dynamic SQL identifiers from visitors | Implemented; unit test added |
| C | Only public, published, manifest-approved, unprotected WordPress records eligible | Retained; synthetic tests |
| D | Accidental production API flag cannot enable candidate endpoint | Runtime guard implemented; stage request test still needed |
| E | Anonymous HTML renders verified canonical article/map links and field browsing without JavaScript | Code prepared from 03A; staging browser proof missing |
| F | Revocations, password changes, permalink edits, stale cache/row handling and durable tombstones | **Blocked / not implemented** |
| G | Real staging SQL EXPLAIN, latency and concurrency tests, warm/cold WP TTFB/Core Web Vitals | **Not yet tested** |
| H | Canonical, redirect and 404 inventory reconciled for all 170 maps | **Tracked separately in issue #13** |
| I | Production-equivalent staging, rollback drill, identity and release approval | **Not complete** |

## Staging validation steps for the next developer

1. Use a non-production PostgreSQL copy with synthetic or approved public-only WordPress fixtures. Confirm no personal/private data in test output.
2. Verify `SC_LIBRARY_ENVIRONMENT=staging` and enable `SC_PUBLICATIONS_DISCOVERY_API_ENABLED=1` **only in staging**. In production-like environment it should remain 404 regardless of flag.
3. Run `python -m unittest discover -s tests -p 'test_publication_discovery_service.py' -v` and `php -d display_errors=stderr tests/test_publications_editorial_manifest.php` plus Library CI.
4. For interests `carbon removal`, `international law`, and a topic present only in older publications, capture relevance matches and demonstrate no recency-first omission from a synthetic corpus larger than 200 records.
5. Record `EXPLAIN (ANALYZE, BUFFERS)` **in staging** for query, field-only and interest-only. Inspect OR predicates over topics/tags JSONB; propose an index only with measured evidence.
6. Confirm WordPress receives an exact, public canonical article/map pair. Simulate post withdrawal, password protection, canonical change and API timeout. Do **not** enable public endpoint while stale Python candidates remain accessible following any such change.
7. Anonymous GET checks: intended canonical on `/publications/` and search parameters; no query-state sitemap entries; no duplicate schema; no private post leaks; keyboard/no-JS/mobile navigation. Capture 14-field parity and existing Spotlight behavior with flags off.
8. Compare cold/warm page timing to the PUB-00 baseline; avoid doubling a page already about 1.9 MB of uncompressed HTML. Reduce payload only without losing useful crawlable links.
9. Review the three broken registry routes and 13 alias redirects in issue #13 before presenting each affected map as a recommended link. Do not guess their destinations.

## Cross-cutting GEO / AEO / LLM discoverability

**[SC-PUB-GEO-01 — AI Search Discovery & Citation Readiness](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library/issues/18)** is a formal workstream alongside traditional SEO, not a replacement for it. It is not a dependency for the safe repair of existing dead links.

- **GEO-00 (parallel with PUB-01):** inspect crawlable/canonical public URLs, title/description/H1, public-only JSON-LD, sitemaps, robots/CDN permissions for search crawlers, and no accidental leakage of private records. Keep AI-search crawler policies distinct from model-training consent.
- **GEO-01/02 (during navigation/template work):** author-reviewed, source-grounded answers/findings, explicit methods/limitations, publication authorship/date and Library citation provenance; validate visible content and appropriate Article/BreadcrumbList metadata under existing Rank Math authority. Never invent scientific evidence or auto-publish material to target AI answers.
- **GEO-03/04 (after public data synchronization):** Python public-only citation/relationship audits, repeatable question-intent tests, and measurements from Google Search Console generative AI reports, Bing AI Performance, and attributed referral analytics when access is available.
- Avoid assuming `llms.txt`, fake third-party mentions, keyword stuffing, or LLM-specific markup provide a ranking benefit. Google's July 2026 Search guidance specifically prioritizes ordinary SEO, original useful content, and good site experience.
- We may evaluate AirOps-style GEO metrics (mentions vs actual citations, sources, prompt/topic clusters and measured change) without adopting AirOps as a site dependency. No unverified citation/ranking claims.

## Release ordering and fastest SEO path

1. **PUB-01 navigation integrity:** prioritize broken links and canonical source links, retaining valid map content and current working redirects.
2. **PUB-03B2 revocation and cache consistency:** reliable status/password/permalink invalidation, signed retry/reconciliation with denial upon stale state, full WordPress/Python acceptance. Required before any public discovery API.
3. **PUB-05 SEO/navigation staging:** public interest-first header, regular server-rendered links, meta description/canonical parity with Rank Math, structured data and sitemap checks, accessibility and mobile performance.
4. **Controlled release:** backend + adapter staging soak, flags initially off, backup/rollback, explicit deploy review. Monitor 404 rate and performance first, then organic search metrics.

SEO success cannot be guaranteed or inferred from green CI. Create a **pre-release Google Search Console baseline** for Publications/article-map URLs: indexed URLs, impressions, clicks, CTR and query clusters; remeasure at 7, 28 and 56 days after deployment, allowing for indexing delays. Only connect real Search Console data when access is provided. Avoid attributing changes to navigation alone without controlling for new content and seasonality.

**Do not merge or deploy this branch alone.** It is a technical step toward the complete safe release, not a production go decision.
