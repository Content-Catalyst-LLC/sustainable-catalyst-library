# Foundations v2.0.1 — Foundations Page Route Recovery

## Fixed

- Existing Foundations page could appear unavailable after replacing the Knowledge Library plugin because WordPress retained stale rewrite rules.
- Added a one-time version-aware rewrite refresh.
- Added a safe fallback for the legacy `/foundations/` entry URL.

## Preserved

- Existing `/institution/foundations/` page and content.
- Native Foundation Document archive at `/foundation-documents/`.
- Foundation Document metadata, templates, catalog shortcode, REST endpoint, citations, PDFs, and Knowledge Library integration introduced in v2.0.0.

## Safety

The repair does not modify page records or page slugs. It only refreshes routing state and provides a fallback redirect when the legacy route is reported as a 404.
