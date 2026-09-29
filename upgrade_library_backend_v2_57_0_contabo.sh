#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.57.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2570.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.57.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.57.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.57.0"
[[ -f "$SRC/app/ocr_htr_transcription_lineage.py" ]] || fail "lineage module missing"
grep -q 'sc-library-ocr-htr-transcription-lineage/1.0' "$SRC/app/ocr_htr_transcription_lineage.py" || fail "lineage contract missing"
grep -q 'library_source_media_assets' "$SRC/app/schema.sql" || fail "source-media schema missing"
grep -q 'library_text_derivation_runs' "$SRC/app/schema.sql" || fail "derivation-run schema missing"
grep -q 'library_text_derivation_segments' "$SRC/app/schema.sql" || fail "derivation-segment schema missing"
grep -q 'sc-library-original-language-corpus/1.0' "$SRC/app/original_language_corpus.py" || fail "preserved v5.45 original-language contract missing"
grep -q 'sc-library-global-source-federation-registry/1.0' "$SRC/app/global_source_federation.py" || fail "preserved v5.44 federation contract missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.57.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
echo "v5.46.0 installs lineage contracts only; deployment does not invoke OCR/HTR/ASR providers, write a sample derivation, translate text, or add credentials."

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
true_keys=['ocr_htr_transcription_lineage','ocr_htr_transcription_source_media_preservation','ocr_htr_transcription_engine_model_provenance','ocr_htr_transcription_segment_lineage','ocr_htr_transcription_review_state','original_language_corpus_ingestion','global_source_federation_registry','publication_embedding_maps','neural_reranking','semantic_similarity_representation_search','scientific_embedding_governance','go_research_ingestion_job_fabric','native_graph_query_engine']
false_keys=['ocr_htr_transcription_confidence_is_truth_probability','ocr_htr_transcription_output_replaces_original','ocr_htr_transcription_automatic_translation','ocr_htr_transcription_automatic_evidence_promotion','ocr_htr_transcription_automatic_truth_promotion','ocr_htr_transcription_automatic_platform_core_promotion']
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in true_keys+false_keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.57.0'
for k in true_keys: assert c.get(k) is True, k
for k in false_keys: assert c.get(k) is False, k
PY

curl -fsS "$BASE/v1/ocr-htr-transcription/readiness" -o "$TMP/lineage-ready.json"
python3 - "$TMP/lineage-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-ocr-htr-transcription-readiness/1.0'
assert x.get('contract')=='sc-library-ocr-htr-transcription-lineage/1.0'
assert x.get('version')=='5.46.0' and x.get('state')=='ready'
l=x.get('lineage') or {}; g=x.get('guardrails') or {}
for k in ['source_media_payload_preserved','source_media_sha256_content_addressing','engine_and_model_identity_preserved','engine_parameters_preserved','segment_geometry_and_timecodes_supported','confidence_preserved_as_measurement','review_state_explicit','derived_text_representation_linked','ocr_htr_transcription_outputs_are_derived']:
    assert l.get(k) is True, k
assert g.get('derived_text_replaces_original') is False
assert g.get('confidence_is_truth_probability') is False
assert g.get('automatic_translation') is False
assert g.get('automatic_platform_core_promotion') is False
PY

curl -fsS -X POST "$BASE/v1/ocr-htr-transcription/validate" -H 'Content-Type: application/json' \
  --data '{"derivation_kind":"ocr","source_id":"internetarchive","source_record_id":"deployment-validation-only","media_type":"image/png","source_payload_base64":"RkFLRS1TQ0FOLUJZVEVT","language_bcp47":"fr","script_iso15924":"Latn","output_text":"Texte reconnu","engine":{"provider":"local-validation","name":"ocr-engine","version":"1","model":"fra"},"confidence_summary":{"mean":0.92},"segments":[{"sequence":1,"segment_kind":"line","page_number":1,"bounding_box":[0.1,0.2,0.7,0.1],"text":"Texte reconnu","confidence":0.92}]}' \
  -o "$TMP/validate.json"
python3 - "$TMP/validate.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':x.get('schema'),'valid':x.get('valid'),'errors':x.get('errors'),'normalized':x.get('normalized'),'guardrails':x.get('guardrails')},ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-text-derivation-validation/1.0' and x.get('valid') is True
n=x.get('normalized') or {}; assert n.get('derivation_kind')=='ocr'; assert len((n.get('engine') or {}).get('fingerprint_sha256',''))==64
assert (x.get('guardrails') or {}).get('confidence_is_truth_probability') is False
PY

curl -fsS -X POST "$BASE/v1/ocr-htr-transcription/package" -H 'Content-Type: application/json' \
  --data '{"derivation_kind":"transcription","source_id":"internetarchive","source_record_id":"deployment-package-only","media_type":"audio/wav","source_payload_base64":"RkFLRS1BVURJTy1CWVRFUw==","language_bcp47":"en","script_iso15924":"Latn","output_text":"hello world","engine":{"provider":"local-validation","name":"asr-engine","version":"1"},"segments":[{"sequence":1,"segment_kind":"time-span","start_ms":0,"end_ms":1200,"speaker_label":"speaker-1","text":"hello world","confidence":0.9}]}' \
  -o "$TMP/package.json"
python3 - "$TMP/package.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'run_id':x.get('run_id'),'kind':x.get('derivation_kind'),'source_asset_id':x.get('source_asset_id'),'output_representation':x.get('output_representation'),'segments':x.get('segments'),'persisted':x.get('persisted')},ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-text-derivation-run/1.0'; assert str(x.get('run_id','')).startswith('textrun:')
assert x.get('derivation_kind')=='transcription'; assert str(x.get('source_asset_id','')).startswith('srcasset:')
r=x.get('output_representation') or {}; assert r.get('representation_kind')=='transcription' and r.get('derived') is True and r.get('canonical_original') is False
s=(x.get('segments') or [])[0]; assert s.get('start_ms')==0 and s.get('end_ms')==1200
assert x.get('persisted') is False and '_source_bytes' not in x and '_output_text' not in x
PY

curl -fsS -X POST "$BASE/v1/ocr-htr-transcription/validate" -H 'Content-Type: application/json' \
  --data '{"derivation_kind":"htr","source_asset_id":"srcasset:11111111111111111111111111111111","language_bcp47":"en","script_iso15924":"Latn","output_text":"derived","engine":{"provider":"local-validation","name":"htr-engine"},"automatic_translation":true,"output_is_canonical_original":true}' \
  -o "$TMP/unsafe.json"
python3 - "$TMP/unsafe.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'valid':x.get('valid'),'errors':x.get('errors')},indent=2))
assert x.get('valid') is False
assert 'automatic-translation-prohibited' in (x.get('errors') or [])
assert 'derived-output-cannot-be-canonical-original' in (x.get('errors') or [])
PY

# Verify cumulative schema without persisting test media or derived text.
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
pool=get_pool()
required={
 'library_source_media_assets': {'source_asset_id','raw_payload','raw_payload_sha256','media_type'},
 'library_text_derivation_runs': {'run_id','derivation_kind','source_asset_id','input_representation_id','output_representation_id','engine_spec_fingerprint','confidence_summary','review_state'},
 'library_text_derivation_segments': {'segment_id','run_id','sequence','page_number','start_ms','end_ms','bounding_box','text_sha256','confidence','review_state'},
 'library_text_representations': {'representation_id','capture_id','source_asset_id','representation_kind','text_sha256'},
}
with pool.connection() as conn, conn.cursor() as cur:
  out={}
  for table, expected in required.items():
    cur.execute("SELECT column_name,is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name=%s",(table,))
    rows=cur.fetchall(); cols={r['column_name'] for r in rows}; assert expected <= cols,(table,expected-cols)
    if table=='library_text_representations':
      nulls={r['column_name']:r['is_nullable'] for r in rows}; assert nulls['capture_id']=='YES'
    cur.execute(f'SELECT count(*) AS n FROM {table}'); out[table]=int(cur.fetchone()['n'])
print({'schema_tables':out})
PY

curl -fsS "$BASE/v1/original-language-corpus/readiness" -o "$TMP/original.json"
python3 - "$TMP/original.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); assert x.get('state')=='ready' and x.get('version')=='5.45.0'
assert (x.get('preservation') or {}).get('original_representation_canonical') is True
print(json.dumps({'original_language_version':x.get('version'),'state':x.get('state'),'counts':x.get('counts')},indent=2))
PY

curl -fsS "$BASE/v1/global-source-federation/readiness" -o "$TMP/federation.json"
python3 - "$TMP/federation.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); assert x.get('state')=='ready' and x.get('version')=='5.44.0'; assert x.get('sources')==26 and x.get('connector_contracts')==26
print(json.dumps({'federation_version':x.get('version'),'sources':x.get('sources'),'connectors':x.get('connector_contracts')},indent=2))
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); o=x.get('ocr_htr_transcription') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'ocr_htr_transcription_state':o.get('state'),'lineage_contract':x.get('ocr_htr_transcription_lineage_contract')},indent=2))
assert x.get('hybrid_retrieval') is True; assert o.get('state')=='ready'; assert x.get('ocr_htr_transcription_lineage_contract')=='sc-library-ocr-htr-transcription-lineage/1.0'
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.57.0'; assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.57.0 OCR, HTR & Transcription Lineage deployed and verified."
echo "NOTE: Deployment validated/package-built sample payloads only. No sample source-media asset or derivation run was persisted; no OCR/HTR/ASR provider, translation provider, embedding provider, or reranker was invoked."
