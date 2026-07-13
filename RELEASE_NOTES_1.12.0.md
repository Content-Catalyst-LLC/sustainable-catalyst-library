# Release Notes — Sustainable Catalyst Library v1.12.0

## Persistent Workspaces, Accounts, and Render Synchronization

### Account storage

- Persistent workspace records owned by WordPress users
- Local, account, and hybrid storage modes
- Local-to-account migration
- Cross-device loading
- Explicit revision saves
- Optional account autosave
- Configurable workspace size limit

### Revisions and recovery

- Monotonic revision numbers
- SHA-256 content hashes
- Revision history
- Export-before-replace controls
- Optimistic concurrency
- Conflict responses rather than silent overwrites
- Local-wins and remote-wins synchronization strategies through the REST layer

### Permissions

- Private, shared, and public visibility
- WordPress-account collaborators
- Viewer and editor roles
- Owner-only deletion and sharing administration
- Public read-only endpoint only for deliberately public workspaces

### Render synchronization

- Optional FastAPI/PostgreSQL service
- Render Blueprint
- Server-to-server bearer authentication
- Timestamped HMAC request signatures over method, path, timestamp, and body
- Independent health and sync status
- Push, pull, no-change, conflict, and error states
- WordPress remains the identity and permission authority

### Portable data

- Workspace schema upgraded to `sc-library-workspace/1.7`
- Portable export schema upgraded to `sc-library-portable-export/1.2`
- Added account workspaces, revisions, collaborators, and sync-log entities
