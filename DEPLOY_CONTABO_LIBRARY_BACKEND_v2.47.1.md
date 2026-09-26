# Deploy Knowledge Library backend v2.47.1

Copy `sustainable-catalyst-library-backend-v2.47.1.zip` and `upgrade_library_backend_v2_47_1_contabo.sh` to `/tmp` on the Contabo host, then run the installer with sudo.

The installer:
1. verifies Python backend 2.47.1, Go runtime 0.1.0 and Rust runtime 0.2.0 source identities;
2. backs up the existing backend;
3. preserves `.env`;
4. performs `docker compose build --no-cache`;
5. starts `sc-library-backend` and `sc-library-ingestion`;
6. checks the Go contract and durable state configuration;
7. executes an authenticated ingestion job lifecycle including retry;
8. verifies Rust native graph continuity;
9. verifies Platform Core readiness.
