# SC-SEO-03.1 Publications directory staging acceptance

The directory is an additive branch in the actual stacked Publications shortcode and template. Set `SC_LIBRARY_PUBLICATIONS_DIRECTORY` true in local or staging only. Disable the flag to remove the directory. The existing spotlight markup, JS, routes, canonical logic, and publishing state are unchanged.

Directory entries require exact same-origin WordPress permalinks backed by published, publicly viewable, non-password-protected records. A map additionally requires textual content and at least one confirmed curated article. A missing record or mismatched route is unresolved. A confirmed map without text or curated slots is empty. A known nonpublic or password-protected record is unavailable. Only populated entries with meaningful link labels render. Duplicate hrefs within the directory are collapsed in editorial order. Registry candidates, historical Google examples and private fields cannot independently create a directory link.

## Reproduce on an isolated fixture

1. Start a fresh local WordPress 6.8.3 installation with a disposable MySQL 8 database, site name `Synthetic staging fixture`, and home/site URLs `http://127.0.0.1:18765`. Enable `/%postname%/` permalinks. Use a minimal theme with `wp_head()`, a `main` wrapping `the_content()`, and `wp_footer()`.
2. In the fixture alone, add a must-use loader that defines `WP_ENVIRONMENT_TYPE` as `staging`, sets `SC_LIBRARY_PUBLICATIONS_DIRECTORY` from `getenv('SEO031_DIRECTORY') === '1'`, defines `SC_LIBRARY_DIR` and `SC_LIBRARY_URL` for this checkout, loads the Publications and Field Spotlights classes, and calls `(new SC_Library_Field_Spotlights())->register_hooks()` on `init`. Serve the fixture only on loopback. The PHP router serves existing assets directly and includes `wp/index.php` for pretty routes.
3. Run these from the Library checkout. The seed validates the disposable fixture site identity before writing synthetic records.

```bash
wp --path=/private/stage/wp eval-file tests/fixtures/seed_publications_directory.php
bash tests/fixtures/capture_publications_directory.sh /private/stage
SEO031_STAGE=/private/stage NODE_PATH=/private/node_modules node --test tests/test_publications_directory_stage.cjs
```

The Node checks require local jsdom and axe-core. The capture script writes original server responses with the flag off and on, and separately records the 170 staging classifications. Use the SEO Intelligence `--publication-baseline` option on the paired hub captures with the loopback property. Keep that comparison separate from the SC-SEO-03 production capture, which used a different article cohort.

Staging classified 28 populated, 14 empty, 14 unavailable and 114 unresolved entries. The directory emitted 28 unique map links and 28 unique article links. Total initial HTML coverage increased from 14 to 28 for each class. The fixture verified independent field stacks, playback, parameter selection, canonical and HTTP behavior, and scoped accessibility rules. Production must remain gated on an authorized publication inventory, route/metadata acceptance with the real theme and plugins, performance review, and separate release approval.

## SC-SEO-03.2 production-equivalent acceptance

Use an isolated, access-controlled clone with outbound traffic blocked. Record WordPress, PHP, database, active Astra theme and child-theme revisions, active and must-use plugins, Library and UI builds, SEO and redirect plugins, permalink rules, and page/object cache configuration. Compare those identifiers with production before calling the clone equivalent. Import the approved Publications page and template assignments, registry and field/slot options, Article Map and curated article records, and route/SEO metadata. Exclude production credentials, unrelated private records, live webhooks and mail delivery. Preserve routes on the staging origin and verify why each intentional difference exists.

The inventory tool is for owner review. Use an approved read replica or offline copy, a database principal restricted to SELECT, and a private file. The database grant must be in effect **before** WordPress and plugin bootstrap. The script adds a read-only transaction with rollback, resolves exact same-origin permalinks, and emits only route, status, protection and coarse content/curation facts. It performs no HTTP fetches. Treat unresolved records as unknown. Verify the full directory against actual anonymous staging responses separately.

```bash
umask 077
SC_SEO_INVENTORY_APPROVED=1 wp --path=/private/approved-wp \
  eval-file tools/export-publication-inventory.php > /private/publications-inventory.json
bash tests/fixtures/benchmark_publications_directory.sh /private/disposable-fixture
```

The benchmark invalidates Library model transients before each cold sample. Warm samples reuse the model transients. Compare on/off medians for both TTFB and full body time with the same runtime and representative load. Verify visible focus and contrast using the real theme. Confirm flag-off returns the baseline response and rehearse the staging page-cache purge. The synthetic fixture warm full-body median rose from 28.8 ms to 977.4 ms. This is a release blocker until the per-request lookup cost is addressed and the representative benchmark passes. Production eligibility and production-equivalent clone acceptance remain blocked.

## SC-SEO-03.3 directory and exporter hardening

The directory batches only recognized flat core WordPress routes without custom query hooks. Other routes retain core resolution. Eligibility is request-scoped, and post publication and permalink checks run against the resolved object. Run the regression budget and mutation checks on the disposable fixture:

```bash
wp --path=/private/disposable-fixture/wp eval-file tests/fixtures/check_publications_directory_performance.php
```

In the benchmark, flag-on warm median full response improved from 686.0 ms to 132.0 ms, p95 from 1,280.9 ms to 163.1 ms, and SQL queries from 575 to 17. The after flag-off warm median was 41.7 ms. Keep production disabled until a production-equivalent run resolves this remaining gap and the 170-map eligibility evidence is available.

For an approved inventory run, use a dedicated `wp-config.php` and define the credentials of a SELECT-only account. Before the config loads `wp-settings.php`, require `tools/publication-inventory-prebootstrap.php`. It checks grants on that account, rejects elevated grants, and records the identity for validation by the exporter. The approved output stays private. A WordPress bootstrap may attempt cache writes. SELECT-only grants deny those writes, as observed on the disposable fixture. Use an approved isolated copy with outbound effects blocked. The owner must review the config, credentials and access before running the exporter.
