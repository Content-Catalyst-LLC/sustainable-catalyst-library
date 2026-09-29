# Deploy Library Backend v2.52.0 to Contabo

Transfer the backend ZIP and installer from macOS:

```bash
cd ~/Downloads/sc-library-v5.41.0-release

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.52.0.zip \
  upgrade_library_backend_v2_52_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

On Contabo:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_52_0_contabo.sh
sudo ./upgrade_library_backend_v2_52_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.52.0.zip
```

The installer rebuilds the Python/Rust backend and Go ingestion service, applies the additive schema indexes, and verifies semantic-similarity readiness. It does not queue or recompute embeddings.
