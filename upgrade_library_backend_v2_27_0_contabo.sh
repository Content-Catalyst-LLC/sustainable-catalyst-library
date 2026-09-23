#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.27.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2270.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.27.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.27.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.27.0"
grep -q 'CREATE TABLE IF NOT EXISTS library_publication_visualizations' "$SRC/app/schema.sql" || fail "publication visualization schema missing"
grep -q 'visual-research-object.create' "$SRC/app/platform_core.py" || fail "Core visual research operation missing"
grep -q '@app.get("/v1/publication-visualizations/readiness")' "$SRC/app/main.py" || fail "visualization readiness route missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.27.0-$stamp.tgz" "$(basename "$ROOT")"
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
printf '%s\n' "$health" | python3 -m json.tool | head -220
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.27.0",d; c=d.get("capabilities",{}); assert c.get("publication_visualizations") is True; assert c.get("publication_visualization_renderer_neutral_specs") is True; assert c.get("publication_visualization_human_review_gate") is True; assert c.get("platform_core_visual_research_handoff") is True; assert c.get("automatic_visual_truth_promotion") is False' "$health"

echo "=== PUBLICATION VISUALIZATION READINESS ==="
visual="$(curl -fsS "$BASE/v1/publication-visualizations/readiness")"
printf '%s\n' "$visual" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-publication-visualization-readiness/1.0",d; assert d.get("storage_ready") is True,d; assert d.get("publication_visualizations") is True; assert d.get("renderer_neutral_specs") is True; assert d.get("human_review_required") is True; assert d.get("research_library_delivery") is True; assert set(d.get("visualization_kinds",[]))=={"citation-network","concept-map","finding-map","claim-map"}; b=d.get("boundaries",{}); assert b.get("automatic_truth_promotion") is False; assert b.get("machine_inferred_graph_edges") is False' "$visual"

echo "=== VERIFYING VISUALIZATION TABLE ==="
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
p=get_pool()
with p.connection() as c, c.cursor() as cur:
    cur.execute("SELECT count(*) AS n FROM library_publication_visualizations")
    print("PASS: library_publication_visualizations rows=", cur.fetchone()["n"])
PY

echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d; ops=set(d.get("operations",[])); assert "visual-research-object.create" in ops; assert "research-finding.create" in ops; assert "research-claim.create" in ops' "$bridge"

echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' "$search"
record_id="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); r=d.get("results",[]); print(r[0].get("record_id","") if r else "")' "$search")"

echo "=== CITATION GRAPH ROUTE REGRESSION TEST ==="
if [[ -n "$record_id" ]]; then
  graph="$(curl -fsS --get "$BASE/v1/citations/$record_id/graph" --data-urlencode 'depth=1' --data-urlencode 'limit=25' --data-urlencode 'include_core=true')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); expected=sys.argv[2]; assert d.get("schema")=="sc-library-citation-graph/1.0",d; assert d.get("root_record_id")==expected,(expected,d)' "$graph" "$record_id"
  echo "PASS: citation graph preserved record_id=$record_id"
  public_visuals="$(curl -fsS --get "$BASE/v1/publication-visualizations" --data-urlencode "record_id=$record_id" --data-urlencode 'limit=8')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-publication-visualization/1.0",d; assert isinstance(d.get("items"),list),d; assert all(x.get("review_state")=="published" for x in d.get("items",[]))' "$public_visuals"
  echo "PASS: public visualization delivery contract"
else
  echo "SKIP: no public search record available for citation/visualization delivery smoke tests"
fi

echo "PASS: Library backend v2.27.0 Publication Visualization Foundations deployed."
