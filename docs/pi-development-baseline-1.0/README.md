# Raspberry Pi Development Baseline 1.0 — Closeout and recovery runbook

**Scope:** Isolated Raspberry Pi local development, **not** the production Sustainable Catalyst deployment. As of 2026-10-27 the Workbench and read-only Knowledge Library scope passed technical and browser acceptance, while a full clean-machine restoration drill is still **not performed**.

## Source of truth

- Workbench: `Content-Catalyst-LLC/sustainable-catalyst-workbench`, branch `pi/baseline-1.0-workbench`, commits `3e70601` (v540 numerics) and `da87aca` (Graph Studio handler/test).
- Library: `Content-Catalyst-LLC/sustainable-catalyst-library`, branch `pi/baseline-1.0-library` (this change); v7.0.0 baseline `81c3ad7` at initial audit.
- Lab: `sustainable-catalyst-lab` existing source branch `codex/pi-development-activation`, last audited commit `3599db9`. No new source fix required. Installed Lab was restored from a hash-verified repository file; 1,682/1,682 checked.
- Reproducible, **non-production** installer/source references: `dev/pi-baseline-1.0/reference/` in this Library branch. They contain no generated `.env`, secrets, database, indexed records, or users.
- Exact vetted Pi development Compose source manifests: `dev/pi-baseline-1.0/runtime-manifests/`. Restore to the original SSD locations (not as a production Compose deployment) after verifying their current compatibility.
- Site WordPress safety: the Pi MU-plugin's HTTP block / narrow allowlist must remain active. Do not copy a running WordPress database to GitHub.

## Persistent SSD storage, local-only

- Workbench managed Compose: `/mnt/catalyst-ssd/workbench-dev/optimized-standalone/compose.yaml` (project `sc-workbench-pi-dev`, bind data to `/data`).
- Library independent Compose, private Postgres, adapter: `/mnt/catalyst-ssd/library-dev/pi-baseline-1/` (project `sc-library-pi-dev`); secrets in local `.env`, **not in Git**.
- WordPress development Compose: `/mnt/catalyst-ssd/wordpress-dev/site/` and external private `sc-internal` network; keep the existing egress/email guard.
- Private evidence and local pre-sanitization example backup: `/mnt/catalyst-ssd/pi-baseline-1.0-closeout/` (not Git).
- Mac access through an SSH local forward from `localhost:8081` to Pi `127.0.0.1:8080`, not public host ports.

## Tested acceptance scope

1. Workbench Graph Mathematics v540 persisted across Compose recreation, with code hash matching running container; Graph Studio's 4 regression tests passed; prior local multi-graph browser rendering verified.
2. Library v7 independent backend + PostgreSQL + private read-only adapter healthy; WordPress admin search for `carbon` returned indexed source records.
3. Lab release `0.163.1.3` integrity repaired by replacing only one file from verified repository source, with original private backup. Release manifest and 1,682 files now verify.
4. Anonymous Library WordPress search rejected; adapter allows fixed internal GET health/search, denies unsupported routes and POST, and publishes no host port.
5. Workspace and Site Intelligence were previously reachable in local development but their complete browser workflow and persistence are **not part of Baseline 1.0 certification**.

## Validation (no restart / data modification)

```bash
cd /mnt/catalyst-ssd/workbench-dev/optimized-standalone
sudo docker compose -p sc-workbench-pi-dev -f compose.yaml config --quiet
cd /mnt/catalyst-ssd/library-dev/pi-baseline-1
sudo docker compose -p sc-library-pi-dev -f compose.yaml config --quiet
curl -fsS http://127.0.0.1:8088/v540/status >/dev/null
curl -fsS http://127.0.0.1:8080/wp-json/sc-dev/v1/library/status >/dev/null
curl -fsS http://127.0.0.1:8080/wp-json/sc-lab/v1/runtime/health | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("ok") is True'
```

The packaged baseline `baseline.sh verify` and closeout security checks must also pass. DO NOT rerun `baseline.sh run`: that would recreate services unnecessarily. The full Lab health JSON is large; parse and retain only the summary.

## Recovery order (controlled, no automatic production promotion)

1. Confirm `/mnt/catalyst-ssd` mounted and recover the repositories at their pinned branches/commits; verify checksums and Compose manifests. Keep existing Docker data and volumes untouched until backups are checked.
2. Recover the local WordPress dev custom-code mirror/MU-plugins and `sc-internal` Compose network; restore the exact scoped allowlist rather than disable egress blocking.
3. Restore **separately safeguarded** Library local `.env` values (DB password / API token). Generate fresh dev-only secrets if restoring from scratch; do not reuse live production credentials.
4. For existing data, restore from consistent Postgres/Workbench backups according to the respective services' disaster recovery procedures. A Git checkout alone will **not** restore databases or research objects.
5. Validate Compose files with `config --quiet`; launch only the specific isolated dev services if a recovery is actually required. Verify health, read-only search and anonymous denial before browser testing.
6. Recheck Workbench calculations/Graph Studio and WordPress Library administrator search; record new certification evidence.

**Rollback:** For the Library-only service, consult `dev/pi-baseline-1.0/reference/pi_baseline_1/rollback-library.sh` before use; it stops the isolated Library project and retains data. Do not execute it during the healthy closeout. For the one-file Lab repair, the installed original backup was recorded at `/mnt/catalyst-ssd/wordpress-dev/site/.sc-dev-repair-backups/lab-one-file-20261027T162051Z/class-sc-lab-instrumentation-production-v0251.php.original`; do **not** restore a mismatched original unless a new integrity issue justifies rollback.

## Boundaries and gaps

- **No production deployment, merge, database migration, live credentials, external internet exposure or authoring API enabled.**
- The full Library interface, private user accounts, document ingest, embedding/federation and Workspace research-object persistence remain future projects.
- End-to-end **clean-machine recovery and multi-service restore tests were not run** by this closeout (they can be planned separately). Preserve verified source and manifests before any additional development.
