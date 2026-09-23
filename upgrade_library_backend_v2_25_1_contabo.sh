#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.25.1.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2251.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.25.1"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.25.1"' "$SRC/app/__init__.py" || fail "payload is not backend v2.25.1"
python3 - <<'PY' "$SRC/app/main.py"
from pathlib import Path
import sys
s=Path(sys.argv[1]).read_text()
assert s.index('@app.get("/v1/citations/{record_id:path}/graph")') < s.index('@app.get("/v1/citations/{record_id:path}")')
PY
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.25.1-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
echo "=== BUILD / RECREATE LIBRARY BACKEND ==="
docker compose config --quiet
docker compose build
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  if [[ "$state" =~ ^(unhealthy|exited|dead)$ ]]; then docker compose logs --tail=180; fail "sc-library-backend entered state $state"; fi
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || { docker compose logs --tail=180; fail "sc-library-backend did not become healthy"; }
BASE=http://127.0.0.1:8087
echo "=== LIBRARY HEALTH ==="
health="$(curl -fsS "$BASE/health")"
printf '%s\n' "$health" | python3 -m json.tool | head -140
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.25.1",d; c=d.get("capabilities",{}); assert c.get("citation_graph") is True; assert c.get("scholarly_lineage") is True; assert c.get("platform_core_research_bridge") is True' "$health"

echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d' "$bridge"

echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' "$search"
record_id="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); r=d.get("results",[]); print(r[0].get("record_id","") if r else "")' "$search")"

echo "=== CITATION GRAPH ROUTE REGRESSION TEST ==="
if [[ -n "$record_id" ]]; then
  set +e
  raw="$(curl -sS --get "$BASE/v1/citations/$record_id/graph" --data-urlencode 'depth=1' --data-urlencode 'limit=25' --data-urlencode 'include_core=true' -w $'\n%{http_code}')"
  rc=$?
  set -e
  body="${raw%$'\n'*}"
  code="${raw##*$'\n'}"
  if [[ $rc -ne 0 || "$code" != "200" ]]; then
    echo "Citation graph HTTP status: $code"
    printf '%s\n' "$body"
    echo "=== RECENT LIBRARY BACKEND LOGS ==="
    docker compose logs --tail=180 library-backend || docker logs --tail=180 sc-library-backend || true
    fail "citation graph route regression test failed"
  fi
  printf '%s\n' "$body" | python3 -m json.tool | head -160
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); expected=sys.argv[2]; assert d.get("schema")=="sc-library-citation-graph/1.0",d; assert d.get("root_record_id")==expected,(expected,d); assert not str(d.get("root_record_id","")).endswith("/graph"),d' "$body" "$record_id"
  echo "PASS: citation graph preserved record_id=$record_id"
else
  echo "SKIP: no public search record available for citation graph route regression test"
fi

echo "PASS: Library backend v2.25.1 Citation Graph Route Precedence Repair deployed."
