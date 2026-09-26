# Deploy Knowledge Library backend v2.42.0

Copy `sustainable-catalyst-library-backend-v2.42.0.zip` and `upgrade_library_backend_v2_42_0_contabo.sh` to `/tmp` on the VPS, then execute the installer with sudo. The installer backs up the current backend, preserves `.env`, rebuilds the Docker service, checks health/version, exercises the v5.31 gap/novelty contract, confirms Platform Core readiness, and verifies hybrid retrieval.
