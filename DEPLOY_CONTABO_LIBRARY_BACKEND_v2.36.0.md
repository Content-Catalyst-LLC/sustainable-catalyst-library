# Deploy Knowledge Library backend v2.36.0 to Contabo

The release bundle contains `sustainable-catalyst-library-backend-v2.36.0.zip` and `upgrade_library_backend_v2_36_0_contabo.sh`.

## 1. From macOS, copy the backend and installer

```bash
cd ~/Downloads/sc-library-v5.25.0-release

scp -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v2.36.0.zip \
  upgrade_library_backend_v2_36_0_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/
```

## 2. Connect to the VPS

```bash
ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## 3. Run the upgrade

```bash
chmod +x /tmp/upgrade_library_backend_v2_36_0_contabo.sh
sudo /tmp/upgrade_library_backend_v2_36_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.36.0.zip
```

The installer backs up the existing backend, preserves `.env`, replaces the application payload, rebuilds/recreates the Docker service, waits for health, and runs release smoke tests.

## Expected release identity

- `/health` returns `ok: true`
- backend version is `2.36.0`
- research graph query capability is enabled
- evidence pathfinding capability is enabled
- direction-aware traversal capability is enabled
- analytical path edges remain opt-in
- v5.24 cross-publication synthesis remains available
- Platform Core readiness remains reachable
