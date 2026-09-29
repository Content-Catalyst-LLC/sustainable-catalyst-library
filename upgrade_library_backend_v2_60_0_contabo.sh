#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.60.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2600.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"; unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.60.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.60.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.60.0"
grep -q 'sc-library-execution-fabric-readiness/1.0' "$SRC/app/durable_job_queue.py" || fail "execution fabric contract missing"
for table in library_research_jobs library_research_job_attempts library_research_job_events; do grep -q "$table" "$SRC/app/schema.sql" || fail "$table schema missing"; done
grep -q 'redis>=5.2,<7' "$SRC/requirements.txt" || fail "redis Python dependency missing"
grep -q 'sc-library-redis:' "$SRC/compose.yml" || fail "Redis compose service missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go runtime identity mismatch"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust runtime identity mismatch"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go binary must not be shipped"
mkdir -p "$BACKUP_ROOT"; stamp="$(date +%Y%m%d-%H%M%S)"; if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.60.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; echo "=== DEPLOYING LIBRARY BACKEND v2.60.0 ==="; docker compose config --quiet; docker compose build --no-cache; docker compose up -d --force-recreate
wait_healthy(){ local c="$1"; for i in $(seq 1 90); do local s; s="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$c" 2>/dev/null || true)"; [[ "$s" == healthy ]] && return 0; [[ "$s" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=300; fail "$c state $s"; }; sleep 2; done; fail "$c did not become healthy"; }
wait_healthy sc-library-redis; wait_healthy sc-library-ingestion; wait_healthy sc-library-backend
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); c=x.get('capabilities',{}); print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'execution':{k:c.get(k) for k in ['durable_research_job_queue','durable_research_execution_state','research_job_postgresql_authority','research_job_redis_dispatch','research_job_redis_authoritative','research_job_worker_fleet_active']}},indent=2)); assert x.get('ok') is True and x.get('version')=='2.60.0'; assert c.get('durable_research_job_queue') is True and c.get('research_job_postgresql_authority') is True and c.get('research_job_redis_authoritative') is False and c.get('research_job_worker_fleet_active') is False
PY
curl -fsS "$BASE/v1/execution-fabric/readiness" -o "$TMP/ready.json"
python3 - "$TMP/ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); print(json.dumps(x,indent=2)); assert x.get('schema')=='sc-library-execution-fabric-readiness/1.0'; assert x.get('version')=='5.49.0' and x.get('backend_version')=='2.60.0'; assert x.get('state')=='ready'; assert x.get('postgresql',{}).get('authoritative') is True; assert x.get('redis',{}).get('available') is True and x.get('redis',{}).get('authoritative') is False; assert x.get('capabilities',{}).get('worker_fleet_active') is False
PY
curl -fsS -X POST "$BASE/v1/execution-fabric/validate-job" -H 'Content-Type: application/json' --data '{"job_type":"pdf-ingest","capability":"document.ingest","requested_runtime":"auto","priority":10,"input_manifest":{"source_uri":"https://example.org/test.pdf"},"provenance_context":{"purpose":"deployment-validation"}}' -o "$TMP/job-validation.json"
python3 - "$TMP/job-validation.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); print(json.dumps({'valid':x.get('valid'),'normalized':x.get('normalized'),'guardrails':x.get('guardrails')},indent=2)); assert x.get('valid') is True; n=x.get('normalized',{}); assert n.get('job_id','').startswith('research-job:'); g=x.get('guardrails',{}); assert g.get('postgresql_is_authoritative_job_state') is True and g.get('redis_is_authoritative_job_state') is False and g.get('worker_fleet_activated_by_this_release') is False
PY
# Verify Redis itself and additive schema; do not persist a sample job.
docker exec sc-library-redis redis-cli ping | grep -q PONG
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
required=['library_research_jobs','library_research_job_attempts','library_research_job_events']
with get_pool().connection() as conn, conn.cursor() as cur:
 out={}
 for table in required:
  cur.execute('SELECT to_regclass(%s) AS r',('public.'+table,)); assert cur.fetchone()['r'] is not None,table
  cur.execute(f'SELECT count(*) AS n FROM {table}'); out[table]=int(cur.fetchone()['n'])
 print({'execution_schema_tables':out})
PY
curl -fsS "$BASE/v1/cross-language-resolution/readiness" -o "$TMP/lang.json"
python3 - "$TMP/lang.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); assert x.get('state')=='ready' and x.get('version')=='5.48.0'; print({'cross_language':x.get('version'),'state':x.get('state')})
PY
curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); r={i.get('engine'):i for i in x.get('runtimes',[])}; print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in r.items()}},indent=2)); assert x.get('backend_version')=='2.60.0'; assert r['go'].get('runtime_version')=='0.1.0' and r['go'].get('available') is True; assert r['rust'].get('runtime_version')=='0.2.0' and r['rust'].get('available') is True
PY
echo "PASS: Library backend v2.60.0 Durable Research Job Queue & Execution State deployed and verified."
echo "NOTE: Deployment performed stateless validation only; it persisted no sample research job. PostgreSQL is authoritative, Redis is dispatch coordination, and specialized worker pools remain deferred to v5.50.0."
