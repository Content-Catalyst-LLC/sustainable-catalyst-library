#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.41.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2410.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.41.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.41.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.41.0"
grep -q 'sc-library-methodology-intelligence/1.0' "$SRC/app/methodology_intelligence.py" || fail "methodology contract missing"
grep -q 'automatic_quality_score' "$SRC/app/methodology_intelligence.py" || fail "methodology quality-score guardrail missing"
grep -q 'sc-library-temporal-knowledge-evolution/1.0' "$SRC/app/temporal_knowledge.py" || fail "v2.40 temporal contract missing"
grep -q 'sc-library-retrieval-evaluation/1.0' "$SRC/app/retrieval_evaluation.py" || fail "v2.39 retrieval contract missing"
grep -q 'sc-library-source-identity-resolution/1.0' "$SRC/app/source_identity_resolution.py" || fail "v2.38 identity contract missing"
grep -q 'sc-library-scientific-document-intelligence/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "v2.37 scientific contract missing"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v2.36 graph contract missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v2.35 synthesis contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.41.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
docker compose config --quiet
docker compose build
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
python3 - "$TMP/health.json" <<'PYHEALTH'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'database':d.get('database'),'methodology':c.get('methodology_intelligence'),'coverage':c.get('methodology_reporting_coverage'),'quality_score':c.get('methodology_automatic_quality_score'),'risk_of_bias':c.get('methodology_automatic_risk_of_bias_judgment'),'temporal':c.get('temporal_knowledge_evolution'),'retrieval':c.get('retrieval_evaluation')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.41.0'
assert c.get('methodology_intelligence') is True
assert c.get('methodology_reporting_coverage') is True
assert c.get('methodology_automatic_quality_score') is False
assert c.get('methodology_automatic_risk_of_bias_judgment') is False
assert c.get('methodology_profile_truth_promotion') is False
assert c.get('temporal_knowledge_evolution') is True
assert c.get('retrieval_evaluation') is True
assert c.get('source_identity_resolution') is True
assert c.get('scientific_document_intelligence') is True
assert c.get('publication_research_graph_query') is True
assert c.get('publication_cross_publication_evidence_synthesis') is True
PYHEALTH
cat > "$TMP/methodology.json" <<'JSONMETHOD'
{"records":[{"record_id":"rct-1","title":"Randomized study","source_key":"validation","metadata":{"methodology":{"study_design":"Randomized controlled trial","population":"Adults","sample_size":240,"geography":"United States","intervention":"Intervention A","comparator":"Usual care","outcomes":["Outcome A"],"statistical_methods":["linear regression"],"confidence_intervals":"95% CI","limitations":["Single region"],"preregistration":"NCT00000000","data_availability":"10.1234/data","code_availability":"https://example.org/code","randomization":"computer-generated"}}},{"record_id":"econ-1","title":"Policy study","metadata":{"study_design":{"design":"Difference-in-differences","population":"Municipalities","sample_size":"n=1,204","analytical_methods":["difference-in-differences","fixed effects"],"uncertainty":["clustered standard errors"]}}}]}
JSONMETHOD
curl -fsS -X POST "$BASE/v1/methodology-intelligence/analyze" -H 'Content-Type: application/json' --data-binary @"$TMP/methodology.json" -o "$TMP/methodology-out.json"
python3 - "$TMP/methodology-out.json" <<'PYMETHOD'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); p={x['record_id']:x for x in d.get('profiles',[])}
print(json.dumps({'schema':d.get('schema'),'profiles':len(p),'designs':d.get('metrics',{}).get('design_family_counts'),'guardrails':d.get('guardrails')},indent=2))
assert d.get('schema')=='sc-library-methodology-intelligence/1.0'
assert p['rct-1']['study_design']['family']=='randomized-interventional'
assert p['econ-1']['study_design']['family']=='quasi-experimental'
assert p['econ-1']['study_context']['sample_size']['value']==1204
assert d.get('guardrails',{}).get('automatic_quality_score') is False
assert d.get('guardrails',{}).get('automatic_risk_of_bias_judgment') is False
assert all(e.get('default_evidence_path') is False for e in d.get('graph_overlay',{}).get('edges',[]))
PYMETHOD
curl -fsS -X POST "$BASE/v1/temporal-knowledge/analyze" -H 'Content-Type: application/json' --data '{"records":[{"record_id":"a","published_at":"2019-01-01","metadata":{"corrected_at":"2021-01-01"}}],"as_of":"2020-01-01","lens":"historical-availability"}' -o "$TMP/temporal.json"
python3 - "$TMP/temporal.json" <<'PYTEMP'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); assert d.get('schema')=='sc-library-temporal-knowledge-evolution/1.0'; assert d.get('snapshot',{}).get('records',[{}])[0].get('status_as_of')=='active'
PYTEMP
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PYCORE'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PYCORE
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=5' -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PYSEARCH'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); assert d.get('schema')=='sc-library-hybrid-retrieval/1.0'
PYSEARCH
echo "PASS: Library backend v2.41.0 Evidence Quality & Methodology Intelligence deployed and verified."
