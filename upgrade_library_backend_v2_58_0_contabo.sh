#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.58.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2580.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.58.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.58.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.58.0"
[[ -f "$SRC/app/linguistic_corpus.py" ]] || fail "linguistic corpus module missing"
grep -q 'sc-library-linguistic-corpus/1.0' "$SRC/app/linguistic_corpus.py" || fail "linguistic corpus contract missing"
grep -q 'library_linguistic_corpora' "$SRC/app/schema.sql" || fail "linguistic corpus schema missing"
grep -q 'library_linguistic_documents' "$SRC/app/schema.sql" || fail "linguistic document schema missing"
grep -q 'library_linguistic_tokens' "$SRC/app/schema.sql" || fail "linguistic token schema missing"
grep -q 'sc-library-ocr-htr-transcription-lineage/1.0' "$SRC/app/ocr_htr_transcription_lineage.py" || fail "preserved v5.46 lineage contract missing"
grep -q 'sc-library-original-language-corpus/1.0' "$SRC/app/original_language_corpus.py" || fail "preserved v5.45 original-language contract missing"
grep -q 'sc-library-global-source-federation-registry/1.0' "$SRC/app/global_source_federation.py" || fail "preserved v5.44 federation contract missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.58.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
echo "v5.47.0 adds deterministic linguistic corpus/KWIC contracts; deployment writes no sample corpus and invokes no external model/provider."

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
true_keys=['linguistic_corpus_objects','linguistic_document_objects','linguistic_token_objects','linguistic_concordance','linguistic_kwic','linguistic_frequency_tables','linguistic_representation_lineage','linguistic_deterministic_tokenizer','ocr_htr_transcription_lineage','original_language_corpus_ingestion','global_source_federation_registry','publication_embedding_maps','neural_reranking','semantic_similarity_representation_search','scientific_embedding_governance','go_research_ingestion_job_fabric','native_graph_query_engine']
false_keys=['linguistic_tokenizer_is_morphological_analysis','linguistic_kwic_context_establishes_meaning_or_intent','linguistic_frequency_implies_importance','linguistic_automatic_translation','linguistic_automatic_evidence_promotion','linguistic_automatic_truth_promotion','linguistic_automatic_platform_core_promotion']
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in true_keys+false_keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.58.0'
for k in true_keys: assert c.get(k) is True,k
for k in false_keys: assert c.get(k) is False,k
PY

curl -fsS "$BASE/v1/linguistic-corpus/readiness" -o "$TMP/ling-ready.json"
python3 - "$TMP/ling-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-linguistic-corpus-readiness/1.0'
assert x.get('contract')=='sc-library-linguistic-corpus/1.0'
assert x.get('version')=='5.47.0' and x.get('state')=='ready'
t=x.get('tokenizer') or {}; assert t.get('profile')=='unicode-word-v1' and len(t.get('fingerprint_sha256',''))==64
c=x.get('capabilities') or {}; g=x.get('guardrails') or {}
for k in ['corpus_objects','document_objects','token_objects','character_offsets','representation_lineage','ocr_htr_transcription_lineage','concordance','kwic','frequency_tables','case_sensitive_queries','phrase_queries','deterministic_query_fingerprints']:
    assert c.get(k) is True,k
assert g.get('tokenization_is_morphological_analysis') is False
assert g.get('kwic_context_establishes_meaning_or_intent') is False
assert g.get('frequency_implies_importance') is False
assert g.get('automatic_platform_core_promotion') is False
PY

# Validate/package/analysis only: no persistent sample corpus is created.
CORPUS='{"title":"deployment validation corpus","documents":[{"representation_id":"textrep:11111111111111111111111111111111","record_id":"validation-1","source_kind":"original","language_bcp47":"en","script_iso15924":"Latn","text":"Climate policy changes climate risk. Policy matters."},{"representation_id":"textrep:22222222222222222222222222222222","record_id":"validation-2","source_kind":"ocr","derivation_run_id":"textrun:22222222222222222222222222222222","review_state":"human-reviewed","language_bcp47":"en","script_iso15924":"Latn","text":"Historical climate policy records."}]}'
printf '%s' "$CORPUS" > "$TMP/corpus.json"

curl -fsS -X POST "$BASE/v1/linguistic-corpus/validate" -H 'Content-Type: application/json' --data-binary @"$TMP/corpus.json" -o "$TMP/validate.json"
python3 - "$TMP/validate.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':x.get('schema'),'valid':x.get('valid'),'warnings':x.get('warnings'),'guardrails':x.get('guardrails')},indent=2))
assert x.get('schema')=='sc-library-linguistic-corpus-validation/1.0' and x.get('valid') is True
assert (x.get('guardrails') or {}).get('tokenization_is_morphological_analysis') is False
PY

curl -fsS -X POST "$BASE/v1/linguistic-corpus/package" -H 'Content-Type: application/json' --data-binary @"$TMP/corpus.json" -o "$TMP/package.json"
python3 - "$TMP/package.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':x.get('schema'),'corpus_id':x.get('corpus_id'),'document_count':x.get('document_count'),'token_count':x.get('token_count'),'source_kind_distribution':x.get('source_kind_distribution')},indent=2))
assert x.get('schema')=='sc-library-linguistic-corpus/1.0'; assert str(x.get('corpus_id','')).startswith('lingcorpus:')
assert x.get('document_count')==2 and x.get('token_count',0)>0
assert (x.get('source_kind_distribution') or {}).get('original')==1 and (x.get('source_kind_distribution') or {}).get('ocr')==1
for d in x.get('documents') or []:
    assert '_text' not in d
    assert len(d.get('tokens') or [])==d.get('token_count')
PY

python3 - "$TMP/corpus.json" > "$TMP/kwic-request.json" <<'PY'
import json,sys
corpus=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'corpus':corpus,'query':'climate policy','window_tokens':2,'case_sensitive':False,'limit':20}))
PY
curl -fsS -X POST "$BASE/v1/linguistic-corpus/kwic" -H 'Content-Type: application/json' --data-binary @"$TMP/kwic-request.json" -o "$TMP/kwic.json"
python3 - "$TMP/kwic.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-kwic-result/1.0'; assert x.get('total_matches')==2
assert len(x.get('query_fingerprint_sha256',''))==64
for m in x.get('matches') or []:
    assert m.get('representation_id'); assert m.get('char_end',0)>m.get('char_start',-1)
assert (x.get('guardrails') or {}).get('kwic_context_establishes_meaning_or_intent') is False
PY

python3 - "$TMP/corpus.json" > "$TMP/freq-request.json" <<'PY'
import json,sys
corpus=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'corpus':corpus,'words_only':True,'limit':20}))
PY
curl -fsS -X POST "$BASE/v1/linguistic-corpus/frequencies" -H 'Content-Type: application/json' --data-binary @"$TMP/freq-request.json" -o "$TMP/freq.json"
python3 - "$TMP/freq.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,ensure_ascii=False,indent=2))
assert x.get('schema')=='sc-library-corpus-frequency-table/1.0'
climate=next(r for r in x.get('rows') or [] if r.get('normalized_text')=='climate'); assert climate.get('count')==3
assert (x.get('guardrails') or {}).get('frequency_implies_importance') is False
PY

curl -fsS -X POST "$BASE/v1/linguistic-corpus/validate" -H 'Content-Type: application/json' \
  --data '{"title":"unsafe","automatic_translation":true,"automatic_lemmatization":true,"documents":[{"representation_id":"textrep:33333333333333333333333333333333","source_kind":"original","language_bcp47":"en","text":"unsafe"}]}' \
  -o "$TMP/unsafe.json"
python3 - "$TMP/unsafe.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'valid':x.get('valid'),'errors':x.get('errors')},indent=2))
assert x.get('valid') is False
assert 'automatic-translation-prohibited' in (x.get('errors') or [])
assert 'automatic-lemmatization-prohibited' in (x.get('errors') or [])
PY

# Verify additive database schema and counts without inserting sample linguistic content.
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
pool=get_pool()
required={
 'library_linguistic_corpora': {'corpus_id','corpus_fingerprint','tokenizer_spec','tokenizer_spec_fingerprint','document_count','token_count'},
 'library_linguistic_documents': {'document_id','corpus_id','representation_id','source_kind','language_bcp47','text_sha256','character_count','token_count'},
 'library_linguistic_tokens': {'token_id','corpus_id','document_id','representation_id','sequence','token_text','normalized_text','token_kind','start_char','end_char'},
}
with pool.connection() as conn, conn.cursor() as cur:
  out={}
  for table,expected in required.items():
    cur.execute("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=%s",(table,))
    cols={r['column_name'] for r in cur.fetchall()}; assert expected <= cols,(table,expected-cols)
    cur.execute(f'SELECT count(*) AS n FROM {table}'); out[table]=int(cur.fetchone()['n'])
print({'linguistic_schema_tables':out})
PY

curl -fsS "$BASE/v1/ocr-htr-transcription/readiness" -o "$TMP/lineage.json"
python3 - "$TMP/lineage.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); assert x.get('state')=='ready' and x.get('version')=='5.46.0'
assert (x.get('lineage') or {}).get('ocr_htr_transcription_outputs_are_derived') is True
print(json.dumps({'ocr_htr_transcription_version':x.get('version'),'state':x.get('state'),'counts':x.get('counts')},indent=2))
PY

curl -fsS "$BASE/v1/original-language-corpus/readiness" -o "$TMP/original.json"
python3 - "$TMP/original.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); assert x.get('state')=='ready' and x.get('version')=='5.45.0'
assert (x.get('preservation') or {}).get('original_representation_canonical') is True
print(json.dumps({'original_language_version':x.get('version'),'state':x.get('state')},indent=2))
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
x=json.load(open(sys.argv[1],encoding='utf-8')); l=x.get('linguistic_corpus') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'linguistic_corpus_state':l.get('state'),'linguistic_corpus_contract':x.get('linguistic_corpus_contract'),'kwic_contract':x.get('kwic_result_contract')},indent=2))
assert x.get('hybrid_retrieval') is True; assert l.get('state')=='ready'
assert x.get('linguistic_corpus_contract')=='sc-library-linguistic-corpus/1.0'
assert x.get('kwic_result_contract')=='sc-library-kwic-result/1.0'
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.58.0'; assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.58.0 Linguistic Corpus Objects, Concordance & KWIC deployed and verified."
echo "NOTE: Deployment built non-persisting corpus/KWIC/frequency packages only. No sample linguistic corpus/token stream was persisted and no external language/model/translation/embedding/reranking provider was invoked."
