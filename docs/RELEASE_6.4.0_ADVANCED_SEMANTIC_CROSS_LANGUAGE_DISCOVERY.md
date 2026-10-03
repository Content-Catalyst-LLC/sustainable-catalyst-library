# Sustainable Catalyst Library v6.4.0 — Advanced Semantic & Cross-Language Discovery

Library v6.4.0 adds a Python-authoritative discovery composition layer over the existing retrieval, language/document, and saved-workspace services.

## Release generations

- Library: 6.4.0
- Backend: 3.4.0
- Web: 2.4.0
- SDK: 1.4.0
- API stability boundary: `/api/library/v1`
- WordPress required: false

## Capabilities

- Original-query-first discovery with explicit representation lineage.
- Weighted reciprocal-rank fusion across original, translation, transliteration, alignment, synonym, and other derived query representations.
- Existing lexical, hybrid, semantic, neural, and adaptive retrieval runtimes are reused; no parallel search authority is introduced.
- Multilingual semantic retrieval can operate directly from the original query when the configured embedding model supports multilingual behavior.
- Explicit derived representations allow lexical/hybrid expansion without requiring automatic machine translation.
- Ranking explanations expose representation matches and project-context influence.
- Project-aware discovery is session-scoped to the signed-in Library owner and reuses v6.3 saved-workspace project context.
- Semantic similarity, rank score, translation alignment, and project membership are not treated as evidence truth or quality scores.

## API

- `GET /api/library/v1/discovery`
- `GET /api/library/v1/discovery/readiness`
- `POST /api/library/v1/discovery/plan`
- `POST /api/library/v1/discovery/search`
- `POST /api/library/v1/discovery/projects/{project_id}/search`

## Persistence

No new database migration is required. The release composes existing retrieval, language/document, and research-state authorities.

## Next gate

v6.5.0 — Global Knowledge Federation II.
