# SC-PUB-SEO-01 · PUB-00 baseline, inventory and recovery

**Evidence captured November 22, 2026 (Central Time).** Implementation owner is [Content-Catalyst-LLC/sustainable-catalyst-library](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library). Development branch is [codex/sc-pub-seo-01](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library/tree/codex/sc-pub-seo-01), based on [7a54547](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library/commit/7a54547f723444a3761ec728176b3c738824e265).

**PUB-01 readiness** · The code path and public baseline are verified. The named Build Map could not be located in the checked repository or connected sources. The route inventory also identified 13 registry-to-permalink mismatches and three missing destinations, and the prior directory acceptance remains synthetic. PUB-01 implementation is held pending the authoritative phase scope and a production-equivalent staging inventory. Phase assignments below are provisional.

## Inventory

The shipped registry and 14 embedded field models agree on all 170 panel keys. The hub exposes 170 panel-selection URLs, 14 direct registry map URLs and 32 direct curated publication URLs in initial HTML. The embedded field payload contains 180 curated associations covering 179 distinct publication URLs. Forty-five maps have curated entries in this payload. Six fields have zero curated entries there. This is the published discovery model, which represents editorial selections separately from the full body of articles linked by each map or stored in WordPress.

| Field | Advertised maps | Exact sitemap URLs | Curated associations |
|---|---:|---:|---:|
| Global Governance | 13 | 12 | 4 |
| Sustainable Systems | 5 | 5 | 20 |
| Technology & Systems Intelligence | 6 | 6 | 24 |
| Natural Science | 7 | 7 | 12 |
| Cultural Anthropology | 6 | 6 | 0 |
| Literature & Cultural Memory | 18 | 17 | 0 |
| Mythology | 17 | 14 | 4 |
| Religious Studies | 11 | 7 | 4 |
| Healing Traditions | 12 | 9 | 0 |
| Psychology | 19 | 18 | 44 |
| Philosophy | 27 | 26 | 0 |
| Thinking | 7 | 7 | 28 |
| Meaning | 8 | 6 | 0 |
| Problem Solving | 14 | 14 | 40 |
| **Total** | **170** | **154** | **180** |

The [route-level inventory](PUB-00-inventory.json) records each field, registry key, URL, direct-link and sitemap presence, curated URLs, and the 16 off-sitemap HTTP probes. Of those 16, 13 redirect to HTTP 200, self-canonical URLs in the captured sitemaps. Three return 404 at the registry URL: `/customary-indigenous-legal-orders/`, `/symbolism-style-cultural-meaning/`, and `/creative-form-composition-interpretation/`. The other 154 exact sitemap matches are sitemap evidence, with one representative map checked independently over HTTP. All 179 unique curated URLs appear in the sitemap shards. Arduino Energy Monitoring System appears in two editorial contexts and uses one destination.

## Prioritized findings

| Priority | Finding and evidence | Proposed action |
|---|---|---|
| P1 | All 170 panel selectors are anchors, but their destinations are hub query states, with 14 map and 32 curated destination URLs linked directly in initial HTML. The directory marker is absent. | Reuse the staging-only directory and compare eligible destinations against initial HTML. Keep existing selectors as reversible playback controls and give discovery links descriptive destination labels. [Google crawlable links guidance](https://developers.google.com/search/docs/crawling-indexing/links-crawlable) |
| P1 | Exact WordPress resolution in the existing directory will reject redirects as candidate permalinks. Public probes prove 13 mismatches and three 404s among the off-sitemap cohort. The eligibility code also requires map text and at least one confirmed curated article. | Reconcile aliases and confirmed canonical records with the editorial owner on staging. Preserve working redirects, suppress invalid directory destinations and approve the eligibility rule for genuinely useful map-only series before changing it. |
| P1 | Hub HTML was 1,868,404 bytes uncompressed. The 14 field-model JSON blocks accounted for 408,717 bytes. Initial HTML includes 65 script elements, 36 with a source, 48 images and 14 explicitly eager images. The single anonymous hub request measured 1.16 seconds TTFB. | Profile cold and warm WordPress requests plus a representative mobile browser. Keep useful initial anchors, defer noncritical imagery and spotlight initialization, and attribute shared scripts to owners before removal. Current byte counts and TTFB are diagnostics. Mobile LCP, INP, CLS, CPU and transferred asset bytes still need measurement. |
| P2 | Rank Math emits one hub canonical and index/follow. The description and social copy still describe a blog, with `og:type=article` and an Article node on the hub. The sampled map and article both have self canonicals, distinct descriptions and BlogPosting nodes. | Update the hub through the active SEO provider, use collection semantics for its schema and keep truthful Article or BlogPosting markup on individual editorial works. Preserve shared organization and breadcrumb identities. |
| P2 | Valid and invalid panel query states return 200 and canonicalize to the hub. `/publications/page/2/` also returns 200 and canonicalizes to the hub with the same initial discovery model. Global Governance archive page two returns 200 with a self canonical. | Keep existing selection deep links and exclude state URLs from sitemaps. Establish a deliberate hub pagination policy after checking inbound usage. Preserve self-canonical, crawlable archive pages with distinct lists and handle out-of-range pages explicitly. |
| P2 | The shipped stage hides overflow panel links behind a JS button and uses JS to rotate and control a polite live region. Server markup includes repeated generic “Explore Article Map” links and image-only links beside equivalent text links. CSS, focus appearance, contrast, motion and screen reader behavior need browser confirmation. No duplicate IDs were found in the captured hub document. | Add a visible no-JS navigation path and specific link names, review duplicate tab stops, test keyboard disclosure and focus, and verify reduced-motion and pause behavior across all 14 stages. |
| P3 | Institutional navigation exposes Publications, Research and Library. `/research/` and `/library/` redirect to existing destinations. The hub, Research and Library have different represented purposes. Duplicate route states are confirmed on the hub. Cross-surface full-text duplication has not been established. | Link across the existing canonical destinations, use short contextual summaries and audit collisions by resolved canonical URL and WordPress record ID before proposing consolidation. |

Robots permits the public paths observed here and advertises `/sitemap_index.xml`. The WordPress core sitemap endpoint redirects to that index. The index advertised 11 post shards, one page shard and the Press sitemap. The hub is listed in the page shard. Sitemap presence indicates discoverability. Search Console indexing and selected canonicals need separate evidence. [Robots](https://sustainablecatalyst.com/robots.txt), [sitemap index](https://sustainablecatalyst.com/sitemap_index.xml), [Publications hub](https://sustainablecatalyst.com/publications/)

## Existing-to-target architecture

| Layer | Existing implementation | Target |
|---|---|---|
| Institutional entry | WordPress page `/publications/`, current theme and navigation. Bootstrap in `sustainable-catalyst-library.php` registers Library components. | Same hub route and institutional design. Primary editorial entry from institutional navigation and contextual links. |
| WordPress rendering | `class-sc-library-publications.php` promotes the historical `[sc_publications]` on the canonical page to `[sc_field_spotlights]`. `class-sc-library-field-spotlights.php` also handles the singular shortcode and legacy fallback. Active layout is `templates/field-spotlights.php` and `field-spotlight-stage.php`. Legacy `publications.php` and `field-spotlight-single.php` have separate callers. | Keep shortcode contracts and the stacked component. Render a lightweight semantic directory from verified destinations using `class-sc-library-publications-directory.php` and `templates/publications-directory.php`. Keep legacy embeds working. |
| Editorial data | `includes/data/field-spotlight-fields-v434.php` and `publications-article-map-registry-v431.php` define field order, labels, routes and aliases. Field/panel WP options are `sc_library_field_spotlights_settings_v434` and `sc_library_field_spotlight_panel_content_v4312`. Legacy settings use `sc_library_publications_settings_v433`. Post metadata, permalinks, thumbnails and editorial slots hydrate the cached models. | Keep registry identity and editorial selection in the existing Library model. Resolve canonical identity against WordPress IDs and permalinks, preserve the ability to publish or edit through the existing workflow, and preserve treatment of intentional empty slots. |
| Interaction | `assets/js/sc-library-field-spotlights.js` and companion CSS power playback and disclosure. Legacy `sc-library-publications.js/.css` serve the nonstack shortcode. A separate publications research graph has its own script and styles. | Keep interaction as progressive enhancement. Make meaningful anchors and archive navigation available without JS. Scope model, timers and images by actual need. Preserve graph and legacy registrations unless separately verified. |
| URL and SEO | Registry hero destinations, core permalinks, theme links and Rank Math provide separate layers. The gated directory verifies same-origin public, unprotected exact permalinks. Library’s canonical-route identity class handles Library routes. | Keep one canonical destination per editorial object, existing observed aliases and published paths. Use WordPress and the SEO provider for redirects, canonicals, metadata and sitemap output. Preserve genuine archive pagination. |
| Research, Library and related editorial | Research resolves to `/research-frameworks-and-analytical-models/`. Library resolves to `/knowledge-libraries/`. Publications surfaces series and curated editorial articles. Library already indexes and relates objects. | Publications owns browse-by-field editorial discovery, Research owns methods, projects and research outputs, and Knowledge Library owns cataloging and retrieval. Cross-link existing records and projects by durable ID or canonical permalink. Use related-work cards or excerpts for Media and Press, with full pieces hosted at their established destinations. No separate Publications runtime is needed. |

## Provisional implementation sequence

| Phase | Scope and acceptance |
|---|---|
| PUB-01 | Verify the staged eligible directory and alias inventory on an access-controlled WordPress clone. Compare flag off/on, expand eligible direct anchor coverage and preserve all 14 stages, existing query selection and publication workflows. Hold rollout if the clone cannot reproduce theme, Rank Math, redirects or route authority. |
| PUB-02 | Reconcile field, map and article navigation, including parent/child and related links. Preserve existing destinations and working redirects. Require verified 200 URLs, descriptive labels and representative empty/private cases. |
| PUB-03 | Align Rank Math title, description, social metadata, canonical and structured data per page type. Require a single canonical and coherent graph plus valid state URL behavior. |
| PUB-04 | Validate sitemap completeness, robots, duplicate routes, category pagination and out-of-range behavior. Compare exact canonical destination sets and inspect representative URLs in Search Console where authorized. |
| PUB-05 | Wire Research, Knowledge Library, Media and Press contextual links through current components. Review records with duplicate titles, shared IDs or cross-surface excerpts. |
| PUB-06 | Verify and improve mobile and accessibility performance. Test JS-disabled navigation, keyboard operation, pause and reduced motion, screen readers, contrast, browser waterfalls and cold/warm WordPress timing. |
| PUB-07 | Run production-equivalent acceptance, publish review evidence to the development branch, rehearse rollback and prepare a separate release decision. |

## Recovery and test evidence

- Branch recovery point is the pinned base SHA. This phase adds read-only audit tooling and documentation. It requires no database migration, production option edit, theme update, deploy or merge.
- In an authorized staging workspace, record provenance and snapshot the clone’s page content, relevant Library options and SEO/redirect configuration before changes. Rehearse disabling `SC_LIBRARY_PUBLICATIONS_DIRECTORY`, purging the staging page cache and comparing baseline HTML and routing. Revert any future staging changes separately from the baseline branch.
- The existing [WordPress acceptance procedure](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library/blob/7a54547f723444a3761ec728176b3c738824e265/docs/acceptance/SC-SEO-03-1-PUBLICATIONS-DIRECTORY.md) includes a SELECT-only, prebootstrap-guarded export and synthetic captures. Its prior synthetic fixture classified 28 populated, 14 empty, 14 unavailable and 114 unresolved. A later synthetic warm directory benchmark reported 132 ms median against a 41.7 ms flag-off median. Those measurements are historical and fixture-specific. The current task did not run the production-equivalent exporter, browser mobile profiling, or the live WordPress directory fixture.
- A fresh end-to-end run of the capture and analyzer reproduced all 170 map rows and their curated associations, including link and sitemap coverage. Two new audit tests pass. Two existing PHP fixtures pass selected-slot persistence and collapsed-model recovery. Nine scoped PHP files lint cleanly, the Field Spotlight runtime passes Node syntax validation and the capture script passes Bash syntax validation. The broad legacy Python selection could not collect the research-graph suite because `RESEARCH_LIBRARY_PAGE_v4.3.37.html` is absent at the baseline. With that suite omitted, the unchanged baseline tests recorded 71 passes and 75 failures, including historical version and markup assertions. Treat this as baseline evidence, with focused behavioral acceptance required for PUB-01.

Run from the Library checkout with Bash, curl, Python 3 and PHP:

```sh
bash tools/capture-publications-source.sh https://sustainablecatalyst.com /tmp/pub00-capture
python3 tools/audit-publications-source.py /tmp/pub00-capture --origin https://sustainablecatalyst.com > /tmp/pub00-inventory.json
python3 tests/test_publications_source_audit.py
php tests/test_field_spotlight_slot_persistence_v4310.php
php tests/test_publications_integrity_recovery_v43181.php
```

The same capture accepts a staging origin. For the exception cohort, select inventory rows with `in_captured_sitemaps=false`, issue anonymous GETs with redirect tracking, and record status, final URL and final canonical. Preserve the raw responses locally for comparisons and keep any staging exports private.
