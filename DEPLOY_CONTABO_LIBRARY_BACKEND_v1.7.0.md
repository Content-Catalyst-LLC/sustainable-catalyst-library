# Deploy Sustainable Catalyst Library Backend v1.7.0

v1.7.0 adds biomedical evidence grading and study-design intelligence.

## Deployment impact
- Rebuild/recreate `sc-library-backend`.
- No PostgreSQL migration.
- No DNS, Caddy, port or Docker-network changes.
- Existing `.env` is preserved.
- No new required credential.

## Upload from macOS
```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v1.7.0.zip \
  upgrade_library_backend_v1_7_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## Upgrade on Contabo
```bash
chmod +x /tmp/upgrade_library_backend_v1_7_0_contabo.sh

/tmp/upgrade_library_backend_v1_7_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.7.0.zip
```

The upgrader validates `/health` and `/v1/evidence-grading` after the container becomes healthy.
