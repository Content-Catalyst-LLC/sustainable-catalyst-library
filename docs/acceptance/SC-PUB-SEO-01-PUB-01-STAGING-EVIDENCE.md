# PUB-01 staging verification

November 22, 2026 · Library development baseline `13b5644`

The existing Publications directory passed fresh acceptance in an isolated, loopback-only WordPress 6.8.3 fixture with PHP 8.4.13, SQLite and a minimal fixture theme. The fixture used synthetic records and loaded the Publications and Field Spotlights classes from the current development checkout.

| Check | Observed result |
|---|---|
| Eligibility | 170 rows classified. 28 populated, 14 empty, 14 unavailable and 114 unresolved. |
| Initial HTML | Flag off emitted zero directory links. Flag on emitted 28 unique map links and 28 unique curated article links. |
| Spotlight and publishing surface | All 14 stages matched between flag states. Parameter selection and the sample map’s HTML and article link matched. The playback control changed the active panel in the test DOM. |
| HTTP and metadata | Hub and selected-panel sample returned 200 in both modes. Canonical, robots metadata and redirect behavior matched the flag-off baseline. |
| Accessibility | Scoped axe WCAG A and AA DOM checks passed. The fixture test excludes color contrast. |
| Rollback | Disabling the directory after a flag-on render restored byte-identical flag-off shortcode HTML in WordPress CLI. |

All six cases in `tests/test_publications_directory_stage.cjs` passed against fresh responses. The separate archived fixture captures also passed all six cases. Reproduction uses the existing seed and capture scripts in `tests/fixtures/` and the [directory acceptance procedure](https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library/blob/7a54547f723444a3761ec728176b3c738824e265/docs/acceptance/SC-SEO-03-1-PUBLICATIONS-DIRECTORY.md). The fresh run used a SQLite drop-in after the local MySQL 9.4 initialization crashed. SQLite evidence covers fixture behavior. The existing MySQL benchmark and the production-equivalent acceptance gate still apply.

## Route review and remaining inputs

The PUB-00 inventory gives the 13 observed redirect targets and three 404s. For each redirect candidate, confirm the intended map and public WordPress record ID on the clone, then use its verified current permalink as the proposed discovery destination while preserving the old redirect. For the three 404s, confirm whether the editorial record was moved, withdrawn or never published before assigning a destination. Keep those routes out of new direct navigation until verified. Exact sitemap membership alone does not establish eligibility for the other 154 registry URLs.

PUB-01 implementation remains gated on the authoritative Build Map and an approved, production-equivalent staging source with the Publications page, registry and field/slot options, published records and redirect/Rank Math settings. The fixture exercised the existing feature flag. Production-equivalent HTML, mobile performance and site-specific SEO behavior remain open. No runtime, registry, staging service or production changes were made.
