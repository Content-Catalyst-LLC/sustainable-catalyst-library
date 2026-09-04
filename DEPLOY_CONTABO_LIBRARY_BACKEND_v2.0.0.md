# Deploy Library Backend v2.0.0 to Contabo

v2.0.0 is a complete backend package. It can replace the currently deployed Library backend directly.

## Mac upload

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.0.0.zip \
  upgrade_library_backend_v2_0_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## Contabo deployment

```bash
chmod +x /tmp/upgrade_library_backend_v2_0_0_contabo.sh

/tmp/upgrade_library_backend_v2_0_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.0.0.zip
```

The upgrader preserves `.env`, creates an application backup, rebuilds the Docker image, waits for health, and validates the Institutional Research Network II manifest.

No PostgreSQL migration and no new connector credentials are required.
