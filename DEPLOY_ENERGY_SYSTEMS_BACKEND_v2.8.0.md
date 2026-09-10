# Deploy Library backend v2.8.0

The v2.8.0 backend carries Energy Systems Intelligence v0.2.0. It requires no database migration and preserves the live `.env` during deployment.

From macOS:

```bash
cd ~/Downloads

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.8.0.zip \
  upgrade_library_backend_v2_8_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

On Contabo:

```bash
chmod +x /tmp/upgrade_library_backend_v2_8_0_contabo.sh
/tmp/upgrade_library_backend_v2_8_0_contabo.sh
```

The upgrader backs up the current application and environment, rebuilds the Docker service, waits for health, and verifies Carbon & Nature v0.5.0 plus the Energy Systems v0.2.0 registry and three source-bound calculations.
