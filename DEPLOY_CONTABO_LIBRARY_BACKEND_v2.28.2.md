# Deploy Knowledge Library backend v2.28.2

Transfer `sustainable-catalyst-library-backend-v2.28.2.zip` and `upgrade_library_backend_v2_28_2_contabo.sh` to `/tmp`, connect to the Contabo VPS, and run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_28_2_contabo.sh
./upgrade_library_backend_v2_28_2_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.28.2.zip
```

The installer backs up the current backend, preserves `.env`, rebuilds the Docker service, verifies backend v2.28.2, checks the safe `post` fallback, validates explicit manifest filtering, preserves single-publication drill-down, verifies publication-visualization readiness, checks Platform Core 8/8 readiness, and runs a hybrid-search smoke test.
