# Deploy Sustainable Catalyst Library Backend v1.0.2 on Contabo

v1.0.2 upgrades the existing Library backend in place. It preserves the existing PostgreSQL database, Caddy route, Docker network, API key, database password, and `.env` settings.

## From the Mac

```bash
cd ~/Downloads/sustainable-catalyst-library-v5.5.2-release

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  sustainable-catalyst-library-backend-v1.0.2.zip \
  catalystadmin@94.72.113.77:/tmp/

scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  upgrade_library_backend_v1_0_2_contabo.sh \
  catalystadmin@94.72.113.77:/tmp/

ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  catalystadmin@94.72.113.77
```

## On the VPS

```bash
chmod +x /tmp/upgrade_library_backend_v1_0_2_contabo.sh

/tmp/upgrade_library_backend_v1_0_2_contabo.sh \
  /tmp/sustainable-catalyst-library-backend-v1.0.2.zip
```

The upgrader:

1. verifies the current backend `.env` exists;
2. creates a timestamped application backup and `.env` backup;
3. replaces application files while preserving `.env`;
4. validates Docker Compose;
5. rebuilds and recreates only `sc-library-backend`;
6. waits for Docker health instead of testing during `health: starting`;
7. validates local `/health` and `/ready`;
8. confirms backend version `1.0.2`.

No PostgreSQL migration is required.

## Expected local validation

```bash
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/ready  | python3 -m json.tool
```

Expected health capability markers include:

```json
"operations_recovery": true,
"integrity_audit": true,
"targeted_pruning": true
```

The existing corpus counts should remain intact.

## Public endpoint

No Caddy change is needed:

```bash
curl -fsS https://library-api.sustainablecatalyst.com/health \
  | python3 -m json.tool
```

## WordPress

After backend v1.0.2 is healthy, install `sustainable-catalyst-library-v5.5.2-wordpress.zip`.

Then open:

**SC Library → Backend Operations**

Run **Integrity audit**. A healthy synchronized corpus should return zero missing, stale, orphaned, and chunkless records. If differences exist, use the targeted repair controls before considering a full reindex.
