# Release Notes — Sustainable Catalyst Foundations v2.0.5

## Fixed

- Replaced the actual `[sc_foundations_library]` shortcode callback after all
  Knowledge Library components register.
- Removed reliance on `pre_do_shortcode_tag`, which did not become the active
  rendering path on the live Foundations page.
- Rendered the canonical Foundations collection directly from WordPress taxonomy
  membership.
- Included every post type registered to the Library Collection taxonomy rather
  than limiting results to `sc_foundation_doc`.
- Preserved retained documentation metadata and new Foundation Document metadata.
- Removed browser-side REST and search-index requirements from the canonical page.
- Preserved the original documentation interface on non-Foundations pages.
