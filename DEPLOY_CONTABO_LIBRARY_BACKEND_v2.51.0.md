# Deploy Knowledge Library Backend v2.51.0

v2.51.0 is the backend companion to Knowledge Library v5.40.0.

## Production order
1. Push/tag the v5.40.0 repository release from the Mac installer.
2. Transfer `sustainable-catalyst-library-backend-v2.51.0.zip` and `upgrade_library_backend_v2_51_0_contabo.sh` to `/tmp` on Contabo.
3. Run the upgrade script with sudo.
4. Confirm `/health` reports `2.51.0`.
5. Confirm `/v1/embeddings/specification` and `/v1/embeddings/governance/readiness` return the new contracts.
6. Review a signed dry-run embedding backfill before queuing any recompute.

The installer preserves the existing `.env`. Because the new compute target defaults to `local`, no environment edit is required for a safe upgrade from v2.50.1.
