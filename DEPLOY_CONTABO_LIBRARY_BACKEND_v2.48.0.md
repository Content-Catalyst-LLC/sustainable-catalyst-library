# Deploy Knowledge Library Backend v2.48.0

Backend v2.48.0 adds the Research Corpus Builder & Dataset Export while preserving Go ingestion runtime v0.1.0 and Rust graph runtime v0.2.0.

The deployment installer performs a no-cache Docker build, verifies backend health/capabilities, builds a deterministic two-record deployment corpus, verifies explicit exclusion and row-level provenance, exports the corpus as a JSON/JSONL/CSV bundle, rechecks the Go durable job fabric, forces Rust native graph execution, and checks Platform Core readiness.

Run:

```bash
sudo ./upgrade_library_backend_v2_48_0_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v2.48.0.zip
```

Expected final line:

`PASS: Library backend v2.48.0 Research Corpus Builder & Dataset Export deployed and verified.`
