#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.23.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2230.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.23.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.23.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.23.0"
grep -q 'library_core_sync_outbox' "$SRC/app/schema.sql" || fail "Core outbox schema missing"
grep -q 'CAPABILITY_PROBES' "$SRC/app/platform_core.py" || fail "Platform Core bridge missing"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.23.0-$stamp.tgz" "$(basename "$ROOT")"
fi

ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
[[ -f "$ROOT/.env" ]] || { cp "$SRC/.env.example" "$ROOT/.env"; chmod 600 "$ROOT/.env"; }

upsert_env(){
  local key="$1" value="$2"
  python3 - "$ROOT/.env" "$key" "$value" <<'PY'
from pathlib import Path
import sys
path=Path(sys.argv[1]); key=sys.argv[2]; value=sys.argv[3]
lines=path.read_text().splitlines() if path.exists() else []
out=[]; replaced=False
for line in lines:
    if line.startswith(key+'='):
        out.append(f'{key}={value}'); replaced=True
    else:
        out.append(line)
if not replaced: out.append(f'{key}={value}')
path.write_text('\n'.join(out).rstrip()+'\n')
PY
}

upsert_env SC_LIBRARY_PLATFORM_CORE_URL "https://core.sustainablecatalyst.com"
if ! grep -q '^SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS=' "$ROOT/.env"; then upsert_env SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS "8"; fi
if ! grep -q '^SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS=' "$ROOT/.env"; then upsert_env SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS "5"; fi

LIB_CORE_KEY="$(grep -m1 '^SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY=' "$ROOT/.env" | cut -d= -f2- || true)"
if [[ -z "$LIB_CORE_KEY" ]] && docker ps --format '{{.Names}}' | grep -qx sc-core; then
  CORE_KEY="$(docker exec sc-core sh -lc 'printf %s "$SC_CORE_WRITE_API_KEY"' 2>/dev/null || true)"
  if [[ -n "$CORE_KEY" ]]; then
    upsert_env SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY "$CORE_KEY"
    unset CORE_KEY
  fi
fi
LIB_CORE_KEY="$(grep -m1 '^SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY=' "$ROOT/.env" | cut -d= -f2- || true)"
[[ -n "$LIB_CORE_KEY" ]] || fail "Platform Core write key unavailable. Set SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY in $ROOT/.env to the same server credential used by SC_CORE_WRITE_API_KEY, then rerun."
unset LIB_CORE_KEY
chmod 600 "$ROOT/.env"

cd "$ROOT"
echo "=== BUILD / RECREATE LIBRARY BACKEND ==="
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  if [[ "$state" =~ ^(unhealthy|exited|dead)$ ]]; then docker compose logs --tail=180; fail "sc-library-backend entered state $state"; fi
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || { docker compose logs --tail=180; fail "sc-library-backend did not become healthy"; }

BASE=http://127.0.0.1:8087
health="$(curl -fsS "$BASE/health")"
printf '%s\n' "$health" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.23.0",d; c=d.get("capabilities",{}); assert c.get("platform_core_research_bridge") is True; assert c.get("platform_core_governed_promotion") is True; assert c.get("platform_core_idempotent_outbox") is True; assert c.get("platform_core_durable_bindings") is True; assert c.get("platform_core_automatic_truth_promotion") is False' "$health"

bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("configured") is True,d; assert d.get("reachable") is True,d; assert d.get("capability_count")==8,d; assert d.get("ready_capability_count")==8,d; p=d.get("promotion_policy",{}); assert p.get("raw_chunks_remain_library_local") is True; assert p.get("automatic_truth_promotion") is False; assert p.get("explicit_governed_promotion_required") is True' "$bridge"

capabilities="$(curl -fsS "$BASE/v1/platform-core/capabilities")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("core_version"),d; assert all(v.get("available") is True for v in d.get("capabilities",{}).values()),d' "$capabilities"

echo "=== VERIFYING ADDITIVE CORE BRIDGE TABLES ==="
docker exec -i sc-library-backend python - <<'PY'
import os
import psycopg
url=os.environ['DATABASE_URL']
with psycopg.connect(url) as conn, conn.cursor() as cur:
    for table in ('library_core_bindings','library_core_sync_outbox'):
        cur.execute('SELECT to_regclass(%s)',(f'public.{table}',))
        value=cur.fetchone()[0]
        assert value==table,(table,value)
print('PASS: Core bridge tables present')
PY

docker exec sc-library-backend sh -lc '[ -n "$SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY" ]' || fail "Core write key is not present inside Library backend container"

echo "PASS: Library backend v2.23.0 Platform Core Research Bridge deployed and Core v3.3+ capability surface is reachable."
