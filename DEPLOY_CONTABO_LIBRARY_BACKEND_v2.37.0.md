# Deploy Knowledge Library backend v2.37.0

## From macOS

```bash
cd ~/Downloads/sc-library-v5.26.0-release

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.37.0.zip \
  upgrade_library_backend_v2_37_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## On Contabo

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_37_0_contabo.sh

./upgrade_library_backend_v2_37_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.37.0.zip
```

The installer backs up the current backend, preserves `.env`, rebuilds the Docker service, verifies backend identity `2.37.0`, runs a structured scientific-document test, validates the corpus scientific-object manifest, checks v5.25 graph/pathfinding preservation, confirms Platform Core readiness, and verifies hybrid retrieval.
