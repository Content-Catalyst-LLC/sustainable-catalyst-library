# Knowledge Library v5.14.0.1 — Citation Graph Route Precedence Repair

Runtime repair for v5.14.0 Citation Graph & Scholarly Lineage.

## Fixed
- Registers `GET /v1/citations/{record_id:path}/graph` before the generic `GET /v1/citations/{record_id:path}` route.
- Prevents Starlette from consuming `/graph` as part of the publication record identifier.
- Adds an HTTP-level regression test using `wordpress:1:post:1621`-style record IDs.
- Preserves v5.14.0 citation schemas, Platform Core scholarly-lineage handoff, and WordPress plugin identity.

## Versions
- Knowledge Library repair tag: `v5.14.0.1`
- WordPress plugin: unchanged at `5.14.0`
- Python backend: `2.25.1`

No WordPress reinstall and no database migration are required.
