# Deploy Library Backend v2.44.0

Upload `sustainable-catalyst-library-backend-v2.44.0.zip` and `upgrade_library_backend_v2_44_0_contabo.sh` to `/tmp`, then run the installer with sudo. It backs up the current backend, preserves `.env`, performs a no-cache Docker build (including Rust), recreates the service, verifies health, exercises the literature-review engine, forces a Rust graph path query, and checks Platform Core readiness.
