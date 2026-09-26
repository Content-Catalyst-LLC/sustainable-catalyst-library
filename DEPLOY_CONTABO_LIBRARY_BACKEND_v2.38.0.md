# Deploy Knowledge Library Backend v2.38.0 to Contabo

Backend v2.38.0 adds Source Identity, Deduplication & Entity Resolution while preserving v2.37 scientific-document intelligence, v2.36 graph/pathfinding, and v2.35 evidence synthesis.

From macOS, copy the backend archive and installer:

```bash
cd ~/Downloads/sc-library-v5.27.0-release

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.38.0.zip \
  upgrade_library_backend_v2_38_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/
```

Connect:

```bash
ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

Deploy:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_38_0_contabo.sh

./upgrade_library_backend_v2_38_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.38.0.zip
```

The installer backs up the current backend, preserves `.env`, rebuilds the Docker service, verifies backend 2.38.0 capabilities, executes a live DOI/version-family + ORCID/ROR/dataset identity test, verifies corpus identity integration, rechecks v5.26/v5.25/v5.24 contracts, checks Platform Core readiness, and verifies hybrid retrieval.
