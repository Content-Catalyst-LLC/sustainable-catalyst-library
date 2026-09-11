# Deploy Library Backend v2.12.0 — Energy Systems v0.6.0

Use `upgrade_library_backend_v2_12_0_contabo.sh`. It preserves the existing `.env`, rebuilds the Docker service, waits for health, and verifies both retained balance endpoints and new economics endpoints. No database migration or new secret is required.
