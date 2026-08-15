# Unified Personal Research Environment — v4.4.0

## Purpose
v4.4.0 turns the mature 4.3 private-research toolchain into one coherent signed-in research home. It is deliberately a composition layer rather than a data migration.

## Canonical-store contract
The environment reads from the existing canonical stores: v4.3.28 My Library; v4.3.29 saved searches/watchlists/research queue; v4.3.30 Research Projects and Source Bundles; v4.3.31 Reading Notebooks; v4.3.32 Evidence Matrices; v4.3.36 learning routes; and the established Workspace, Research Librarian II, and portability surfaces. It creates no replacement project, notebook, evidence, learning, or personal-library store.

## Research home
`[sc_personal_research_environment]` shows private account totals, an owned-project selector, current project reference/bundle/notebook/matrix/learning-route counts, bounded recent-work links, and direct navigation into the specialized tools. The selector uses `sc_project` only as page context; it does not create a persisted active-project preference.

## REST
`GET /wp-json/sc-library/v1/personal-research-environment` is authenticated. Optional `project_id` is accepted only when the current user owns that Research Project. The response is a summary/composition payload rather than a dump of note bodies or private source files.

## Privacy and mutation boundary
The environment performs no automatic migration, project write, notebook write, evidence promotion, publication, Workspace mutation, or remote private-context synthesis. Stable IDs, URNs, permissions, provenance, and storage ownership remain with their existing modules.

## Production hardening
The existing Production Readiness engine is retained. v4.4.0 version-aligns the release gate, adds the new module/assets/private REST route to first-party certification, and keeps third-party provider health non-blocking.
