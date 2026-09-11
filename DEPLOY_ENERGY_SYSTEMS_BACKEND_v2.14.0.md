# Deploy Energy Systems Intelligence v0.8.0 / Library Backend v2.14.0

## Preconditions

- Contabo backend root: `/opt/sustainable-catalyst/library-backend`
- Docker Compose runtime already configured
- deployment account: `catalystadmin`
- backend archive copied to `/tmp/sustainable-catalyst-library-backend-v2.14.0.zip`
- upgrader copied to `/tmp/upgrade_library_backend_v2_14_0_contabo.sh`

No database migration or new secret is required. The existing `.env` is preserved.

## Deploy

```bash
chmod +x /tmp/upgrade_library_backend_v2_14_0_contabo.sh
/tmp/upgrade_library_backend_v2_14_0_contabo.sh
```

The upgrader backs up the current runtime, repairs the shared backup directory if an earlier deployment left it unwritable, installs v2.14.0 while preserving `.env`, rebuilds/restarts Docker, waits for the health check, and verifies the Energy Systems v0.8.0 static contracts.

## Live-source verification

By default, an external World Bank profile smoke test is attempted but is **non-fatal**. A temporary World Bank or network failure should not roll back an otherwise healthy backend. To require that live request to succeed:

```bash
SC_VERIFY_LIVE_GLOBAL_ENERGY=1 /tmp/upgrade_library_backend_v2_14_0_contabo.sh
```

## Manual verification

```bash
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/v1/energy-systems/global-energy-framework | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/v1/energy-systems/global-energy-metrics | python3 -m json.tool
curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/global-energy-country-profile?country=USA&start_year=2020&end_year=2026' | python3 -m json.tool
```

Expected health identity: backend `2.14.0`, Energy Systems domain `0.8.0`, Carbon & Nature `0.5.0`.
