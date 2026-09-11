# Deploy Library Backend v2.16.0

Expected payload: `sustainable-catalyst-library-backend-v2.16.0.zip`.

The deployment upgrader preserves the existing `.env`, creates a timestamped backup under `/opt/sustainable-catalyst/backups`, repairs the backup directory ownership when necessary, replaces the backend source, rebuilds/recreates the Docker service, waits for health, and validates Energy Systems v1.0.0 plus representative prior layers.

No database migration and no new environment variable or API secret are required.

```bash
chmod +x /tmp/upgrade_library_backend_v2_16_0_contabo.sh
/tmp/upgrade_library_backend_v2_16_0_contabo.sh
```

A successful run ends with:

```text
PASS: Sustainable Catalyst Library backend v2.16.0 / Energy Systems v1.0.0 deployed and verified.
```
