# Deploy Library backend v2.39.0

Copy `sustainable-catalyst-library-backend-v2.39.0.zip` and `upgrade_library_backend_v2_39_0_contabo.sh` to `/tmp` on the Contabo host, then run the upgrade script. The installer preserves `.env`, creates a dated backup, rebuilds the Docker service, waits for health, verifies v2.39.0 capabilities, exercises retrieval evaluation/profile/reranking, checks live hybrid/adaptive search, Platform Core readiness, and previous v2.38/v2.37/v2.36/v2.35 contracts.
