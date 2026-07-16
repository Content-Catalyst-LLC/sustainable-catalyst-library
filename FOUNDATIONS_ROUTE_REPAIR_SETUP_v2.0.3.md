# Sustainable Catalyst Foundations v2.0.3

## Idempotent Route Collision Repair

This release replaces the brittle v2.0.2 source patcher. It supports alternate
PHP spacing, quote styles, array syntax, files already using the repaired route,
and Library builds where the legacy declaration is absent.

### Canonical routes

- Institutional Foundations page: `/institution/foundations/`
- Individual Foundation Documents: `/foundation-documents/<document-slug>/`
- Foundations catalog shortcode: `[sc_foundations_catalog]`

### Failed v2.0.2 recovery

The installer recognizes the exact files left by the failed v2.0.2 attempt. It
creates a safety backup, resets only that partial release state, and continues.
It aborts when unrelated repository changes are present.

### After installation

Upload the generated WordPress plugin ZIP, choose **Replace current with
uploaded**, then purge WordPress and Cloudflare caches for the Foundations page
and Foundation Document routes.
