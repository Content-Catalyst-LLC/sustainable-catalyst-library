# Deploy Library Backend v1.6.0 to Contabo

v1.6.0 adds Clinical Study & Trial Intelligence. No PostgreSQL migration, DNS change, port change, or credential rotation is required.

From macOS:

```bash
cd ~/Downloads
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v1.6.0.zip \
  upgrade_library_backend_v1_6_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77
```

On Contabo:

```bash
chmod +x /tmp/upgrade_library_backend_v1_6_0_contabo.sh
/tmp/upgrade_library_backend_v1_6_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.6.0.zip
```

The upgrader preserves the existing `.env`, backs up the live application, rebuilds the Docker service, waits for health, and verifies the clinical-trial manifest.
