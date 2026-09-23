#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.25.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2250.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.25.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.25.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.25.0"
grep -q 'CREATE TABLE IF NOT EXISTS library_citations' "$SRC/app/schema.sql" || fail "citation schema missing"
grep -q 'scholarly-citation.create' "$SRC/app/platform_core.py" || fail "Core citation handoff missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.25.0-$stamp.tgz" "$(basename "$ROOT")"
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
printf '%s\n' "$health" | python3 -m json.tool | head -180
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.25.0",d; c=d.get("capabilities",{}); assert c.get("hybrid_retrieval") is True; assert c.get("citation_graph") is True; assert c.get("scholarly_lineage") is True; assert c.get("platform_core_scholarly_citation_handoff") is True; assert c.get("automatic_citation_inference") is False' "$health"
echo "=== CITATION GRAPH READINESS ==="
citations="$(curl -fsS "$BASE/v1/citations/readiness")"
printf '%s\n' "$citations" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("citation_graph") is True,d; assert d.get("exact_identifier_resolution") is True,d; assert d.get("unresolved_reference_preservation") is True,d; assert d.get("platform_core_scholarly_citation_handoff") is True,d; assert d.get("automatic_citation_inference") is False,d' "$citations"
echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d' "$bridge"
echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
printf '%s\n' "$search" | python3 -m json.tool | head -160
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' "$search"
echo "=== CITATION GRAPH ROUTE SMOKE TEST ==="
record_id="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); r=d.get("results",[]); print(r[0].get("record_id","") if r else "")' "$search")"
if [[ -n "$record_id" ]]; then
  graph="$(curl -fsS --get "$BASE/v1/citations/$record_id/graph" --data-urlencode 'depth=1' --data-urlencode 'limit=25' --data-urlencode 'include_core=true')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-citation-graph/1.0",d; assert d.get("root_record_id"),d' "$graph"
  echo "Citation graph smoke test passed for $record_id"
else
  echo "SKIP: no public search record available for citation graph route smoke test"
fi
echo "PASS: Library backend v2.25.0 Citation Graph & Scholarly Lineage deployed."
