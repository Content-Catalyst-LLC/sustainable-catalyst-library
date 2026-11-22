# SC-PUB-SEO-03A — Editorial Publication Manifest & Article Map Revalidation

**Owner:** Sustainable Catalyst Library  
**Status:** development-only, stacked on SC-PUB-SEO-02 PR #15  
**This implementation is not a production deployment.**

## Objective

Support visitor-interest discovery without granting the Python catalog authority to decide what WordPress has published. Derive editorial relationship approvals from existing explicit WordPress Publication ↔ Research Graph Article Map selections, check current publication state, then add a small amount of provenance metadata to the **existing signed WordPress-to-Python indexing packet**.

No database migration and no new independent Publications service.

## Current feature gates

| Gate | Default | Allowed stage | Purpose |
| --- | --- | --- | --- |
| `SC_LIBRARY_PUBLICATIONS_MANIFEST_SYNC` | Undefined = off | WordPress local/staging only | Attach editorial manifest metadata to signed index packets |
| `SC_PUBLICATIONS_DISCOVERY_API_ENABLED` | Undefined = off | Isolated Python staging only | Expose bounded candidate API |
| `SC_LIBRARY_PUBLICATIONS_INTEREST_PREVIEW` | Undefined = off | WordPress local/staging only | Render search/interest UX and verified links |

There is **no production flag path** for the new WordPress code. Do not bypass the environment checks.

## Data contract

Packet metadata added when the manifest flag is explicitly enabled:

```json
{
  "publication_manifest_schema": "sc-publications-editorial-manifest/0.1",
  "publication_discovery_approved": true,
  "wordpress_public_verified": true,
  "wordpress_password_protected": false,
  "publication_field": "global-governance",
  "publication_map_key": "international-law"
}
```

These values are not defaults. The manifest returns approval false unless all criteria pass:

1. The source is a published, publicly viewable, unprotected WordPress **post**.
2. It has an existing editor-selected `_sc_publication_article_map_key_v4337` that resolves into Field Spotlights' Article Map registry.
3. The map field is recognized in field definitions.
4. Neither the mapped field nor the panel is editorially hidden in Field Spotlights settings.
5. The post has an exact same-origin WordPress permalink, without search or preview parameters.
6. The map's registry route resolves to a published, unprotected, publicly viewable WordPress post object with an exact permalink equal to its registry URL. A redirect, missing route, or 404 **does not pass** this check.

For unsupported post types, missing class dependencies, absent map selection, hidden areas, mismatched routes and protection states, the manifest fails closed and returns `publication_discovery_approved:false`.

The manifest is only run in local/staging, and the existing indexing packet is **unchanged** with the flag off.

### Python candidate output

The bounded discovery response now includes `article_map_key` only as a sanitized **identity**, never as a trusted URL. On the staging WordPress page, the map is independently resolved through the current map registry and WordPress post/permalink checks before the page renders a conventional HTML link beside the recommended publication.

## How to validate

A GitHub Actions job runs:

```sh
php -d display_errors=stderr tests/test_publications_editorial_manifest.php
python -m unittest discover -s tests -p 'test_publication_discovery_service.py' -v
```

The manifest fixture checks successful approval, production rejection, missing and unknown editor map assignments, unpublished/private/protected/foreign-origin records, editorial hidden fields and maps, a noncanonical map registry URL, and missing targets.

Complete staging acceptance requires an **isolated** WordPress/Python deployment with synthetic published, private and password-protected records. Verify the resulting signed packet, saved Library metadata types, API output, HTML links and no-JS navigation. For a real WordPress post, test editorial assignment changed, map unpublished and map URL redirected before considering any release.

## Critical blockers: do not release to production yet

1. **Revocation/tombstones.** Existing WordPress sync calls a signed DELETE on `transition_post_status` for unpublished posts, but needs durable retry on deletion failures, password changes, canonical edits, stale catalog entries and restore/republication. These must not leak a prior approved candidate through the public API. Implement source revision checks, idempotent replay and denied reads when sync is stale.
2. **Coverage and completeness.** This phase approves only editorial **posts with mapped Article Map keys**. Map pages and public articles without that explicit relationship are deliberately omitted. A 200-record recent pool is not full-corpus interest discovery. Implement complete indexed/paginated retrieval and a map-page manifest before claiming broad discovery.
3. **Site-visible state.** The PHP helper checks WordPress records and permalink equality; only a production-equivalent anonymous HTTP crawl can confirm effective routing and Rank Math canonical selection, especially for 13 existing redirect aliases and 3 broken registry URLs documented in issue #13.
4. **Page performance.** Add a limited response cache with invalidation and compare flag-off/on cold/warm TTFB and full body time on production-equivalent staging. The 2-second request timeout is not itself an acceptable target.
5. **Editorial privacy.** No admin review data, private records, draft titles or protected snippets should escape to the client, cache, sitemap or API. Test permission changes and withdrawals under concurrency.
6. **Release discipline.** Merge and release only after PR #15 and this stacked work are reviewed and versioned, staging is parity-verified, backups/rollback exist, and a separate production approval is issued.

## Scope decisions

- The user has **not** authorized automatic moves of orphaned Article Maps to Private. Issue #13 covers dead-link repair and orphan review without status changes.
- WordPress owns public editorial URLs, publication statuses and site-facing SEO metadata; Library Python owns candidate computation and related research/catalog services.
- The new interest discovery should remain available without tracking reader identity; web server log handling is separate.
- Article Map associations are editor-declared, not LLM-inferred. Discovery ranking does not constitute evidence quality or scientific validity.

## Next stage

**SC-PUB-SEO-03B** — durable signed revocation/manifest reconciliation and complete indexed retrieval with staging revalidation. Only after this is green should the WordPress interest navigation be proposed for controlled production activation.
