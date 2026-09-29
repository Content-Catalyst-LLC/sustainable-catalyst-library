#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.54.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2540.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.54.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.54.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.54.0"
[[ -f "$SRC/app/publication_embedding_maps.py" ]] || fail "publication embedding map module missing"
grep -q 'sc-library-publication-embedding-map/1.0' "$SRC/app/publication_embedding_maps.py" || fail "embedding map contract missing"
grep -q 'sc-library-neural-reranking/1.0' "$SRC/app/neural_reranking.py" || fail "preserved neural reranking contract missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.54.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
if grep -q '^SC_LIBRARY_EMBEDDING_PROVIDER=' .env 2>/dev/null; then grep '^SC_LIBRARY_EMBEDDING_PROVIDER=' .env; else echo "SC_LIBRARY_EMBEDDING_PROVIDER is unset -> stored maps remain renderable; query-text embedding remains disabled"; fi
if grep -q '^SC_LIBRARY_RERANK_PROVIDER=' .env 2>/dev/null; then grep '^SC_LIBRARY_RERANK_PROVIDER=' .env; else echo "SC_LIBRARY_RERANK_PROVIDER is unset -> backend default is disabled"; fi

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
keys=['publication_embedding_maps','publication_embedding_map_deterministic_pca','publication_embedding_map_same_specification_only','publication_embedding_map_current_content_only','publication_semantic_neighborhoods','semantic_similarity_representation_search','neural_reranking','scientific_embedding_governance','go_research_ingestion_job_fabric','native_graph_query_engine']
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.54.0'
for key in keys: assert c.get(key) is True, key
for key in ['publication_embedding_map_proximity_is_evidence','publication_embedding_map_proximity_is_truth','publication_embedding_map_proximity_is_causality']:
    assert c.get(key) is False, key
assert c.get('publication_embedding_map_provider_required_to_render_stored_map') is False
PY

curl -fsS "$BASE/v1/publication-embedding-maps/readiness" -o "$TMP/map-ready.json"
python3 - "$TMP/map-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-semantic-knowledge-landscape/1.0'
assert x.get('state')=='ready'
assert x.get('projection')=='sc-library-deterministic-pca-projection/1.0'
assert x.get('same_specification_only') is True
assert x.get('current_content_only') is True
assert x.get('provider_required_to_render_stored_map') is False
g=x.get('guardrails') or {}
assert g.get('spatial_proximity_is_evidence') is False
assert g.get('spatial_proximity_is_truth') is False
assert g.get('spatial_proximity_is_causality') is False
PY

# Map construction uses stored vectors only and makes no external model call.
curl -fsS -X POST "$BASE/v1/publication-embedding-maps/map" \
  -H 'Content-Type: application/json' \
  --data '{"source_key":"wordpress-main","similarity_threshold":0.72,"neighbors_per_point":8,"max_edges":200,"max_publications":50}' \
  -o "$TMP/map.json"
python3 - "$TMP/map.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'available':x.get('available'),'reason':x.get('reason'),'points':len(x.get('points') or []),'edges':len(x.get('edges') or []),'specification':x.get('specification')},indent=2))
assert x.get('schema')=='sc-library-publication-embedding-map/1.0'
g=x.get('guardrails') or {}
assert g.get('spatial_proximity_is_evidence') is False
assert g.get('spatial_proximity_is_truth') is False
assert g.get('spatial_proximity_is_causality') is False
points=x.get('points') or []
if x.get('available'):
    assert len(points) >= 2
    fps={((p.get('representation') or {}).get('specification_fingerprint_sha256')) for p in points}
    assert len(fps)==1 and None not in fps
    assert all(-1.000001 <= float(p.get('x',0)) <= 1.000001 and -1.000001 <= float(p.get('y',0)) <= 1.000001 for p in points)
else:
    assert x.get('reason') in {'no-current-governed-publication-representations','at-least-two-compatible-representations-required'}
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search-ready.json"
python3 - "$TMP/search-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); m=x.get('publication_embedding_maps') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'embedding_map_contract':x.get('publication_embedding_map_contract'),'map_state':m.get('state'),'neural_reranking_contract':x.get('neural_reranking_contract')},indent=2))
assert x.get('hybrid_retrieval') is True
assert x.get('publication_embedding_map_contract')=='sc-library-publication-embedding-map/1.0'
assert x.get('semantic_knowledge_landscape_contract')=='sc-library-semantic-knowledge-landscape/1.0'
assert m.get('schema')=='sc-library-semantic-knowledge-landscape/1.0'
assert x.get('neural_reranking_contract')=='sc-library-neural-reranking/1.0'
PY

curl -fsS "$BASE/v1/semantic-similarity/readiness" -o "$TMP/semantic-ready.json"
python3 - "$TMP/semantic-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert x.get('schema')=='sc-library-representation-search/1.0' and x.get('state')=='ready'
assert x.get('record_to_record_similarity_requires_provider') is False
assert (x.get('guardrails') or {}).get('semantic_similarity_is_truth') is False
print(json.dumps({'semantic_state':x.get('state'),'stored':x.get('stored_representations')},indent=2))
PY

curl -fsS "$BASE/v1/neural-reranking/readiness" -o "$TMP/rerank-ready.json"
python3 - "$TMP/rerank-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert x.get('schema')=='sc-library-neural-reranking/1.0' and x.get('state')=='ready'
assert x.get('baseline_fallback_when_unconfigured') is True
assert x.get('fake_neural_scores') is False
print(json.dumps({'reranking_state':x.get('state'),'configured':x.get('configured')},indent=2))
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.54.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.54.0 Publication Embedding Maps & Semantic Knowledge Landscape deployed and verified."
echo "NOTE: No embedding backfill and no external embedding/reranking request was initiated by this installer."
