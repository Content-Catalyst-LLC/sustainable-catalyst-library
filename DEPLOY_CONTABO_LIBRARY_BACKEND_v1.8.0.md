# Deploy Library Backend v1.8.0 to Contabo

No database migration, DNS change, Caddy change, port change, or credential rotation is required.

From macOS, upload the generated backend ZIP and upgrader to `/tmp`, then SSH into the VPS. Run the v1.8.0 upgrader against the ZIP.

The upgrader preserves `/opt/sustainable-catalyst/library-backend/.env`, creates timestamped application and environment backups, rebuilds the Docker service, waits for `sc-library-backend` to become healthy, verifies `/health` reports version `1.8.0`, and verifies the biomedical evidence graph manifest keeps automated pooled effects and clinical recommendations disabled.
