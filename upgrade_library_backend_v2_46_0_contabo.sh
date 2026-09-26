#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.46.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2460.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.46.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.46.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.46.0"
grep -q 'sc-library-native-graph-query/1.0' "$SRC/app/native_graph_runtime.py" || fail "native graph query contract missing"
grep -q 'query_native_graph' "$SRC/app/native_graph_query.py" || fail "native graph query engine missing"
[[ -f "$SRC/native-graph-runtime/Cargo.toml" ]] || fail "Rust native graph crate missing"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust crate is not v0.2.0"
first_line="$(head -n 1 "$SRC/native-graph-runtime/src/main.rs")"
[[ "$first_line" == use\ std::collections* ]] || fail "Rust main.rs source identity is invalid/stale"
grep -q 'connected-components' "$SRC/native-graph-runtime/src/main.rs" || fail "Rust native query operations missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.46.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
docker compose config --quiet
docker compose build --no-cache
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=240; fail "container state $state"; }
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'native_query':c.get('native_graph_query_engine'),'neighborhood':c.get('native_graph_filtered_neighborhoods'),'reachability':c.get('native_graph_reachability'),'components':c.get('native_graph_connected_components'),'subgraph':c.get('native_graph_induced_subgraphs'),'stats':c.get('native_graph_structural_statistics')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.46.0'
for key in ['native_rust_evidence_graph_acceleration','native_graph_query_engine','native_graph_filtered_neighborhoods','native_graph_reachability','native_graph_connected_components','native_graph_induced_subgraphs','native_graph_structural_statistics']:
    assert c.get(key) is True, key
assert c.get('native_graph_analytical_relationships_opt_in') is True
PY
curl -fsS "$BASE/v1/runtime/native-graph/status" -o "$TMP/native.json"
python3 - "$TMP/native.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(d,indent=2)); assert d.get('schema')=='sc-library-native-graph-runtime/1.0'; assert d.get('query_schema')=='sc-library-native-graph-query/1.0'; assert d.get('available') is True; assert d.get('reported_version')=='0.2.0'
PY
cat > "$TMP/native-query.json" <<'JSON'
{"corpus":{"nodes":[{"id":"finding:1","kind":"finding"},{"id":"pub:a","kind":"publication"},{"id":"pub:b","kind":"publication"},{"id":"pub:c","kind":"publication"},{"id":"topic:x","kind":"topic"}],"edges":[{"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":false},{"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":true},{"source":"pub:b","target":"pub:c","relationship_basis":"metadata-association","directed":false},{"source":"pub:c","target":"topic:x","relationship_basis":"publication-topic-cooccurrence","directed":false,"analytical":true}]},"query":{"operation":"neighborhood","start_node_ids":["finding:1"],"max_depth":4,"runtime":"rust"}}
JSON
curl -fsS -X POST "$BASE/v1/runtime/native-graph/query" -H 'Content-Type: application/json' --data-binary @"$TMP/native-query.json" -o "$TMP/native-query-out.json"
python3 - "$TMP/native-query-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':d.get('schema'),'operation':d.get('operation'),'runtime':d.get('runtime'),'metrics':d.get('metrics'),'nodes':[x.get('id') for x in d.get('nodes',[])]},indent=2)); assert d.get('schema')=='sc-library-native-graph-query/1.0'; assert d.get('runtime',{}).get('used')=='rust'; ids={x.get('id') for x in d.get('nodes',[])}; assert {'finding:1','pub:a','pub:b','pub:c'}.issubset(ids); assert 'topic:x' not in ids; assert d.get('interpretation',{}).get('connectivity_implies_evidence_support') is False
PY
python3 - "$TMP/native-query.json" "$TMP/native-stats.json" <<'PY'
import json,sys
p=json.load(open(sys.argv[1],encoding='utf-8')); p['query']={'operation':'structural-stats','runtime':'rust'}; json.dump(p,open(sys.argv[2],'w',encoding='utf-8'))
PY
curl -fsS -X POST "$BASE/v1/runtime/native-graph/query" -H 'Content-Type: application/json' --data-binary @"$TMP/native-stats.json" -o "$TMP/native-stats-out.json"
python3 - "$TMP/native-stats-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'runtime':d.get('runtime'),'stats':d.get('stats')},indent=2)); s=d.get('stats',{}); assert d.get('runtime',{}).get('used')=='rust'; assert s.get('node_count')==5; assert s.get('edge_count')==3; assert s.get('component_count')==2; assert d.get('interpretation',{}).get('degree_or_connectivity_is_quality_score') is False
PY
cat > "$TMP/native-path.json" <<'JSON'
{"corpus":{"nodes":[{"id":"finding:1","kind":"finding"},{"id":"pub:a","kind":"publication"},{"id":"pub:b","kind":"publication"}],"edges":[{"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":false},{"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":true}]},"query":{"start_node_ids":["finding:1"],"target_node_ids":["pub:b"],"runtime":"rust","max_hops":4}}
JSON
curl -fsS -X POST "$BASE/v1/runtime/native-graph/pathfind" -H 'Content-Type: application/json' --data-binary @"$TMP/native-path.json" -o "$TMP/native-path-out.json"
python3 - "$TMP/native-path-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'runtime':d.get('runtime'),'paths':d.get('metrics',{}).get('path_count')},indent=2)); assert d.get('runtime',{}).get('used')=='rust'; assert d.get('paths')
PY
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PY
echo "PASS: Library backend v2.46.0 Rust Evidence Graph Acceleration & Native Query Engine deployed and verified."
