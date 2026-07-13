# Sustainable Catalyst Library v1.12.0

Library v1.12.0 adds **Persistent Workspaces, Accounts, and Render Synchronization** to the Sustainable Catalyst knowledge base.

The browser-local Notebook remains fully usable. Signed-in WordPress users can explicitly save account revisions, move research across devices, share workspaces with viewer or editor roles, review revision history, and optionally synchronize a secondary copy to a Render FastAPI/PostgreSQL service.

## Included

- WordPress account-owned persistent workspaces
- Local, account, and hybrid storage modes
- One-click local-to-account migration
- Explicit save and load controls
- Optional debounced account autosave
- Revision numbers and content hashes
- Optimistic concurrency and HTTP 409 conflict responses
- Revision history
- Private, shared, and public visibility
- Viewer and editor collaborators tied to WordPress accounts
- Export-before-replace safeguards
- Independent sync states: local, pending, synced, conflict, and error
- Optional Render health checks and server-to-server synchronization
- Signed `sc-library-sync/1.0` handoff packets
- Render FastAPI/PostgreSQL service and Blueprint
- PostgreSQL portable-export schema v1.2 with account workspace entities
- Workspace schema `sc-library-workspace/1.7`

## WordPress administration

Open:

```text
SC Library → Workspace Sync
```

Configure persistence in **SC Library** settings:

- Enable persistent workspaces
- Choose local, account, or hybrid mode
- Set the maximum account workspace size
- Enable account autosave only after testing
- Optionally enter the Render service URL and server key

## Shortcodes

```text
[sc_library_account_workspaces]
[sc_library_notebook tab="sync"]
```

Existing Library and Notebook shortcodes continue to work.

## REST endpoints

- `/wp-json/sustainable-catalyst/v1/library/account/status`
- `/wp-json/sustainable-catalyst/v1/library/workspaces`
- `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}`
- `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/history`
- `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/share`
- `/wp-json/sustainable-catalyst/v1/library/workspaces/{uuid}/sync`
- `/wp-json/sustainable-catalyst/v1/library/workspaces/render/status`

Account routes require a signed-in WordPress user and REST nonce. The public route returns only workspaces explicitly marked public.

## Render service

The optional service is in `render-workspace-service/`.

```text
WordPress account workspace
        ↓ signed server request
Render FastAPI service
        ↓
PostgreSQL workspace and revision tables
```

WordPress remains the identity and permission authority. Render stores a secondary application copy and rejects stale or divergent revisions.

See `WORKSPACE_SYNC_SETUP.md` and `render-workspace-service/README.md`.
