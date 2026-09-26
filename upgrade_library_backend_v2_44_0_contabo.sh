#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.44.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2440.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.44.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.44.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.44.0"
grep -q 'sc-library-literature-review/1.0' "$SRC/app/literature_review.py" || fail "literature review runtime missing"
grep -q 'sc-library-native-graph-runtime/1.0' "$SRC/app/native_graph_runtime.py" || fail "native graph adapter missing"
[[ -f "$SRC/native-graph-runtime/Cargo.toml" ]] || fail "Rust native graph crate missing"
first_line="$(head -n 1 "$SRC/native-graph-runtime/src/main.rs")"
[[ "$first_line" == use\ std::collections* ]] || fail "Rust main.rs source identity is invalid/stale"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.44.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
docker compose config --quiet
docker compose build --no-cache
docker compose up -d --force-recreate
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
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'literature_review':c.get('reproducible_literature_review_engine'),'native_rust':c.get('native_rust_graph_runtime_foundation'),'automatic_screening':c.get('literature_review_automatic_screening')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.44.0'
assert c.get('reproducible_literature_review_engine') is True
assert c.get('literature_review_human_screening_decisions') is True
assert c.get('literature_review_automatic_screening') is False
assert c.get('literature_review_automatic_inclusion_exclusion') is False
assert c.get('literature_review_automatic_meta_analysis') is False
assert c.get('native_rust_graph_runtime_foundation') is True
PY
curl -fsS "$BASE/v1/runtime/native-graph/status" -o "$TMP/native.json"
python3 - "$TMP/native.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(d,indent=2)); assert d.get('schema')=='sc-library-native-graph-runtime/1.0'; assert d.get('available') is True; assert d.get('reported_version')=='0.1.0'
PY
cat > "$TMP/review.json" <<'JSON'
{"protocol":{"title":"Deployment review","research_question":"Does the review engine preserve explicit human screening?","inclusion_criteria":["Empirical"],"exclusion_criteria":["Commentary"],"search_strategies":[{"source":"deployment-test","query":"review engine","executed_at":"2026-09-26"}]},"records":[{"record_id":"r1"},{"record_id":"r2"}],"decisions":[{"record_id":"r1","stage":"full-text","decision":"include","reviewer":"deployment-test"},{"record_id":"r2","stage":"title-abstract","decision":"exclude","reason":"Commentary","reviewer":"deployment-test"}],"extractions":[{"record_id":"r1","fields":{"design":"test"},"extractor":"deployment-test"}]}
JSON
curl -fsS -X POST "$BASE/v1/literature-reviews/build" -H 'Content-Type: application/json' --data-binary @"$TMP/review.json" -o "$TMP/review-out.json"
python3 - "$TMP/review-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':d.get('schema'),'review_id':d.get('review_id'),'flow':d.get('flow'),'guardrails':d.get('guardrails')},indent=2)); assert d.get('schema')=='sc-library-literature-review/1.0'; assert d.get('sets',{}).get('included_record_ids')==['r1']; assert d.get('sets',{}).get('excluded_record_ids')==['r2']; assert d.get('guardrails',{}).get('automatic_screening_decisions') is False; assert d.get('guardrails',{}).get('automatic_meta_analysis') is False
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
echo "PASS: Library backend v2.44.0 Reproducible Literature Review Engine deployed and verified."
