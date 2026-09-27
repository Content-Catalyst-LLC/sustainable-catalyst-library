#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.49.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2490.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.49.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.49.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.49.0"
grep -q 'sc-library-go-ingestion-runtime/1.0' "$SRC/app/ingestion_job_fabric.py" || fail "Python Go-fabric adapter missing"
[[ -f "$SRC/go-ingestion-runtime/main.go" ]] || fail "Go ingestion runtime source missing"
[[ -f "$SRC/app/research_corpus_builder.py" ]] || fail "research corpus builder missing"
[[ -f "$SRC/app/unified_runtime_contract.py" ]] || fail "unified research runtime contract missing"
grep -q 'sc-library-research-runtime-contract/1.0' "$SRC/app/unified_runtime_contract.py" || fail "unified runtime contract schema missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'contract = "sc-library-go-ingestion-runtime/1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion contract missing"
[[ -f "$SRC/native-graph-runtime/Cargo.toml" ]] || fail "Rust native graph crate missing"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.49.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
docker compose config --quiet
docker compose build --no-cache
docker compose up -d --force-recreate
wait_healthy(){
  local container="$1"
  for i in $(seq 1 75); do
    local state
    state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || true)"
    [[ "$state" == "healthy" ]] && return 0
    [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=260; fail "$container state $state"; }
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
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'go_fabric':c.get('go_research_ingestion_job_fabric'),'backpressure':c.get('go_ingestion_backpressure'),'rust_query':c.get('native_graph_query_engine')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.49.0'
for key in ['go_research_ingestion_job_fabric','go_ingestion_concurrency','go_ingestion_retries','go_ingestion_cancellation','go_ingestion_backpressure','go_ingestion_worker_health','native_graph_query_engine','research_corpus_builder','research_corpus_deterministic_manifests','research_corpus_row_level_provenance','research_corpus_json_export','research_corpus_jsonl_export','research_corpus_csv_export','unified_research_runtime_contract','unified_runtime_discovery','unified_runtime_routing','unified_runtime_execution_envelopes','unified_runtime_explicit_fallback_policy','unified_runtime_python_semantics_authority']:
    assert c.get(key) is True, key
assert c.get('research_corpus_automatic_quality_judgment') is False
assert c.get('research_corpus_automatic_truth_promotion') is False
assert c.get('research_corpus_automatic_core_promotion') is False
assert c.get('go_ingestion_job_state_implies_source_validity') is False
assert c.get('go_ingestion_job_state_implies_evidence_truth') is False
assert c.get('go_ingestion_automatic_core_promotion') is False
assert c.get('unified_runtime_cross_runtime_equivalence_claim') is False
assert c.get('unified_runtime_automatic_core_promotion') is False
PY
# Unified runtime discovery and explicit routing must reflect all live production runtimes.
curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime-status.json"
python3 - "$TMP/runtime-status.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'schema':x.get('schema'),'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('schema')=='sc-library-research-runtime-contract/1.0'
assert x.get('backend_version')=='2.49.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['python'].get('available') is True
assert runtimes['go'].get('available') is True and runtimes['go'].get('runtime_version')=='0.1.0'
assert runtimes['rust'].get('available') is True and runtimes['rust'].get('runtime_version')=='0.2.0'
assert x.get('authority',{}).get('research_semantics')=='python-library-backend'
assert x.get('authority',{}).get('durable_governed_research_objects')=='platform-core'
assert x.get('guardrails',{}).get('cross_runtime_result_is_automatically_equivalent') is False
PY
cat > "$TMP/runtime-resolve.json" <<'JSON'
{"workload":"native-graph-query","runtime":"auto","allow_fallback":true}
JSON
curl -fsS -X POST "$BASE/v1/runtime/research/resolve" -H 'Content-Type: application/json' --data-binary @"$TMP/runtime-resolve.json" -o "$TMP/runtime-resolve-out.json"
python3 - "$TMP/runtime-resolve-out.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-runtime-routing-decision/1.0'
assert x.get('selected_runtime')=='rust' and x.get('execution_ready') is True
assert x.get('fallback_used') is False and x.get('routing_fingerprint_sha256')
PY
# Exercise the signed unified execution envelope across Python, Go and Rust.
docker exec -i sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
base='http://127.0.0.1:8080'; key=os.environ['SC_LIBRARY_BACKEND_API_KEY']; path='/v1/runtime/research/execute'
def signed(obj):
    body=json.dumps(obj,separators=(',',':')).encode(); ts=str(int(time.time())); digest=hashlib.sha256(body).hexdigest(); canonical=f'POST\n{path}\n{ts}\n{digest}'; sig=hmac.new(key.encode(),canonical.encode(),hashlib.sha256).hexdigest(); req=urllib.request.Request(base+path,data=body,method='POST',headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig,'Content-Type':'application/json','Accept':'application/json'}); return json.load(urllib.request.urlopen(req,timeout=15))
py=signed({'workload':'research-corpus-build','input':{'title':'Unified runtime deployment corpus','records':[{'record_id':'runtime:1','title':'Runtime record'}]}})
assert py['schema']=='sc-library-runtime-execution-envelope/1.0' and py['state']=='completed' and py['routing']['selected_runtime']=='python'; assert py['result']['schema']=='sc-library-research-corpus/1.0'
go=signed({'workload':'ingestion-job-submit','input':{'type':'metadata-extract','source_key':'runtime-contract-smoke','payload':{'record_id':'runtime:go'},'idempotency_key':'v2490-unified-go'}})
assert go['state']=='accepted' and go['routing']['selected_runtime']=='go' and go['result']['job']['state']=='queued'
rust=signed({'workload':'native-graph-query','runtime':'rust','input':{'corpus':{'nodes':[{'id':'finding:runtime','kind':'finding'},{'id':'pub:runtime','kind':'publication'}],'edges':[{'source':'finding:runtime','target':'pub:runtime','relationship_basis':'reviewed-finding-evidence','directed':False}]},'query':{'operation':'neighborhood','start_node_ids':['finding:runtime'],'max_depth':2}}})
assert rust['state']=='completed' and rust['routing']['selected_runtime']=='rust' and rust['result']['runtime']['used']=='rust'
for row in (py,go,rust):
    assert len(row['request_fingerprint_sha256'])==64 and len(row['result_fingerprint_sha256'])==64
    assert row['guardrails']['successful_execution_implies_truth'] is False
    assert row['guardrails']['automatic_platform_core_promotion'] is False
print(json.dumps({'python':py['state'],'go':go['state'],'rust':rust['result']['runtime']['used'],'runtime_contract':'PASS'},indent=2))
PY
cat > "$TMP/corpus.json" <<'JSON'
{"title":"Deployment corpus","records":[{"record_id":"deploy:r2","title":"Second deployment record","doi":"10.1000/deploy2","published_at":"2026-02-02","source_type":"journal-article","topics":["carbon"],"source_key":"deploy","source_hash":"hash2"},{"record_id":"deploy:r1","title":"First deployment record","doi":"10.1000/deploy1","published_at":"2025-01-01","source_type":"journal-article","topics":["carbon"],"source_key":"deploy","source_hash":"hash1"}],"selection":{"include_record_ids":["deploy:r1","deploy:r2"],"exclude_record_ids":["deploy:r2"],"human_reviewed":true},"fields":["record_id","title","doi"]}
JSON
curl -fsS -X POST "$BASE/v1/research-corpora/build" -H 'Content-Type: application/json' --data-binary @"$TMP/corpus.json" -o "$TMP/corpus-out.json"
python3 - "$TMP/corpus-out.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'corpus_id':x.get('corpus_id'),'selected':x.get('metrics',{}).get('selected_record_count'),'rows':x.get('metrics',{}).get('row_count')},indent=2))
assert x.get('schema')=='sc-library-research-corpus/1.0'
assert x.get('metrics',{}).get('selected_record_count')==1
assert x.get('rows',[{}])[0].get('record_id')=='deploy:r1'
assert len(x.get('row_provenance') or [])==1
assert x.get('guardrails',{}).get('corpus_membership_implies_truth') is False
assert x.get('guardrails',{}).get('automatic_platform_core_promotion') is False
PY
python3 - "$TMP/corpus-out.json" "$TMP/corpus-export-request.json" <<'PY'
import json,sys
corpus=json.load(open(sys.argv[1],encoding='utf-8'))
json.dump({'corpus':corpus,'export':{'format':'bundle','filename':'deployment-corpus'}},open(sys.argv[2],'w',encoding='utf-8'))
PY
curl -fsS -X POST "$BASE/v1/research-corpora/export" -H 'Content-Type: application/json' --data-binary @"$TMP/corpus-export-request.json" -o "$TMP/corpus-export-out.json"
python3 - "$TMP/corpus-export-out.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); e=x.get('exports',{})
print(json.dumps({'schema':x.get('schema'),'formats':sorted(e),'provenance_rows':len(x.get('row_provenance') or [])},indent=2))
assert x.get('schema')=='sc-library-dataset-export-package/1.0'
assert set(e)=={'json','jsonl','csv'}
assert all(e[k].get('sha256') for k in e)
assert 'deploy:r1' in e['csv'].get('content','')
PY
curl -fsS "$BASE/v1/runtime/ingestion-fabric/status" -o "$TMP/go-status.json"
python3 - "$TMP/go-status.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(d,indent=2))
assert d.get('schema')=='sc-library-go-ingestion-runtime/1.0'
assert d.get('runtime_version')=='0.1.0' and d.get('available') is True
r=d.get('reported',{}); assert r.get('version')=='0.1.0'; assert r.get('durability')=='state-file'
assert d.get('job_completion_implies_source_validity') is False
assert d.get('job_completion_implies_evidence_truth') is False
PY
# Exercise signed Python API -> Go queue -> worker claim/fail/retry/complete -> signed read.
docker exec -i sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
base='http://127.0.0.1:8080'; key=os.environ['SC_LIBRARY_BACKEND_API_KEY']
def signed(method,path,obj=None):
    body=b'' if obj is None else json.dumps(obj,separators=(',',':')).encode()
    ts=str(int(time.time())); digest=hashlib.sha256(body).hexdigest(); canonical=f'{method}\n{path}\n{ts}\n{digest}'
    sig=hmac.new(key.encode(),canonical.encode(),hashlib.sha256).hexdigest()
    req=urllib.request.Request(base+path,data=(body if method!='GET' else None),method=method,headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig,'Content-Type':'application/json','Accept':'application/json'})
    return json.load(urllib.request.urlopen(req,timeout=8))
def go(method,path,obj=None):
    body=None if obj is None else json.dumps(obj,separators=(',',':')).encode()
    req=urllib.request.Request('http://sc-library-ingestion:8090'+path,data=body,method=method,headers={'Content-Type':'application/json'})
    with urllib.request.urlopen(req,timeout=8) as r:
        raw=r.read().decode(); return json.loads(raw) if raw else {}
sub=signed('POST','/v1/ingestion-jobs',{'type':'ocr','source_key':'deploy-smoke','payload':{'record_id':'smoke-1'},'idempotency_key':'v2490-smoke','max_attempts':2})
job=sub['job']; jid=job['id']; assert job['state']=='queued'
claim=go('POST','/v1/jobs/claim',{'worker_id':'deploy-worker','types':['ocr']}); assert claim['job']['id']==jid and claim['job']['attempt']==1
failed=go('POST',f'/v1/jobs/{jid}/fail',{'worker_id':'deploy-worker','error':'intentional retry smoke test','retry_after_seconds':0}); assert failed['job']['state']=='retry_wait'
claim2=go('POST','/v1/jobs/claim',{'worker_id':'deploy-worker','types':['ocr']}); assert claim2['job']['id']==jid and claim2['job']['attempt']==2
complete=go('POST',f'/v1/jobs/{jid}/complete',{'worker_id':'deploy-worker','result':{'smoke_test':True}}); assert complete['job']['state']=='completed'
read=signed('GET',f'/v1/ingestion-jobs/{jid}'); assert read['job']['state']=='completed' and read['job']['attempt']==2
sub2=signed('POST','/v1/ingestion-jobs',{'type':'ocr','source_key':'deploy-smoke','payload':{'record_id':'smoke-1'},'idempotency_key':'v2490-smoke','max_attempts':2}); assert sub2['job']['id']==jid and sub2.get('deduplicated') is True
print(json.dumps({'job_id':jid,'state':read['job']['state'],'attempt':read['job']['attempt'],'idempotency':'PASS','retry':'PASS'},indent=2))
PY
# Queue one job, restart Go sidecar, and prove durable state survives.
DURABLE_JOB_ID="$(docker exec -i sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
key=os.environ['SC_LIBRARY_BACKEND_API_KEY']; path='/v1/ingestion-jobs'; body=json.dumps({'type':'metadata-extract','source_key':'deploy-durability','payload':{'record_id':'durable-1'},'idempotency_key':'v2490-durable'},separators=(',',':')).encode(); ts=str(int(time.time())); sig=hmac.new(key.encode(),f'POST\n{path}\n{ts}\n{hashlib.sha256(body).hexdigest()}'.encode(),hashlib.sha256).hexdigest(); req=urllib.request.Request('http://127.0.0.1:8080'+path,data=body,method='POST',headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig,'Content-Type':'application/json'}); print(json.load(urllib.request.urlopen(req,timeout=8))['job']['id'])
PY
)"
[[ -n "$DURABLE_JOB_ID" ]] || fail "durability smoke job was not created"
docker compose restart sc-library-ingestion >/dev/null
wait_healthy sc-library-ingestion
docker exec -i -e JOB_ID="$DURABLE_JOB_ID" sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
key=os.environ['SC_LIBRARY_BACKEND_API_KEY']; jid=os.environ['JOB_ID']; path=f'/v1/ingestion-jobs/{jid}'; body=b''; ts=str(int(time.time())); sig=hmac.new(key.encode(),f'GET\n{path}\n{ts}\n{hashlib.sha256(body).hexdigest()}'.encode(),hashlib.sha256).hexdigest(); req=urllib.request.Request('http://127.0.0.1:8080'+path,method='GET',headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig}); d=json.load(urllib.request.urlopen(req,timeout=8)); print(json.dumps({'durable_job':jid,'state':d['job']['state']},indent=2)); assert d['job']['id']==jid and d['job']['state']=='queued'
PY
# Rust v0.2 continuity and forced native query.
curl -fsS "$BASE/v1/runtime/native-graph/status" -o "$TMP/native.json"
python3 - "$TMP/native.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'available':d.get('available'),'reported_version':d.get('reported_version'),'query_schema':d.get('query_schema')},indent=2)); assert d.get('available') is True and d.get('reported_version')=='0.2.0'; assert d.get('query_schema')=='sc-library-native-graph-query/1.0'
PY
cat > "$TMP/native-query.json" <<'JSON'
{"corpus":{"nodes":[{"id":"finding:1","kind":"finding"},{"id":"pub:a","kind":"publication"},{"id":"pub:b","kind":"publication"},{"id":"topic:x","kind":"topic"}],"edges":[{"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":false},{"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":true},{"source":"pub:b","target":"topic:x","relationship_basis":"publication-topic-cooccurrence","directed":false,"analytical":true}]},"query":{"operation":"neighborhood","start_node_ids":["finding:1"],"max_depth":4,"runtime":"rust"}}
JSON
curl -fsS -X POST "$BASE/v1/runtime/native-graph/query" -H 'Content-Type: application/json' --data-binary @"$TMP/native-query.json" -o "$TMP/native-query-out.json"
python3 - "$TMP/native-query-out.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); ids={x.get('id') for x in d.get('nodes',[])}; print(json.dumps({'runtime':d.get('runtime'),'nodes':sorted(ids)},indent=2)); assert d.get('runtime',{}).get('used')=='rust'; assert {'finding:1','pub:a','pub:b'}.issubset(ids); assert 'topic:x' not in ids
PY
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PY
echo "PASS: Library backend v2.49.0 Unified Research Runtime Contract deployed and verified."
