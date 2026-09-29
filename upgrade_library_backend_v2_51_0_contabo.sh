#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.51.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2510.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.51.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.51.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.51.0"
[[ -f "$SRC/app/embedding_governance.py" ]] || fail "embedding governance module missing"
grep -q 'sc-library-embedding-governance/1.0' "$SRC/app/embedding_governance.py" || fail "embedding governance contract missing"
grep -q 'library_embedding_compute_handoffs' "$SRC/app/schema.sql" || fail "Workspace embedding handoff migration missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.51.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
if grep -q '^SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=' .env 2>/dev/null; then
  grep '^SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=' .env
else
  echo "SC_LIBRARY_EMBEDDING_COMPUTE_TARGET is unset -> backend default is local (safe compatibility mode)"
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
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'embedding_governance':c.get('scientific_embedding_governance'),'workspace_handoff':c.get('workspace_embedding_compute_handoff'),'go_runtime':c.get('go_research_ingestion_job_fabric'),'rust_query':c.get('native_graph_query_engine')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.51.0'
for key in [
 'scientific_embedding_governance','embedding_specification_provenance','embedding_representation_lineage',
 'embedding_deterministic_backfill','workspace_embedding_compute_handoff','workspace_embedding_result_ingestion',
 'go_research_ingestion_job_fabric','native_graph_query_engine','research_corpus_builder','unified_research_runtime_contract',
 'cross_runtime_reproducibility_execution_lineage']:
    assert c.get(key) is True, key
for key in ['embedding_automatic_evidence_promotion','embedding_automatic_truth_promotion','embedding_automatic_core_promotion']:
    assert c.get(key) is False, key
PY

curl -fsS "$BASE/v1/embeddings/specification" -o "$TMP/spec.json"
python3 - "$TMP/spec.json" <<'PY'
import json,sys
s=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':s.get('schema'),'specification_id':s.get('specification_id'),'provider':s.get('provider'),'model':s.get('model'),'dimensions':s.get('dimensions'),'configured':s.get('configured')},indent=2))
assert s.get('schema')=='sc-core-compatible-embedding-specification/1.0'
assert len(s.get('fingerprint_sha256') or '')==64
assert s.get('normalization')=='l2-unit' and s.get('similarity_metric')=='cosine'
g=s.get('governance') or {}
assert g.get('library_owns_operational_vector_index') is True
assert g.get('platform_core_owns_governed_representation_contracts') is True
assert g.get('embedding_is_evidence') is False and g.get('similarity_is_truth') is False
PY

curl -fsS "$BASE/v1/embeddings/governance/readiness" -o "$TMP/governance.json"
python3 - "$TMP/governance.json" <<'PY'
import json,sys
g=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':g.get('schema'),'state':g.get('state'),'compute_policy':g.get('compute_policy'),'stored':g.get('stored_representations'),'current_spec':g.get('current_specification_representations'),'handoffs':g.get('workspace_handoff_counts')},indent=2))
assert g.get('schema')=='sc-library-embedding-governance/1.0'
assert g.get('state')=='ready'
assert g.get('contracts',{}).get('workspace_handoff')=='sc-library-workspace-embedding-handoff/1.0'
assert g.get('guardrails',{}).get('embedding_is_evidence') is False
assert g.get('guardrails',{}).get('automatic_core_promotion') is False
PY

# Verify the migration was applied without deleting the existing vector store.
docker exec -i sc-library-backend python - <<'PY'
import os,psycopg,json
with psycopg.connect(os.environ['DATABASE_URL']) as conn, conn.cursor() as cur:
    cur.execute("SELECT column_name FROM information_schema.columns WHERE table_name='library_record_embeddings'")
    emb={r[0] for r in cur.fetchall()}
    cur.execute("SELECT column_name FROM information_schema.columns WHERE table_name='library_embedding_jobs'")
    jobs={r[0] for r in cur.fetchall()}
    cur.execute("SELECT to_regclass('public.library_embedding_compute_handoffs')")
    handoff=cur.fetchone()[0]
required_emb={'specification_fingerprint','representation_id','execution_target','execution_id','provenance'}
required_jobs={'specification_fingerprint','execution_target','execution_id','handoff_id','provenance'}
print(json.dumps({'embedding_columns':sorted(required_emb & emb),'job_columns':sorted(required_jobs & jobs),'handoff_table':handoff},indent=2))
assert required_emb <= emb
assert required_jobs <= jobs
assert handoff=='library_embedding_compute_handoffs'
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); s=x.get('semantic') or {}; g=x.get('embedding_governance') or {}
print(json.dumps({'semantic_configured':s.get('configured'),'indexed':s.get('indexed_records'),'current':s.get('current_indexed_records'),'stale':s.get('stale_indexed_records'),'compute_target':g.get('compute_target')},indent=2))
assert 'current_indexed_records' in s and 'stale_indexed_records' in s
assert g.get('enabled') is True and len(g.get('specification_fingerprint_sha256') or '')==64
assert g.get('automatic_truth_promotion') is False
PY

# Signed dry-run only: report backfill candidates but do not queue or recompute anything.
docker exec -i sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
key=os.environ['SC_LIBRARY_BACKEND_API_KEY']; path='/v1/admin/embeddings/backfill'; body=b''; ts=str(int(time.time()))
sig=hmac.new(key.encode(),f'POST\n{path}\n{ts}\n{hashlib.sha256(body).hexdigest()}'.encode(),hashlib.sha256).hexdigest()
url='http://127.0.0.1:8080'+path+'?dry_run=true&limit=10000'
req=urllib.request.Request(url,data=body,method='POST',headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig,'Content-Type':'application/json','Accept':'application/json'})
x=json.load(urllib.request.urlopen(req,timeout=20))
print(json.dumps({'schema':x.get('schema'),'dry_run':x.get('dry_run'),'candidate_count':x.get('candidate_count'),'queued':x.get('queued'),'execution_target':x.get('execution_target')},indent=2))
assert x.get('schema')=='sc-library-embedding-backfill/1.0'
assert x.get('dry_run') is True and x.get('queued')==0
PY

# Signed handoff status proves the durable queue endpoint without creating work.
docker exec -i sc-library-backend python - <<'PY'
import hashlib,hmac,json,os,time,urllib.request
key=os.environ['SC_LIBRARY_BACKEND_API_KEY']; path='/v1/admin/embeddings/handoffs'; body=b''; ts=str(int(time.time()))
sig=hmac.new(key.encode(),f'GET\n{path}\n{ts}\n{hashlib.sha256(body).hexdigest()}'.encode(),hashlib.sha256).hexdigest()
req=urllib.request.Request('http://127.0.0.1:8080'+path+'?limit=10',method='GET',headers={'Authorization':'Bearer '+key,'X-SC-Timestamp':ts,'X-SC-Signature':sig,'Accept':'application/json'})
x=json.load(urllib.request.urlopen(req,timeout=10)); print(json.dumps({'schema':x.get('schema'),'counts':x.get('counts')},indent=2))
assert x.get('schema')=='sc-library-workspace-embedding-handoff/1.0'
PY

# Preserve v5.39 runtime continuity.
curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.51.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['python'].get('available') is True
assert runtimes['go'].get('available') is True and runtimes['go'].get('runtime_version')=='0.1.0'
assert runtimes['rust'].get('available') is True and runtimes['rust'].get('runtime_version')=='0.2.0'
assert x.get('guardrails',{}).get('cross_runtime_result_is_automatically_equivalent') is False
PY

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2))
assert d.get('reachable') is True
assert int(d.get('ready_capability_count') or 0)>0
PY

echo "PASS: Library backend v2.51.0 Scientific Embedding Governance & Compute Handoff deployed and verified."
echo "NOTE: Backfill was DRY RUN ONLY. Queue it explicitly after reviewing candidate_count."
