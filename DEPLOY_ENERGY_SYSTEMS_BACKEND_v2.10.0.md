# Deploy Library Backend v2.10.0

Upload `sustainable-catalyst-library-backend-v2.10.0.zip` and `upgrade_library_backend_v2_10_0_contabo.sh` to `/tmp` on the VPS, then run the upgrader.

The upgrader:

1. requires the existing `/opt/sustainable-catalyst/library-backend/.env`;
2. backs up the application and environment;
3. validates backend v2.10.0 and Energy Systems v0.4.0 identity;
4. replaces application files while preserving `.env`;
5. rebuilds and recreates the Docker service;
6. waits for container health;
7. verifies Carbon & Nature v0.5.0, the v0.2.0 numeric registry, all 30 v0.3.0 indicators, the seven renewable technologies, six resource classes, technology/resource contracts, and disabled suitability/ranking boundaries.

The upgrade is additive. Energy Systems v0.4.0 is a governed read-only object/contract layer and requires no PostgreSQL migration.
