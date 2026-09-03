# Deploy Sustainable Catalyst Library Backend v1.3.0

v1.3.0 is required for Library v5.8.0 biomedical search. It is an in-place backend code upgrade with **no PostgreSQL migration, DNS change, Caddy change, port change, or credential rotation**.

## From macOS

```bash
cd ~/Downloads/sustainable-catalyst-library-v5.8.0-release

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v1.3.0.zip \
  upgrade_library_backend_v1_3_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## On the VPS

```bash
chmod +x /tmp/upgrade_library_backend_v1_3_0_contabo.sh

/tmp/upgrade_library_backend_v1_3_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.3.0.zip
```

The upgrader preserves `/opt/sustainable-catalyst/library-backend/.env`, backs up the current application, rebuilds only `sc-library-backend`, verifies Docker health, verifies backend version `1.3.0`, and confirms all five biomedical source descriptors are registered.

Optional NCBI settings can be added to the existing `.env` later:

```text
SC_LIBRARY_NCBI_TOOL=sustainable_catalyst_library
SC_LIBRARY_NCBI_EMAIL=your-operational-email@example.com
SC_LIBRARY_NCBI_API_KEY=
```

The API key is optional for low-rate use. Do not expose it to WordPress or browsers.
