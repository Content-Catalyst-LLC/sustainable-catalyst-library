# Deploy Library Backend v1.0.0 on the Sustainable Catalyst VPS

This assumes the existing PostgreSQL container is named `sc-postgres` and is attached to the external Docker network `sc-internal`.

## 1. Create database and database user

Run on the VPS. Replace both placeholder passwords before executing.

```bash
cd /opt/sustainable-catalyst

sudo docker exec -it sc-postgres psql -U postgres <<'SQL'
CREATE ROLE sc_library LOGIN PASSWORD 'REPLACE_WITH_A_LONG_DATABASE_PASSWORD';
CREATE DATABASE sc_library OWNER sc_library;
\c sc_library
GRANT ALL ON SCHEMA public TO sc_library;
SQL
```

## 2. Install backend files

Copy the `library-backend` directory from this release to:

```text
/opt/sustainable-catalyst/library-backend
```

Then:

```bash
cd /opt/sustainable-catalyst/library-backend
cp .env.example .env

python3 - <<'PY'
import secrets
print(secrets.token_urlsafe(48))
PY

nano .env
```

Set `DATABASE_URL` using the database password and put the generated token into `SC_LIBRARY_BACKEND_API_KEY`.

## 3. Start the service

```bash
cd /opt/sustainable-catalyst/library-backend
docker compose build --pull
docker compose up -d
docker compose ps
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/ready | python3 -m json.tool
```

## 4. Reverse proxy

Expose the local port through the existing reverse proxy as:

```text
https://library-api.sustainablecatalyst.com
```

Proxy to:

```text
http://127.0.0.1:8087
```

Do not expose port `8087` directly to the public internet.

## 5. Configure WordPress

In WordPress go to:

**SC Library → Python Backend**

Set the backend URL and the same `SC_LIBRARY_BACKEND_API_KEY`, save, confirm the health payload reports `ok: true`, then run **Reindex all published Library records**.

## 6. Verify public search

```bash
curl -fsS 'https://library-api.sustainablecatalyst.com/v1/search?q=sustainability&limit=5' | python3 -m json.tool
```
