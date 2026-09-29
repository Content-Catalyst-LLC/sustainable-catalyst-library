#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.56.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2560.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.56.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.56.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.56.0"
[[ -f "$SRC/app/original_language_corpus.py" ]] || fail "original language corpus module missing"
grep -q 'sc-library-original-language-corpus/1.0' "$SRC/app/original_language_corpus.py" || fail "original language corpus contract missing"
grep -q 'library_original_language_captures' "$SRC/app/schema.sql" || fail "original language capture table migration missing"
grep -q 'sc-library-global-source-federation-registry/1.0' "$SRC/app/global_source_federation.py" || fail "preserved v5.44 federation contract missing"
grep -q 'sc-library-publication-embedding-map/1.0' "$SRC/app/publication_embedding_maps.py" || fail "preserved v5.43 embedding map contract missing"
grep -q 'sc-library-neural-reranking/1.0' "$SRC/app/neural_reranking.py" || fail "preserved v5.42 reranking contract missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.56.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
echo "v5.45.0 preserves original-language source bytes/text; it does not translate, query a source, or add credentials during deployment."
if grep -q '^SC_LIBRARY_EMBEDDING_PROVIDER=' .env 2>/dev/null; then grep '^SC_LIBRARY_EMBEDDING_PROVIDER=' .env; else echo "SC_LIBRARY_EMBEDDING_PROVIDER is unset -> semantic query embedding remains disabled"; fi
if grep -q '^SC_LIBRARY_RERANK_PROVIDER=' .env 2>/dev/null; then grep '^SC_LIBRARY_RERANK_PROVIDER=' .env; else echo "SC_LIBRARY_RERANK_PROVIDER is unset -> neural reranking remains disabled"; fi

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
keys=[
 'original_language_corpus_ingestion','original_language_raw_payload_preservation',
 'original_language_raw_text_preservation','original_language_sha256_content_addressing',
 'original_language_script_variant_identity','original_language_unicode_normalization_is_derived',
 'original_language_translation_is_derived','global_source_federation_registry','publication_embedding_maps',
 'neural_reranking','semantic_similarity_representation_search','scientific_embedding_governance',
 'go_research_ingestion_job_fabric','native_graph_query_engine'
]
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.56.0'
for key in keys: assert c.get(key) is True, key
for key in [
 'original_language_normalized_text_replaces_original','original_language_automatic_translation',
 'original_language_automatic_evidence_promotion','original_language_automatic_truth_promotion',
 'original_language_automatic_platform_core_promotion'
]: assert c.get(key) is False, key
PY

curl -fsS "$BASE/v1/original-language-corpus/readiness" -o "$TMP/original-ready.json"
python3 - "$TMP/original-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-original-language-corpus-readiness/1.0'
assert x.get('contract')=='sc-library-original-language-corpus/1.0'
assert x.get('version')=='5.45.0' and x.get('state')=='ready'
p=x.get('preservation') or {}; g=x.get('guardrails') or {}
assert p.get('raw_payload_preserved') is True and p.get('raw_text_preserved') is True
assert p.get('original_representation_canonical') is True
assert p.get('unicode_normalization_is_derived') is True
assert g.get('translation_is_derived_representation') is True
assert g.get('automatic_translation') is False
assert g.get('normalized_text_replaces_original') is False
assert g.get('automatic_platform_core_promotion') is False
PY

curl -fsS -X POST "$BASE/v1/original-language-corpus/validate" \
  -H 'Content-Type: application/json' \
  --data '{"source_id":"crossref","source_record_id":"deployment-validation-only","language_bcp47":"fr","script_iso15924":"Latn","raw_text":"Café — texte source","charset":"utf-8","create_normalized_derivative":true}' \
  -o "$TMP/validate.json"
python3 - "$TMP/validate.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-original-language-validation/1.0' and x.get('valid') is True
n=x.get('normalized') or {}
assert len(n.get('raw_payload_sha256',''))==64 and len(n.get('raw_text_sha256',''))==64
assert (x.get('guardrails') or {}).get('normalization_replaces_original') is False
PY

curl -fsS -X POST "$BASE/v1/original-language-corpus/package" \
  -H 'Content-Type: application/json' \
  --data '{"source_id":"crossref","source_record_id":"deployment-package-only","language_bcp47":"ja","script_iso15924":"Jpan","raw_text":"日本語の原文","charset":"utf-8","create_normalized_derivative":true}' \
  -o "$TMP/package.json"
python3 - "$TMP/package.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'capture_id':x.get('capture_id'),'representations':x.get('representations'),'persisted':x.get('persisted')},ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-original-language-capture/1.0'
assert str(x.get('capture_id','')).startswith('olc:')
reps=x.get('representations') or []
assert len(reps)>=2 and reps[0].get('representation_kind')=='original'
assert reps[0].get('canonical_original') is True and reps[0].get('derived') is False
assert reps[1].get('representation_kind')=='unicode-normalized' and reps[1].get('derived') is True
assert x.get('persisted') is False
assert '_raw_text' not in x and '_raw_bytes' not in x
PY

curl -sS -X POST "$BASE/v1/original-language-corpus/validate" \
  -H 'Content-Type: application/json' \
  --data '{"language_bcp47":"de","script_iso15924":"Latn","raw_text":"Originaltext","automatic_translation":true}' \
  -o "$TMP/unsafe.json"
python3 - "$TMP/unsafe.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'valid':x.get('valid'),'errors':x.get('errors')},indent=2))
assert x.get('valid') is False
assert 'automatic-translation-prohibited' in (x.get('errors') or [])
PY

# Verify migrations without writing a sample source capture.
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
pool=get_pool()
with pool.connection() as conn, conn.cursor() as cur:
    required={
      'library_original_language_captures': {'capture_id','raw_payload','raw_payload_sha256','raw_text','raw_text_sha256','language_bcp47','script_iso15924'},
      'library_text_representations': {'representation_id','capture_id','representation_kind','text_content','text_sha256','canonical_original','derived'},
      'library_text_transformations': {'transformation_id','capture_id','input_representation_id','output_representation_id','operation'},
    }
    out={}
    for table, expected in required.items():
        cur.execute("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=%s", (table,))
        cols={r['column_name'] for r in cur.fetchall()}
        assert expected <= cols, (table, expected-cols)
        cur.execute(f'SELECT count(*) AS n FROM {table}')
        out[table]=int(cur.fetchone()['n'])
print({'schema_tables':out})
PY

curl -fsS "$BASE/v1/global-source-federation/readiness" -o "$TMP/federation.json"
python3 - "$TMP/federation.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert x.get('state')=='ready' and x.get('version')=='5.44.0'
assert x.get('sources')==26 and x.get('connector_contracts')==26
print(json.dumps({'federation_version':x.get('version'),'sources':x.get('sources'),'connectors':x.get('connector_contracts')},indent=2))
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search-ready.json"
python3 - "$TMP/search-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); ol=x.get('original_language_corpus') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'original_language_state':ol.get('state'),'contract':x.get('original_language_corpus_contract')},indent=2))
assert x.get('hybrid_retrieval') is True
assert ol.get('state')=='ready'
assert x.get('original_language_corpus_contract')=='sc-library-original-language-corpus/1.0'
assert x.get('global_source_federation_contract')=='sc-library-global-source-federation-registry/1.0'
assert x.get('publication_embedding_map_contract')=='sc-library-publication-embedding-map/1.0'
assert x.get('neural_reranking_contract')=='sc-library-neural-reranking/1.0'
PY

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json" || true
python3 - "$TMP/core.json" <<'PY'
import json,sys,os
p=sys.argv[1]
if os.path.exists(p) and os.path.getsize(p):
    x=json.load(open(p,encoding='utf-8')); print(json.dumps({'core_version':x.get('core_version'),'reachable':x.get('reachable'),'ready':x.get('ready'),'total':x.get('total')},indent=2))
else:
    print('INFO: Platform Core readiness response unavailable; Library deployment verification continues.')
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.56.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.56.0 Original-Language Corpus Ingestion & Preservation deployed and verified."
echo "NOTE: Deployment performed validation/package checks only. No sample source capture was persisted, no source queried, no translation performed, and no embedding backfill or external model request was initiated."
