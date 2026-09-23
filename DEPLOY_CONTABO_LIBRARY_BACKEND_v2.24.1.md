# Deploy Library Backend v2.24.1

Backend-only repair for Knowledge Library v5.13.0.1. No WordPress reinstall and no new database migration are required.

Copy `sustainable-catalyst-library-backend-v2.24.1.zip` and `upgrade_library_backend_v2_24_1_contabo.sh` to `/tmp` on the VPS, then execute the upgrade script. The script preserves `.env`, rebuilds the existing Docker service, verifies backend identity and Platform Core readiness, and requires the live Core-aware `/v1/search` smoke test to return HTTP 200.
