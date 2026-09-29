#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.52.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2520.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.52.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.52.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.52.0"
[[ -f "$SRC/app/representation_search.py" ]] || fail "representation search module missing"
grep -q 'sc-library-semantic-similarity/1.0' "$SRC/app/representation_search.py" || fail "semantic similarity contract missing"
grep -q 'library_record_embeddings_specification_idx' "$SRC/app/schema.sql" || fail "semantic specification index migration missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.52.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
if grep -q '^SC_LIBRARY_SEMANTIC_MIN_SIMILARITY=' .env 2>/dev/null; then
  grep '^SC_LIBRARY_SEMANTIC_MIN_SIMILARITY=' .env
else
  echo "SC_LIBRARY_SEMANTIC_MIN_SIMILARITY is unset -> backend default is 0.0"
fi
if grep -q '^SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=' .env 2>/dev/null; then
  grep '^SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=' .env
else
  echo "SC_LIBRARY_EMBEDDING_COMPUTE_TARGET is unset -> backend default is local"
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
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
keys=['scientific_embedding_governance','semantic_similarity_representation_search','semantic_similarity_current_specification_only','semantic_similarity_current_content_only','semantic_record_to_record_without_provider','workspace_embedding_compute_handoff','go_research_ingestion_job_fabric','native_graph_query_engine']
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'capabilities':{k:c.get(k) for k in keys}},indent=2))
assert d.get('ok') is True and d.get('version')=='2.52.0'
for key in keys: assert c.get(key) is True, key
for key in ['semantic_similarity_automatic_evidence_promotion','semantic_similarity_automatic_truth_promotion','semantic_similarity_automatic_causality_inference']:
    assert c.get(key) is False, key
PY

curl -fsS "$BASE/v1/semantic-similarity/readiness" -o "$TMP/semantic-readiness.json"
python3 - "$TMP/semantic-readiness.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-representation-search/1.0'
assert x.get('state')=='ready'
assert x.get('record_to_record_similarity_requires_provider') is False
assert x.get('query_text_current_specification_only') is True
assert x.get('record_similarity_same_specification_as_seed') is True
g=x.get('guardrails') or {}
assert g.get('semantic_similarity_is_evidence') is False
assert g.get('semantic_similarity_is_truth') is False
assert g.get('semantic_similarity_is_causality') is False
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search-readiness.json"
python3 - "$TMP/search-readiness.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); r=x.get('representation_search') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'semantic_retrieval':x.get('semantic_retrieval'),'representation_state':r.get('state'),'eligible':r.get('record_similarity_eligible_representations')},indent=2))
assert x.get('hybrid_retrieval') is True
assert r.get('schema')=='sc-library-representation-search/1.0' and r.get('state')=='ready'
assert x.get('semantic_similarity_contract')=='sc-library-semantic-similarity/1.0'
PY

# Verify additive indexes and vector-store continuity.
docker exec -i sc-library-backend python - <<'PY'
import os,psycopg,json
with psycopg.connect(os.environ['DATABASE_URL']) as conn, conn.cursor() as cur:
    cur.execute("SELECT indexname FROM pg_indexes WHERE schemaname='public' AND tablename='library_record_embeddings'")
    indexes={r[0] for r in cur.fetchall()}
    cur.execute("SELECT count(*) FROM library_record_embeddings")
    stored=cur.fetchone()[0]
required={'library_record_embeddings_specification_idx','library_record_embeddings_representation_uidx'}
print(json.dumps({'required_indexes':sorted(required & indexes),'stored_representations':stored},indent=2))
assert required <= indexes
PY

# Text semantic endpoint must fail open cleanly when provider compute is disabled.
curl -fsS "$BASE/v1/semantic-similarity/search?q=sustainability&limit=3" -o "$TMP/text-search.json"
python3 - "$TMP/text-search.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'available':x.get('available'),'reason':x.get('reason'),'count':x.get('count')},indent=2))
assert x.get('schema')=='sc-library-representation-search/1.0'
if x.get('available') is False:
    assert x.get('reason')=='embedding-provider-not-configured'
else:
    g=x.get('guardrails') or {}
    assert g.get('current_content_only') is True and g.get('current_specification_only') is True
    assert g.get('semantic_similarity_is_truth') is False
PY

# If any current governed representation exists, prove provider-independent record similarity.
docker exec -i sc-library-backend python - <<'PY'
import json,os,psycopg,urllib.parse,urllib.request
with psycopg.connect(os.environ['DATABASE_URL']) as conn, conn.cursor() as cur:
    cur.execute("""
      SELECT r.record_id
        FROM library_records r JOIN library_record_embeddings e ON e.record_id=r.record_id
       WHERE e.content_hash=r.content_hash AND e.specification_fingerprint IS NOT NULL
       ORDER BY r.record_id LIMIT 1
    """)
    row=cur.fetchone()
if not row:
    print(json.dumps({'record_similarity':'SKIP','reason':'no governed representations stored yet'},indent=2))
else:
    rid=row[0]; url='http://127.0.0.1:8080/v1/semantic-similarity/records/'+urllib.parse.quote(rid,safe='')+'?limit=3'
    x=json.load(urllib.request.urlopen(url,timeout=20))
    print(json.dumps({'record_similarity':'PASS','record_id':rid,'schema':x.get('schema'),'count':x.get('count'),'provider_required':(x.get('guardrails') or {}).get('provider_required_for_seed_search')},indent=2))
    assert x.get('schema')=='sc-library-semantic-similarity/1.0'
    assert (x.get('guardrails') or {}).get('provider_required_for_seed_search') is False
PY

# Existing hybrid endpoint remains callable even with semantic compute unavailable.
curl -fsS "$BASE/v1/search?q=sustainability&mode=hybrid&limit=3" -o "$TMP/hybrid.json"
python3 - "$TMP/hybrid.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); r=x.get('retrieval') or {}
print(json.dumps({'schema':x.get('schema'),'effective_mode':r.get('effective_mode'),'degraded':r.get('degraded'),'semantic_candidates':r.get('semantic_candidate_count')},indent=2))
assert x.get('schema')=='sc-library-hybrid-retrieval/1.0'
assert r.get('semantic_current_specification_only') is True
assert r.get('semantic_current_content_only') is True
assert r.get('semantic_similarity_is_truth') is False
PY

# Preserve cross-runtime continuity.
curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.52.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.52.0 Semantic Similarity & Representation Search deployed and verified."
echo "NOTE: No embedding backfill was queued by this installer."
