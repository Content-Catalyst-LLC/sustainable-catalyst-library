#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.53.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2530.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.53.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.53.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.53.0"
[[ -f "$SRC/app/neural_reranking.py" ]] || fail "neural reranking module missing"
grep -q 'sc-library-neural-reranking/1.0' "$SRC/app/neural_reranking.py" || fail "neural reranking contract missing"
grep -q 'SC_LIBRARY_RERANK_PROVIDER=disabled' "$SRC/.env.example" || fail "reranker configuration example missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.53.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
if grep -q '^SC_LIBRARY_RERANK_PROVIDER=' .env 2>/dev/null; then
  grep '^SC_LIBRARY_RERANK_PROVIDER=' .env
else
  echo "SC_LIBRARY_RERANK_PROVIDER is unset -> backend default is disabled (safe baseline mode)"
fi
if grep -q '^SC_LIBRARY_RERANK_MAX_CANDIDATES=' .env 2>/dev/null; then
  grep '^SC_LIBRARY_RERANK_MAX_CANDIDATES=' .env
else
  echo "SC_LIBRARY_RERANK_MAX_CANDIDATES is unset -> backend default is 40"
fi

docker compose config --quiet
docker compose build --no-cache
docker compose up -d --force-recreate

wait_healthy(){
  local container="$1"
  for i in $(seq 1 90); do
    local state
    state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || true)"
    [[ "$state" == "healthy" ]] && return 0
    [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=300; fail "$container state $state"; }
    sleep 2
  done
  fail "$container did not become healthy"
}
wait_healthy sc-library-ingestion
wait_healthy sc-library-backend
BASE=http://127.0.0.1:8087

curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); c=x.get('capabilities',{})
keys=['neural_reranking','neural_reranking_baseline_rank_preserved','neural_reranking_score_components_exposed','neural_reranking_retrieval_evaluation','semantic_similarity_representation_search','scientific_embedding_governance','go_research_ingestion_job_fabric','native_graph_query_engine']
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.53.0'
for key in keys: assert c.get(key) is True, key
for key in ['neural_reranking_automatic_filtering','neural_reranking_evidence_promotion','neural_reranking_truth_promotion','neural_reranking_score_is_probability']:
    assert c.get(key) is False, key
PY

curl -fsS "$BASE/v1/neural-reranking/readiness" -o "$TMP/rerank-ready.json"
python3 - "$TMP/rerank-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-neural-reranking/1.0'
assert x.get('state')=='ready'
assert x.get('baseline_fallback_when_unconfigured') is True
assert x.get('retrieval_evaluation_integration') is True
assert x.get('fake_neural_scores') is False
g=x.get('guardrails') or {}
assert g.get('rerank_score_is_probability') is False
assert g.get('rerank_score_is_evidence') is False
assert g.get('rerank_score_is_truth') is False
PY

# If reranking is disabled (the default), prove fail-open baseline preservation without an external call.
python3 - "$TMP/rerank-ready.json" > "$TMP/is-configured.txt" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print('1' if x.get('configured') else '0')
PY
if [[ "$(cat "$TMP/is-configured.txt")" == "0" ]]; then
  curl -fsS -X POST "$BASE/v1/neural-reranking/rerank" \
    -H 'Content-Type: application/json' \
    --data '{"query":"carbon emissions","results":[{"record_id":"a","title":"Carbon accounting","hybrid_score":0.5},{"record_id":"b","title":"Climate finance","hybrid_score":0.4}]}' \
    -o "$TMP/rerank.json"
  python3 - "$TMP/rerank.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'available':x.get('available'),'reason':x.get('reason'),'ids':[r.get('record_id') for r in x.get('results',[])]},indent=2))
assert x.get('schema')=='sc-library-neural-reranking/1.0'
assert x.get('available') is False and x.get('reason')=='reranking-provider-not-configured'
assert [r.get('record_id') for r in x.get('results',[])]==['a','b']
for r in x.get('results',[]):
    b=r.get('neural_reranking') or {}
    assert b.get('provider_relevance_score') is None and b.get('rank_delta')==0
PY
else
  echo "NOTE: neural reranker is configured; deployment verifier will not make a billable/external rerank call."
fi

# Evaluation route is local/deterministic and does not call the neural provider.
curl -fsS -X POST "$BASE/v1/neural-reranking/evaluate" \
  -H 'Content-Type: application/json' \
  --data '{"query":"carbon emissions","baseline_results":[{"record_id":"a"},{"record_id":"b"}],"reranked_results":[{"record_id":"b"},{"record_id":"a"}],"judgments":[{"record_id":"a","relevance_grade":1},{"record_id":"b","relevance_grade":3}],"known_relevant_ids":["a","b"]}' \
  -o "$TMP/rerank-eval.json"
python3 - "$TMP/rerank-eval.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'judgment_count':x.get('judgment_count'),'deltas':x.get('metric_deltas_reranked_minus_baseline')},indent=2))
assert x.get('schema')=='sc-library-neural-reranking-evaluation/1.0'
assert x.get('judgment_count')==2
i=x.get('interpretation') or {}
assert i.get('quality_claim_requires_explicit_judgments') is True
assert i.get('positive_metric_delta_is_not_truth_validation') is True
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search-ready.json"
python3 - "$TMP/search-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); n=x.get('neural_reranking') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'neural_reranking_contract':x.get('neural_reranking_contract'),'neural_configured':n.get('configured')},indent=2))
assert x.get('hybrid_retrieval') is True
assert x.get('neural_reranking_contract')=='sc-library-neural-reranking/1.0'
assert x.get('neural_reranking_evaluation_contract')=='sc-library-neural-reranking-evaluation/1.0'
assert n.get('schema')=='sc-library-neural-reranking/1.0'
PY

# Preserve v5.41 semantic-governance readiness.
curl -fsS "$BASE/v1/semantic-similarity/readiness" -o "$TMP/semantic-ready.json"
python3 - "$TMP/semantic-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert x.get('schema')=='sc-library-representation-search/1.0' and x.get('state')=='ready'
assert x.get('record_to_record_similarity_requires_provider') is False
assert (x.get('guardrails') or {}).get('semantic_similarity_is_truth') is False
print(json.dumps({'semantic_state':x.get('state'),'stored':x.get('stored_representations')},indent=2))
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.53.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.53.0 Neural Reranking & Retrieval Evaluation deployed and verified."
echo "NOTE: No embedding backfill and no external reranker call was initiated by this installer."
