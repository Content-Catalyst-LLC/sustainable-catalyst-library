# Release Notes — Sustainable Catalyst Foundations v2.0.3

## Fixed

- Replaced exact-string source patching with flexible, idempotent matching.
- Supports single or double quotes and arbitrary PHP whitespace.
- Accepts an already-repaired Foundation Document route.
- Accepts Library builds where the legacy route declaration is absent.
- Updates legacy document return links to `/institution/foundations/`.
- Recovers safely from the partial files left by the failed v2.0.2 installer.
- Keeps `/institution/foundations/` reserved for the WordPress page.
- Keeps individual records under `/foundation-documents/<slug>/`.

## Deployment

Run the v2.0.3 macOS installer. It will validate repository state, create a
backup, apply the route repair, run tests, build the plugin ZIP, commit, and
push.
