#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.28.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2280.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.28.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.28.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.28.0"
grep -q 'KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-knowledge-map/1.0"' "$SRC/app/publication_knowledge_maps.py" || fail "knowledge map engine missing"
grep -q '@app.get("/v1/publication-knowledge-maps/readiness")' "$SRC/app/main.py" || fail "knowledge map readiness route missing"
grep -q '@app.get("/v1/publication-knowledge-maps")' "$SRC/app/main.py" || fail "knowledge map route missing"
grep -q 'visual-research-object.create' "$SRC/app/platform_core.py" || fail "Core visual research operation missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.28.0-$stamp.tgz" "$(basename "$ROOT")"
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
  if [[ "$state" =~ ^(unhealthy|exited|dead)$ ]]; then docker compose logs --tail=220; fail "sc-library-backend entered state $state"; fi
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || { docker compose logs --tail=220; fail "sc-library-backend did not become healthy"; }
BASE=http://127.0.0.1:8087

echo "=== LIBRARY HEALTH ==="
health="$(curl -fsS "$BASE/health")"
printf '%s\n' "$health" | python3 -m json.tool | head -240
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.28.0",d; c=d.get("capabilities",{}); assert c.get("publication_knowledge_mapping") is True; assert c.get("publication_scientific_graphical_analysis") is True; assert c.get("publication_topic_relationship_mapping") is True; assert c.get("publication_semantic_similarity")=="stored-embeddings-only"; assert c.get("publication_knowledge_map_workspace_portable") is True; assert c.get("automatic_visual_truth_promotion") is False' "$health"

echo "=== SCIENTIFIC KNOWLEDGE MAP READINESS ==="
ready="$(curl -fsS "$BASE/v1/publication-knowledge-maps/readiness")"
printf '%s\n' "$ready" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-publication-knowledge-map-readiness/1.0",d; assert d.get("publication_knowledge_mapping") is True; assert d.get("scientific_graphical_analysis") is True; assert d.get("source_anchored_topic_relationships") is True; assert d.get("semantic_similarity_from_real_embeddings_only") is True; assert d.get("interactive_research_library_renderer") is True; assert d.get("workspace_portable_contract") is True; assert d.get("platform_core_visual_runtime_alignment") is True; assert d.get("storage_ready") is True,d; b=d.get("boundaries",{}); assert b.get("llm_inferred_edges") is False; assert b.get("automatic_truth_promotion") is False; assert b.get("semantic_edges_are_truth_claims") is False' "$ready"

echo "=== PUBLICATION VISUALIZATION FOUNDATION ==="
visual="$(curl -fsS "$BASE/v1/publication-visualizations/readiness")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-publication-visualization-readiness/1.0",d; assert d.get("storage_ready") is True,d; assert d.get("human_review_required") is True,d' "$visual"

echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d; ops=set(d.get("operations",[])); assert "visual-research-object.create" in ops; assert "research-finding.create" in ops; assert "research-claim.create" in ops' "$bridge"

echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' "$search"
record_id="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); r=d.get("results",[]); print(r[0].get("record_id","") if r else "")' "$search")"

if [[ -n "$record_id" ]]; then
  echo "=== KNOWLEDGE MAP LIVE CONTRACT ==="
  map="$(curl -fsS --get "$BASE/v1/publication-knowledge-maps" --data-urlencode "record_id=$record_id" --data-urlencode 'include_citations=true' --data-urlencode 'include_semantic_similarity=true' --data-urlencode 'semantic_threshold=0.72' --data-urlencode 'max_neighbors=25')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); expected=sys.argv[2]; assert d.get("schema")=="sc-library-publication-knowledge-map/1.0",d; assert d.get("record_id")==expected,(expected,d); assert isinstance(d.get("nodes"),list); assert isinstance(d.get("edges"),list); assert d.get("boundaries",{}).get("llm_inferred_edges") is False; assert d.get("boundaries",{}).get("semantic_edges_are_truth_claims") is False; s=d.get("semantic_analysis",{}); assert "available" in s; assert "threshold" in s' "$map" "$record_id"
  echo "PASS: scientific knowledge map record_id=$record_id"

  echo "=== CITATION GRAPH ROUTE REGRESSION ==="
  graph="$(curl -fsS --get "$BASE/v1/citations/$record_id/graph" --data-urlencode 'depth=1' --data-urlencode 'limit=25' --data-urlencode 'include_core=true')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); expected=sys.argv[2]; assert d.get("schema")=="sc-library-citation-graph/1.0",d; assert d.get("root_record_id")==expected,(expected,d)' "$graph" "$record_id"
else
  echo "SKIP: no public search record available for live knowledge-map/citation smoke tests"
fi

echo "PASS: Library backend v2.28.0 Scientific Knowledge Mapping & Interactive Semantic Analysis deployed."
