# Release Notes — Sustainable Catalyst Foundations v2.0.4

## Fixed

- Replaced the canonical Foundations page's JavaScript-only documentation loading
  path with server-rendered WordPress output.
- Prevented the message “The documentation library could not be loaded.” from
  replacing the institutional catalog when the REST endpoint fails.
- Added compatibility for both `[sc_foundations_library]` and the legacy
  `[sc_library collection="foundations" mode="documentation"]` shortcode.
- Preserved server-side search, type, and status filters.
- Loaded the Foundation Document design system on the canonical Foundations page.
- Preserved the v2.0.3 route collision repair.
