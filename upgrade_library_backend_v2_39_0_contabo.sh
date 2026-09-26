#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.39.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2390.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.39.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.39.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.39.0"
grep -q 'sc-library-retrieval-evaluation/1.0' "$SRC/app/retrieval_evaluation.py" || fail "retrieval evaluation contract missing"
grep -q '"automatic_record_filtering": False' "$SRC/app/retrieval_evaluation.py" || fail "rerank-only guardrail missing"
grep -q '@app.post("/v1/search/adaptive")' "$SRC/app/main.py" || fail "adaptive search route missing"
grep -q 'sc-library-source-identity-resolution/1.0' "$SRC/app/source_identity_resolution.py" || fail "v2.38 source identity contract missing"
grep -q 'sc-library-scientific-document-intelligence/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "v2.37 scientific contract missing"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v2.36 graph contract missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v2.35 synthesis contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.39.0-$stamp.tgz" "$(basename "$ROOT")"; fi
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
python3 - "$TMP/health.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'database':d.get('database'),'retrieval_evaluation':c.get('retrieval_evaluation'),'adaptive_ranking_profiles':c.get('adaptive_ranking_profiles'),'source_identity':c.get('source_identity_resolution'),'scientific_document_intelligence':c.get('scientific_document_intelligence')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.39.0'
assert c.get('retrieval_evaluation') is True
assert c.get('retrieval_precision_recall_ndcg_mrr_map') is True
assert c.get('adaptive_ranking_profiles') is True
assert c.get('adaptive_ranking_bounded_rerank') is True
assert c.get('adaptive_ranking_automatic_filtering') is False
assert c.get('adaptive_ranking_truth_promotion') is False
assert c.get('source_identity_resolution') is True
assert c.get('scientific_document_intelligence') is True
assert c.get('publication_research_graph_query') is True
assert c.get('publication_cross_publication_evidence_synthesis') is True
PY
cat > "$TMP/eval.json" <<'JSON'
{"case_id":"deploy-check","query":"sustainability evidence","known_relevant_ids":["a","c"],"results":[{"record_id":"a","source_key":"one","object_type":"publication","relevance_grade":3,"retrieval_signals":{"lexical_rank":1,"semantic_rank":2},"platform_core":{"bound":true}},{"record_id":"b","source_key":"two","object_type":"publication","relevance_grade":0,"retrieval_signals":{"lexical_rank":2,"semantic_rank":1}},{"record_id":"c","source_key":"one","object_type":"publication","relevance_grade":2,"retrieval_signals":{"lexical_rank":3,"semantic_rank":3}}]}
JSON
curl -fsS -X POST "$BASE/v1/retrieval-evaluation/evaluate" -H 'Content-Type: application/json' --data-binary @"$TMP/eval.json" -o "$TMP/eval-out.json"
curl -fsS -X POST "$BASE/v1/retrieval-evaluation/profile" -H 'Content-Type: application/json' --data-binary @"$TMP/eval.json" -o "$TMP/profile.json"
python3 - "$TMP/eval-out.json" "$TMP/profile.json" <<'PY'
import json,sys
e=json.load(open(sys.argv[1],encoding='utf-8')); p=json.load(open(sys.argv[2],encoding='utf-8'))
case=e.get('cases',[{}])[0]
print(json.dumps({'evaluation_schema':e.get('schema'),'mrr':case.get('metrics',{}).get('mean_reciprocal_rank'),'profile_schema':p.get('schema'),'profile_active':p.get('active'),'weights':p.get('weights'),'guardrails':p.get('guardrails')},indent=2))
assert case.get('metrics',{}).get('mean_reciprocal_rank') == 1.0
assert p.get('schema')=='sc-library-adaptive-ranking-profile/1.0'
assert p.get('active') is True
assert p.get('guardrails',{}).get('automatic_record_filtering') is False
assert p.get('guardrails',{}).get('automatic_truth_promotion') is False
PY
python3 - "$TMP/eval.json" "$TMP/profile.json" "$TMP/rerank.json" <<'PY'
import json,sys
e=json.load(open(sys.argv[1],encoding='utf-8')); p=json.load(open(sys.argv[2],encoding='utf-8'))
json.dump({'results':e['results'],'profile':p},open(sys.argv[3],'w',encoding='utf-8'))
PY
curl -fsS -X POST "$BASE/v1/retrieval-evaluation/rerank" -H 'Content-Type: application/json' --data-binary @"$TMP/rerank.json" -o "$TMP/rerank-out.json"
python3 - "$TMP/rerank-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
assert d.get('schema')=='sc-library-adaptive-reranking/1.0'
assert d.get('result_count')==3
assert {x.get('record_id') for x in d.get('results',[])}=={'a','b','c'}
assert d.get('guardrails',{}).get('result_set_preserved') is True
PY
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=5' -o "$TMP/search.json"
python3 - "$TMP/search.json" "$TMP/profile.json" "$TMP/adaptive.json" <<'PY'
import json,sys
s=json.load(open(sys.argv[1],encoding='utf-8')); p=json.load(open(sys.argv[2],encoding='utf-8'))
assert s.get('schema')=='sc-library-hybrid-retrieval/1.0'
json.dump({'search':{'q':'sustainability','mode':'hybrid','include_core':True,'limit':5},'profile':p},open(sys.argv[3],'w',encoding='utf-8'))
PY
curl -fsS -X POST "$BASE/v1/search/adaptive" -H 'Content-Type: application/json' --data-binary @"$TMP/adaptive.json" -o "$TMP/adaptive-out.json"
python3 - "$TMP/adaptive-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); a=d.get('adaptive_ranking',{})
print(json.dumps({'schema':d.get('schema'),'results':len(d.get('results',[])),'adaptive':a},indent=2))
assert d.get('schema')=='sc-library-hybrid-retrieval/1.0'
assert a.get('result_set_filtered') is False
assert a.get('truth_status_changed') is False
PY
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PY
echo "PASS: Library backend v2.39.0 Research Retrieval Evaluation & Adaptive Ranking deployed and verified."
