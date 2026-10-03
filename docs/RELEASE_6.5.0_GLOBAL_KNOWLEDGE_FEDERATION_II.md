# Sustainable Catalyst Knowledge Library v6.5.0

## Global Knowledge Federation II

**Generations:** Library 6.5.0 · backend 3.5.0 · independent web 2.5.0 · SDK 1.5.0 · optional WordPress adapter 6.5.0

v6.5.0 promotes global knowledge federation from a registry/certification foundation into an independent Library-native research orchestration layer. It composes the existing global source registry, connector federation runtime, v6.4 advanced semantic and cross-language discovery, original-language provenance rules, and user-controlled trust boundaries.

### New federation capabilities

- Regional, language, script, and context discovery lenses.
- Expanded source registry spanning global open scholarship, national/regional repositories, cultural heritage collections, and official public-statistics sources.
- Federation plans that explicitly separate Python-native connectors, browser handoffs, pending adapters, and registry-only sources.
- Original-language-first and cross-language query planning without silently replacing canonical source text with translations.
- Explicit user trust and exclusion policies that remain separate from descriptive source-quality signals.
- Local Library discovery can execute immediately; external sources are planned but are **not automatically fetched or imported** by this release.
- First-party Python and JavaScript client methods for the new federation endpoints.
- Independent Web 2.5.0 system readiness visibility for Global Knowledge Federation II.

### New API endpoints

- `GET /api/library/v1/federation/global`
- `GET /api/library/v1/federation/global/readiness`
- `GET /api/library/v1/federation/global/lenses`
- `GET /api/library/v1/federation/global/sources/{source_id}`
- `POST /api/library/v1/federation/global/plan`
- `POST /api/library/v1/federation/global/discover`

### Architectural boundaries

The Library remains the source/research-state authority. Federation membership is not endorsement, partnership, evidence quality, or truth. Connector health is operational metadata, not a research-quality judgment. New external sources are registry-only or browser-handoff unless an actual Python adapter already exists. No new database migration is required in v6.5.0.

### Lineage preserved

- v6.3.0 Research Projects & Saved Workspaces
- v6.4.0 Advanced Semantic & Cross-Language Discovery
- v6.4.0.1 runtime Web-version metadata repair

### Next architecture release

**v6.6.0 — Research Graph & Evidence Navigation**
