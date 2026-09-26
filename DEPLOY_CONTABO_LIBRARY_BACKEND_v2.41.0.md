# Deploy Knowledge Library backend v2.41.0 to Contabo

From macOS, copy the backend archive and installer:

```bash
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.41.0.zip \
  upgrade_library_backend_v2_41_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/
```

Connect:

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77
```

On the VPS:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_41_0_contabo.sh
./upgrade_library_backend_v2_41_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.41.0.zip
```

The installer preserves `.env`, creates a pre-release backup, rebuilds the Docker service, waits for health, verifies methodology-intelligence guardrails, rechecks v5.29 temporal behavior, verifies Platform Core readiness, and tests hybrid retrieval.
