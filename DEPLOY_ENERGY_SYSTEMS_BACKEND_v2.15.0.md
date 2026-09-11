# Deploy Library Backend v2.15.0 — Energy Systems Intelligence v0.9.0

Upload `sustainable-catalyst-library-backend-v2.15.0.zip` and `upgrade_library_backend_v2_15_0_contabo.sh` to `/tmp` on the Contabo VPS, then run the upgrader as `catalystadmin`.

The upgrader:

1. verifies the archive identity;
2. repairs the shared backup directory if it is root-owned/unwritable;
3. backs up the current Library backend;
4. preserves `.env`;
5. installs the cumulative v2.15.0 backend;
6. rebuilds/recreates `sc-library-backend`;
7. waits for the Docker health check;
8. validates Energy Systems v0.9.0, the decision framework, decision packet template, neutral matrix/readiness behavior, v0.8.0 Global Energy preservation, v0.7.0 bioenergy, v0.6.0 economics, v0.5.0 balance, and Carbon & Nature v0.5.0.

No database migration and no new secret are required.
