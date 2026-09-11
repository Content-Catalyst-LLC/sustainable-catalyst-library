# Deploy Library Backend v2.13.0

Copy `sustainable-catalyst-library-backend-v2.13.0.zip` and `upgrade_library_backend_v2_13_0_contabo.sh` to `/tmp` on the Contabo VPS, then run the upgrader as `catalystadmin`.

The upgrader preserves the existing `.env`, takes a timestamped backup, repairs `/opt/sustainable-catalyst/backups` ownership with `sudo` only if that directory is not writable, deploys the cumulative backend, rebuilds/recreates the Docker service, waits for health, and verifies Energy Systems v0.7.0 plus Carbon & Nature v0.5.0.

Because the v2.13.0 archive is cumulative and there is no database migration, it can replace a still-running v2.11.0 or v2.12.0 Library backend. It does not require the failed v2.12.0 deployment to be completed first.
