# Deploy Library backend v2.23.0 — Platform Core Research Bridge

The release uses the existing Library backend container at `/opt/sustainable-catalyst/library-backend` and the existing Platform Core service at `https://core.sustainablecatalyst.com`.

The deployment script:

1. validates the backend archive;
2. backs up the current backend;
3. preserves the existing Library `.env`;
4. adds the new Platform Core bridge environment settings;
5. imports the existing Core server write key from the running `sc-core` container when needed, without printing the secret;
6. rebuilds/recreates `sc-library-backend`;
7. verifies backend v2.23.0 health;
8. verifies all eight Core capability probes;
9. verifies the additive PostgreSQL binding/outbox tables.

No Platform Core redeploy is required for this release.

## VPS command

```bash
cd /tmp
unzip -q sustainable-catalyst-library-v5.12.0-release-bundle.zip -d sc-library-v5120
cd sc-library-v5120
chmod +x upgrade_library_backend_v2_23_0_contabo.sh
./upgrade_library_backend_v2_23_0_contabo.sh "$PWD/sustainable-catalyst-library-backend-v2.23.0.zip"
```

If the script reports that the Core write key is unavailable, set `SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY` in `/opt/sustainable-catalyst/library-backend/.env` to the same server-side value Core uses for `SC_CORE_WRITE_API_KEY`, then rerun. Do not paste that value into WordPress or browser JavaScript.
