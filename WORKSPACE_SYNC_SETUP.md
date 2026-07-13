# Library v1.13.3 Workspace Sync Setup

## 1. Install the WordPress update

Upload `sustainable-catalyst-library-v1.13.3.zip`, replace the previous plugin, and activate it.

Open **SC Library**, save settings once, and rebuild the Library index.

## 2. Test WordPress account storage first

Before configuring Render:

1. Enable **Persistent workspaces and Render synchronization**.
2. Set the mode to **Hybrid local + account revision**.
3. Leave account autosave disabled.
4. Open `[sc_library_notebook tab="sync"]` while signed in.
5. Export a local JSON backup.
6. Create an account workspace.
7. Make a Notebook change and use **Save current**.
8. Open another browser session, sign in, and load the account workspace.

## 3. Optional Render service

Deploy `render-workspace-service/render.yaml` or create a Render web service and PostgreSQL database manually. The included free Blueprint is intended for evaluation; choose persistent production infrastructure before relying on it as a long-term workspace store.

Required environment variables:

```text
DATABASE_URL
SC_LIBRARY_SYNC_API_KEY
SC_LIBRARY_MAX_WORKSPACE_MB=8
```

Copy the same generated `SC_LIBRARY_SYNC_API_KEY` into WordPress. Enter the Render service root URL, including `https://`.

Do not place the key in page HTML, JavaScript, a public repository, or browser storage.

## 4. Verify health

Open **SC Library → Workspace Sync**. The service should show **Online** with version `1.13.0`.

The WordPress health request uses:

```text
https://your-service.onrender.com/health
```

## 5. Enable autosave only after manual testing

Account autosave creates explicit account revisions after browser-local changes. Render synchronization is queued through WP-Cron. Keep it disabled until manual account save, load, conflict, and Render sync tests succeed.

## Conflict behavior

- Newer Render revision: WordPress pulls it during automatic comparison.
- Newer WordPress revision: WordPress pushes it.
- Same revision and same hash: no data transfer.
- Same revision and different hash: the workspace is marked **Conflict**.
- A forced local strategy increments the local revision before pushing.

No conflict is silently resolved.

## Storage boundary

- WordPress owns account identity, collaborators, and the primary revision.
- Render is optional secondary storage.
- Canonical publications remain in WordPress posts and taxonomies.
- Private research is never added to the public Library index.

## v1.14.0 service addendum

The optional service now reports version `1.14.1` and workspace schema `sc-library-workspace/1.8`. Existing 1.7 workspaces remain accepted and migrate forward. The same signed service can also process document and authorized media jobs.
