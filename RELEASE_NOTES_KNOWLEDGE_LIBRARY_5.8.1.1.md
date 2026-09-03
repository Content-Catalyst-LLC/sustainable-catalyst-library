# Release Notes — Sustainable Catalyst Library v5.8.1.1

## Release Console Version Identity & Runtime Synchronization Repair

- Advances the WordPress Library release identity from 5.8.1 to 5.8.1.1.
- Fixes the homepage/research console so its public release label is sourced from the canonical `SC_LIBRARY_VERSION` instead of the historical console module version.
- Preserves `SC_Library_Homepage_Console::VERSION = 5.7.1` as module provenance rather than pretending the module originated in this patch.
- Adds public runtime status route `/wp-json/sc-library/v1/runtime/release`.
- Runtime payload separates current Library release identity, installed WordPress release identity, synchronization state, backend service state, and backend version.
- Adds `Cache-Control: no-store, max-age=0` to the runtime release response to reduce stale visible release identity.
- Homepage console displays `LIBRARY: v5.8.1.1` immediately and resolves backend runtime asynchronously, e.g. `BACKEND: v1.4.0 · ONLINE`.
- Adds a visible drift state if the installed WordPress option and canonical code release differ.
- Existing frontend assets continue using `SC_LIBRARY_VERSION` as their cache-busting version.
- Library backend remains 1.4.0; no backend redeploy is required.
- No PostgreSQL migration, Caddy change, DNS change, port change, credential rotation, or API-key change.
- Preserves v5.8.1 FDA Drug & Regulatory Intelligence, v5.8.0 Biomedical & Clinical Evidence, v5.7.x institutional/Johns Hopkins integration, Dynamic Explorer, and Publications behavior.
