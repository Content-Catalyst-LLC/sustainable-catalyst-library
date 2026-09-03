# Deploy Sustainable Catalyst Library Backend v1.4.0

v1.4.0 is required for Library v5.8.1 FDA Drug & Regulatory Intelligence. This is an in-place code upgrade with **no PostgreSQL migration, DNS change, Caddy change, port change, or credential rotation**.

## From macOS

```bash
cd ~/Downloads/sustainable-catalyst-library-v5.8.1-release

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v1.4.0.zip \
  upgrade_library_backend_v1_4_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## On the VPS

```bash
chmod +x /tmp/upgrade_library_backend_v1_4_0_contabo.sh

/tmp/upgrade_library_backend_v1_4_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.4.0.zip
```

The upgrader preserves `/opt/sustainable-catalyst/library-backend/.env`, backs up the current application, rebuilds only `sc-library-backend`, verifies Docker health, verifies backend version `1.4.0`, and confirms all seven FDA regulatory source descriptors plus the five v1.3.0 biomedical descriptors remain registered.

## Optional/recommended openFDA production key

Add to the existing backend `.env` when you have a key:

```text
SC_LIBRARY_FDA_TIMEOUT_SECONDS=8
SC_LIBRARY_OPENFDA_API_KEY=YOUR_SERVER_SIDE_KEY
```

Keep the key only on the VPS. Do not place it in WordPress, JavaScript, Git, or public documentation.
