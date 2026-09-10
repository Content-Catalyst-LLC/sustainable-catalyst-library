# Deploy Library Backend v2.7.0

Energy Systems Intelligence v0.1.0 advances the shared Sustainable Catalyst Library Python backend from v2.6.0 to v2.7.0. No PostgreSQL migration or new secret is required. The upgrader preserves the existing `.env`, creates an application backup, rebuilds the Docker service, and verifies both retained Carbon & Nature v0.5.0 and new Energy Systems endpoints.

## From macOS

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.7.0.zip \
  upgrade_library_backend_v2_7_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## On Contabo

```bash
chmod +x /tmp/upgrade_library_backend_v2_7_0_contabo.sh
/tmp/upgrade_library_backend_v2_7_0_contabo.sh
```

The script verifies `/health`, `/v1/carbon-nature`, `/v1/energy-systems`, `/v1/energy-systems/knowledge-map`, `/v1/energy-systems/sources`, and `/v1/energy-systems/handoffs` before reporting success.
