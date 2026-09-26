#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.45.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2450.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.45.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.45.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.45.0"
grep -q 'sc-library-living-evidence/1.0' "$SRC/app/living_evidence.py" || fail "living evidence runtime missing"
grep -q 'sc-library-literature-review/1.0' "$SRC/app/literature_review.py" || fail "literature review runtime missing"
grep -q 'sc-library-native-graph-runtime/1.0' "$SRC/app/native_graph_runtime.py" || fail "native graph adapter missing"
[[ -f "$SRC/native-graph-runtime/Cargo.toml" ]] || fail "Rust native graph crate missing"
first_line="$(head -n 1 "$SRC/native-graph-runtime/src/main.rs")"
[[ "$first_line" == use\ std::collections* ]] || fail "Rust main.rs source identity is invalid/stale"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.45.0-$stamp.tgz" "$(basename "$ROOT")"; fi
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
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'living_evidence':c.get('living_evidence_research_evolution'),'review_update_candidates':c.get('living_evidence_review_update_candidates'),'automatic_search':c.get('living_evidence_automatic_search'),'automatic_state_change':c.get('living_evidence_automatic_review_state_change'),'native_rust':c.get('native_rust_graph_runtime_foundation')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.45.0'
assert c.get('living_evidence_research_evolution') is True
assert c.get('living_evidence_review_update_candidates') is True
assert c.get('living_evidence_snapshot_comparison') is True
assert c.get('living_evidence_automatic_search') is False
assert c.get('living_evidence_automatic_review_state_change') is False
assert c.get('living_evidence_newer_evidence_truth_promotion') is False
assert c.get('native_rust_graph_runtime_foundation') is True
PY
curl -fsS "$BASE/v1/runtime/native-graph/status" -o "$TMP/native.json"
python3 - "$TMP/native.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(d,indent=2)); assert d.get('schema')=='sc-library-native-graph-runtime/1.0'; assert d.get('available') is True; assert d.get('reported_version')=='0.1.0'
PY
cat > "$TMP/living.json" <<'JSON'
{
  "baseline_review": {
    "protocol": {"title":"Deployment living review","research_question":"Can living evidence preserve historical state?","inclusion_criteria":["Empirical"],"exclusion_criteria":["Commentary"],"search_strategies":[{"source":"deployment-test","query":"living evidence","executed_at":"2026-09-01"}]},
    "records": [{"record_id":"r1","content_hash":"a","published_at":"2025-01-01","publication_status":"published"}],
    "decisions": [{"record_id":"r1","stage":"full-text","decision":"include","reviewer":"deployment-test"}],
    "extractions": [{"record_id":"r1","fields":{"effect":"old"},"extractor":"deployment-test"}]
  },
  "current_review": {
    "protocol": {"title":"Deployment living review","research_question":"Can living evidence preserve historical state?","inclusion_criteria":["Empirical"],"exclusion_criteria":["Commentary"],"search_strategies":[{"source":"deployment-test","query":"living evidence","executed_at":"2026-09-26"}]},
    "records": [{"record_id":"r1","content_hash":"b","published_at":"2025-01-01","publication_status":"corrected"},{"record_id":"r2","content_hash":"c","published_at":"2026-05-01","publication_status":"published"}],
    "decisions": [{"record_id":"r1","stage":"full-text","decision":"include","reviewer":"deployment-test"}],
    "extractions": [{"record_id":"r1","fields":{"effect":"updated"},"extractor":"deployment-test"}]
  },
  "events": [{"record_id":"r1","event_type":"corrected","occurred_at":"2026-09-20","source":"deployment-test"}]
}
JSON
curl -fsS -X POST "$BASE/v1/living-evidence/analyze" -H 'Content-Type: application/json' --data-binary @"$TMP/living.json" -o "$TMP/living-out.json"
python3 - "$TMP/living-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':d.get('schema'),'id':d.get('living_evidence_id'),'metrics':d.get('metrics'),'guardrails':d.get('guardrails')},indent=2))
assert d.get('schema')=='sc-library-living-evidence/1.0'
kinds={c.get('kind') for c in d.get('update_candidates',[])}
assert 'new-record' in kinds and 'source-record-changed' in kinds and 'explicit-source-status-change' in kinds and 'extraction-changed' in kinds
assert d.get('guardrails',{}).get('new_record_is_automatically_included') is False
assert d.get('guardrails',{}).get('changed_record_invalidates_prior_review') is False
assert d.get('guardrails',{}).get('living_review_implies_continuous_automatic_search') is False
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
echo "PASS: Library backend v2.45.0 Living Evidence & Research Evolution deployed and verified."
