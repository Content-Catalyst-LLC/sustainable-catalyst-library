# Deploy Knowledge Library backend v2.49.0

Backend v2.49.0 deploys **Knowledge Library v5.38.0 — Unified Research Runtime Contract** while preserving Go ingestion runtime v0.1.0 and Rust graph runtime v0.2.0.

The installer performs a no-cache Docker build, validates backend/runtime identities, verifies live Python/Go/Rust descriptors, resolves a native graph workload to Rust, exercises signed unified execution across all three engines, then re-runs corpus export, Go durability, Rust native-query, and Platform Core readiness checks.

```bash
sudo ./upgrade_library_backend_v2_49_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.49.0.zip
```

Expected final line:

```text
PASS: Library backend v2.49.0 Unified Research Runtime Contract deployed and verified.
```
