# Deploy Library Backend v2.54.0

Transfer `sustainable-catalyst-library-backend-v2.54.0.zip` and `upgrade_library_backend_v2_54_0_contabo.sh` to `/tmp` on Contabo, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_54_0_contabo.sh
sudo ./upgrade_library_backend_v2_54_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.54.0.zip
```

The installer builds Python, Go, and Rust runtime images, verifies embedding-map contracts and guardrails, preserves v5.42 neural-reranking and v5.41 semantic-search readiness, and makes no external model call or embedding-backfill request.
