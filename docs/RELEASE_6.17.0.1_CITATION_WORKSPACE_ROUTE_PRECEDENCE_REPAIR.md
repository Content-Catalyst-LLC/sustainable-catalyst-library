# Library v6.17.0.1 — Citation Workspace Route Precedence Repair

## Defect
Library v6.17.0 registered the greedy GET route
`/api/library/v1/citations/{record_id:path}` before the static citation-workspace GET routes.

FastAPI/Starlette evaluates routes in registration order. As a result,
`/api/library/v1/citations/workspace/readiness` and `/bootstrap` were captured as
record IDs (`workspace/readiness`, `workspace/bootstrap`) and returned 404.

## Repair
v6.17.0.1 moves the legacy record-citation catch-all after the complete
`/api/library/v1/citations/workspace/*` route family.

## Release identity
- Library: 6.17.0.1
- Backend: 3.17.0.1
- Web: 2.17.0 (unchanged)
- SDK: 1.17.0 (unchanged)
- Database migration: no
- WordPress required: no

## Preserved boundaries
- Existing Python/PostgreSQL citation service remains durable citation authority.
- Bibliographic workspace does not auto-persist citations.
- Duplicate candidates are not auto-merged.
- Citation presence/counts do not establish truth, quality, or evidence strength.
