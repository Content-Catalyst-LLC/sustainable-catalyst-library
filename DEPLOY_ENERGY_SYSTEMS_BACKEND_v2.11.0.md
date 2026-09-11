# Deploy Library Backend v2.11.0

Upload `sustainable-catalyst-library-backend-v2.11.0.zip` and `upgrade_library_backend_v2_11_0_contabo.sh` to `/tmp` on the VPS, then run the upgrader.

The upgrader:

1. requires the existing `/opt/sustainable-catalyst/library-backend/.env`;
2. backs up the application and environment;
3. validates backend v2.11.0 and Energy Systems v0.5.0 identity;
4. replaces application files while preserving `.env`;
5. rebuilds and recreates the Docker service;
6. waits for container health;
7. verifies Carbon & Nature v0.5.0, prior Energy Systems layers, the balance framework, conversion-chain arithmetic, supply–demand accounting, capacity-factor generation arithmetic, scenario contract, and disabled dispatch/reliability/optimization boundaries.

The upgrade is additive and requires no PostgreSQL migration. The v0.5.0 balance calculations are stateless GET operations driven by explicit inputs.
