# Deploy Knowledge Library Backend v2.51.1

v2.51.1 is the backend companion to Library v5.40.0.1.

1. Push/tag the v5.40.0.1 repository release from macOS.
2. Transfer `sustainable-catalyst-library-backend-v2.51.1.zip` and `upgrade_library_backend_v2_51_1_contabo.sh` to `/tmp`.
3. Run the installer with sudo.
4. Confirm `/health` reports `2.51.1`.
5. Confirm the signed embedding backfill request returns the `sc-library-embedding-backfill/1.0` payload with `dry_run=true` and HTTP 200.

Deployment verification never queues or recomputes embeddings.
