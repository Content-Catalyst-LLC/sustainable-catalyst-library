# Deploy Library Backend v2.4.0 — Carbon & Nature Intelligence v0.3.0

Upload `sustainable-catalyst-library-backend-v2.4.0.zip` and `upgrade_library_backend_v2_4_0_contabo.sh` to `/tmp` on the Contabo VPS, then run the upgrader as `catalystadmin`.

The upgrader preserves the existing `.env`, creates a timestamped application backup, rebuilds the Docker service, waits for health, and validates the Carbon & Nature manifest, evidence registry, methodology registry, graph, graph neighborhood, and Research Librarian context handoff.

No PostgreSQL migration and no new environment variables are required.
