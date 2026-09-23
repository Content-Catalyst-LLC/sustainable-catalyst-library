# Deploy Library backend v2.28.1

Upload `sustainable-catalyst-library-backend-v2.28.1.zip` and `upgrade_library_backend_v2_28_1_contabo.sh` to `/tmp` on the Contabo VPS, then execute the upgrade script.

The installer preserves `.env`, backs up the current backend, rebuilds/recreates `sc-library-backend`, verifies backend health, checks Platform Core readiness, and performs a live corpus smoke test against `source_key=wordpress-main`.

The deployment is considered successful only when the corpus endpoint reports at least one eligible/analyzed publication and returns publication nodes from `wordpress-main`.
