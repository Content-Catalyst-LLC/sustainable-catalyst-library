# Deploy Knowledge Library Backend v2.56.0 to Contabo

Copy `sustainable-catalyst-library-backend-v2.56.0.zip` and `upgrade_library_backend_v2_56_0_contabo.sh` to `/tmp`, then run:

```bash
cd /tmp
chmod +x upgrade_library_backend_v2_56_0_contabo.sh
sudo ./upgrade_library_backend_v2_56_0_contabo.sh /tmp/sustainable-catalyst-library-backend-v2.56.0.zip
```

The installer preserves the existing `.env`, backs up the current backend, rebuilds Python/Go/Rust containers, initializes the three v5.45 preservation tables, and verifies the original-language readiness and validation contracts. It does not ingest a sample source, translate text, rotate credentials, queue embedding backfill, or call an external model/provider.
