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
