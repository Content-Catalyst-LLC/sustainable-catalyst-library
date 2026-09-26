#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.43.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2430.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.43.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.43.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.43.0"
grep -q 'sc-library-native-graph-runtime/1.0' "$SRC/app/native_graph_runtime.py" || fail "native graph adapter contract missing"
[[ -f "$SRC/native-graph-runtime/Cargo.toml" ]] || fail "Rust native graph crate missing"
grep -q 'sc-library-research-gap-novelty/1.0' "$SRC/app/research_gap_novelty.py" || fail "v2.42 gap/novelty contract missing"
grep -q 'sc-library-methodology-intelligence/1.0' "$SRC/app/methodology_intelligence.py" || fail "v2.41 methodology contract missing"
grep -q 'sc-library-temporal-knowledge-evolution/1.0' "$SRC/app/temporal_knowledge.py" || fail "v2.40 temporal contract missing"
grep -q 'sc-library-retrieval-evaluation/1.0' "$SRC/app/retrieval_evaluation.py" || fail "v2.39 retrieval contract missing"
grep -q 'sc-library-source-identity-resolution/1.0' "$SRC/app/source_identity_resolution.py" || fail "v2.38 identity contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.43.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config --quiet; docker compose build; docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'gap_novelty':c.get('research_gap_novelty_discovery'),'novelty_claim':c.get('research_novelty_automatic_claim'),'methodology':c.get('methodology_intelligence'),'temporal':c.get('temporal_knowledge_evolution')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.43.0'
assert c.get('native_rust_graph_runtime_foundation') is True
assert c.get('native_graph_runtime_python_fallback') is True
assert c.get('research_gap_novelty_discovery') is True
assert c.get('research_novelty_automatic_claim') is False
assert c.get('research_gap_global_absence_claim') is False
assert c.get('methodology_intelligence') is True
assert c.get('temporal_knowledge_evolution') is True
PY
curl -fsS "$BASE/v1/runtime/native-graph/status" -o "$TMP/native.json"
python3 - "$TMP/native.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(d,indent=2)); assert d.get('schema')=='sc-library-native-graph-runtime/1.0'; assert d.get('available') is True; assert d.get('reported_version')=='0.1.0'; assert d.get('native_runtime_changes_research_semantics') is False
PY
cat > "$TMP/native-path.json" <<'JSON'
{"corpus":{"nodes":[{"id":"finding:1","kind":"finding"},{"id":"pub:a","kind":"publication"},{"id":"pub:b","kind":"publication"}],"edges":[{"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":false},{"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":true}]},"query":{"start_node_ids":["finding:1"],"target_node_ids":["pub:b"],"runtime":"rust","max_hops":4}}
JSON
curl -fsS -X POST "$BASE/v1/runtime/native-graph/pathfind" -H 'Content-Type: application/json' --data-binary @"$TMP/native-path.json" -o "$TMP/native-path-out.json"
python3 - "$TMP/native-path-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'runtime':d.get('runtime'),'paths':d.get('metrics',{}).get('path_count')},indent=2)); assert d.get('runtime',{}).get('used')=='rust'; assert d.get('paths'); assert d.get('interpretation',{}).get('native_runtime_changes_research_semantics') is False
PY
cat > "$TMP/gap.json" <<'JSON'
{"records":[{"record_id":"r1","publication_year":2026,"topics":["Topic A","Topic B"],"metadata":{}},{"record_id":"r2","publication_year":2017,"topics":["Legacy Topic"],"metadata":{}},{"record_id":"r3","publication_year":2016,"topics":["Legacy Topic"],"metadata":{}}],"nodes":[{"id":"claim-1","kind":"claim","label":"Claim 1","record_id":"r1"}],"edges":[]}
JSON
curl -fsS -X POST "$BASE/v1/research-gap-novelty/analyze" -H 'Content-Type: application/json' --data-binary @"$TMP/gap.json" -o "$TMP/gap-out.json"
python3 - "$TMP/gap-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':d.get('schema'),'gaps':d.get('metrics',{}).get('gap_candidate_count'),'novelty':d.get('metrics',{}).get('novelty_candidate_count'),'guardrails':d.get('guardrails')},indent=2))
assert d.get('schema')=='sc-library-research-gap-novelty/1.0'
assert d.get('guardrails',{}).get('gap_signal_proves_global_absence') is False
assert d.get('guardrails',{}).get('novelty_candidate_is_novelty_claim') is False
assert all(e.get('default_evidence_path') is False for e in d.get('graph_overlay',{}).get('edges',[]))
PY
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PY
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=5' -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); assert d.get('schema')=='sc-library-hybrid-retrieval/1.0'
PY
echo "PASS: Library backend v2.43.0 Rust Research Graph Runtime Foundation deployed and verified."
