# Deploy Sustainable Catalyst Library Backend v1.0.1 on Contabo

This is an in-place code upgrade from backend v1.0.0. It does **not** replace PostgreSQL, change the `sc_library` database, rotate credentials, or change Caddy.

## Preconditions

Expected existing state:

- `/opt/sustainable-catalyst/library-backend`
- container `sc-library-backend`
- Docker network `sc-internal`
- PostgreSQL container `sc-postgres`
- public endpoint `https://library-api.sustainablecatalyst.com`
- a working `.env` in the backend directory

## Upload from macOS

```bash
scp -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes \
  ~/Downloads/sustainable-catalyst-library-backend-v1.0.1.zip \
  catalystadmin@94.72.113.77:/tmp/
```

## Upgrade on the VPS

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes catalystadmin@94.72.113.77

cd /opt/sustainable-catalyst

cp library-backend/.env /tmp/sc-library-backend-v1.0.1.env
cp -a library-backend "library-backend.before-v1.0.1-$(date +%Y%m%d-%H%M%S)"

rm -rf /tmp/sc-library-backend-v1.0.1
mkdir -p /tmp/sc-library-backend-v1.0.1
unzip -q /tmp/sustainable-catalyst-library-backend-v1.0.1.zip \
  -d /tmp/sc-library-backend-v1.0.1

rsync -a --delete \
  --exclude='.env' \
  /tmp/sc-library-backend-v1.0.1/library-backend/ \
  /opt/sustainable-catalyst/library-backend/

cp /tmp/sc-library-backend-v1.0.1.env /opt/sustainable-catalyst/library-backend/.env
chmod 600 /opt/sustainable-catalyst/library-backend/.env

cd /opt/sustainable-catalyst/library-backend

docker compose config --quiet && echo "PASS: compose valid"
docker compose build --pull
docker compose up -d --force-recreate
docker compose ps
```

## Validate

```bash
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/ready | python3 -m json.tool
curl -fsS https://library-api.sustainablecatalyst.com/health | python3 -m json.tool
```

Expected backend version:

```text
1.0.1
```

Expected health capabilities include:

```text
adaptive_ingestion: true
server_chunk_fallback: true
```

`ingest_limits` should also be present.

## Request ceiling

If the current `.env` contains `SC_LIBRARY_MAX_BODY_MB=50`, this upgrade preserves it. v5.5.1 no longer depends on a 50 MB ceiling because WordPress targets 6 MB leaf payloads and splits on 413. After successful production soak you may choose to reduce the server ceiling again, but that is optional and should be done separately.

## WordPress

Install `sustainable-catalyst-library-v5.5.1-wordpress.zip`, retain the existing Backend URL/API key settings, then run **Reindex all published Library records** once to validate adaptive telemetry. A healthy second run should report most records as `unchanged`, zero failed records, and no 413 errors.
