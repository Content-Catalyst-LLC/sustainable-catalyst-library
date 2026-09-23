# Deploy Knowledge Library Backend v2.25.0

Copy `sustainable-catalyst-library-backend-v2.25.0.zip` and `upgrade_library_backend_v2_25_0_contabo.sh` to `/tmp` on the Contabo VPS, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_25_0_contabo.sh
./upgrade_library_backend_v2_25_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.25.0.zip
```

The script preserves `.env`, backs up the prior backend, rebuilds the Docker service, verifies backend v2.25.0, verifies Platform Core 8/8 readiness, checks citation readiness/schema migration, and re-runs the Core-aware hybrid-search smoke test.
