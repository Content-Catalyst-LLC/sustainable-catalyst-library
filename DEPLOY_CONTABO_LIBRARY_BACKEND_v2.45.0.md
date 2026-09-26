# Deploy Knowledge Library backend v2.45.0 to Contabo

Upload `sustainable-catalyst-library-backend-v2.45.0.zip` and `upgrade_library_backend_v2_45_0_contabo.sh` to `/tmp`, then run the installer as `catalystadmin` with sudo.

The installer preserves `.env`, backs up the existing backend, performs a no-cache Docker build (including Rust graph runtime v0.1.0), verifies backend health/capabilities, exercises the living-evidence endpoint, forces native Rust pathfinding, and confirms Platform Core readiness.
