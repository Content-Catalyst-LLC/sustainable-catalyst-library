# Deploy Knowledge Library backend v2.28.0

From macOS, copy `sustainable-catalyst-library-backend-v2.28.0.zip` and `upgrade_library_backend_v2_28_0_contabo.sh` to `/tmp` on the Contabo host, then run the upgrade script there.

The installer preserves `.env`, creates a timestamped backup, rebuilds `sc-library-backend`, requires healthy backend v2.28.0, verifies scientific knowledge-map readiness, preserves Publication Visualization Foundations, verifies Platform Core 8/8 readiness, checks hybrid search, and performs a live publication knowledge-map plus citation-route smoke test when a public search result is available.
