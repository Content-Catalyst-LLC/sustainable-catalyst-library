# Deploy Library backend v2.1.0 to Contabo

Backend v2.1.0 is a complete replacement package and includes all capabilities from v2.0.0 and earlier.

## Migration behavior

This release includes an additive PostgreSQL schema migration. On backend startup, the existing schema initializer creates the new `library_private_*` tables and indexes using idempotent `CREATE ... IF NOT EXISTS` statements. Existing public records are not moved into the private plane.

No new environment variable or credential is required. The existing `SC_LIBRARY_BACKEND_API_KEY` protects private data routes through the signed server-to-server request contract.

## Mac upload

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.1.0.zip \
  upgrade_library_backend_v2_1_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## Contabo upgrade

```bash
chmod +x /tmp/upgrade_library_backend_v2_1_0_contabo.sh

/tmp/upgrade_library_backend_v2_1_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.1.0.zip
```

The upgrader preserves `.env`, creates an application/environment backup, rebuilds/recreates the backend container, waits for health, validates backend v2.1.0 and the private-knowledge manifest, and verifies that the required private tables exist.
