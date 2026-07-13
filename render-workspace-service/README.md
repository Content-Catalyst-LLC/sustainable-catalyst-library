# Sustainable Catalyst Library Workspace Service v1.12.0

Optional FastAPI/PostgreSQL service for secondary synchronization of WordPress-owned Library account workspaces.

## Boundary

- WordPress authenticates users and enforces owner/collaborator permissions.
- The browser never receives `SC_LIBRARY_SYNC_API_KEY` or `DATABASE_URL`.
- WordPress sends signed `sc-library-sync/1.0` packets to Render.
- The service uses optimistic revision checks and rejects stale or divergent writes with HTTP 409.
- WordPress remains the primary account-workspace record. Render is an optional synchronized application store.

## Local run

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements-dev.txt
cp .env.example .env
uvicorn app.main:app --reload
```

## Render

Deploy `render.yaml`, copy the generated `SC_LIBRARY_SYNC_API_KEY` into WordPress under **SC Library → Settings**, and set the Render workspace service URL. Keep the key server-side.

## Deployment note

The included Blueprint uses free resources for evaluation. Review database retention, backups, and service availability before production use.
