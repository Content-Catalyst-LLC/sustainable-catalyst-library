#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.24.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2240.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.24.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.24.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.24.0"
grep -q 'library_record_embeddings' "$SRC/app/schema.sql" || fail "semantic vector schema missing"
grep -q 'library_embedding_jobs' "$SRC/app/schema.sql" || fail "embedding job queue schema missing"
grep -q 'reciprocal_rank_fusion' "$SRC/app/hybrid_retrieval.py" || fail "hybrid retrieval engine missing"
grep -q 'library_core_bindings' "$SRC/app/hybrid_retrieval.py" || fail "Core-aware result enrichment missing"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.24.0-$stamp.tgz" "$(basename "$ROOT")"
fi

ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
[[ -f "$ROOT/.env" ]] || { cp "$SRC/.env.example" "$ROOT/.env"; chmod 600 "$ROOT/.env"; }

upsert_env(){
  local key="$1" value="$2"
  python3 - "$ROOT/.env" "$key" "$value" <<'PY'
from pathlib import Path
import sys
path=Path(sys.argv[1]); key=sys.argv[2]; value=sys.argv[3]
lines=path.read_text().splitlines() if path.exists() else []
out=[]; replaced=False
for line in lines:
    if line.startswith(key+'='):
        out.append(f'{key}={value}'); replaced=True
    else:
        out.append(line)
if not replaced: out.append(f'{key}={value}')
path.write_text('\n'.join(out).rstrip()+'\n')
PY
}

env_value(){
  grep -m1 "^$1=" "$ROOT/.env" 2>/dev/null | cut -d= -f2- || true
}

# Preserve/repair the v5.12 Platform Core bridge configuration.
upsert_env SC_LIBRARY_PLATFORM_CORE_URL "${SC_LIBRARY_PLATFORM_CORE_URL:-$(env_value SC_LIBRARY_PLATFORM_CORE_URL)}"
if [[ -z "$(env_value SC_LIBRARY_PLATFORM_CORE_URL)" ]]; then upsert_env SC_LIBRARY_PLATFORM_CORE_URL "https://core.sustainablecatalyst.com"; fi
if [[ -z "$(env_value SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS)" ]]; then upsert_env SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS "8"; fi
if [[ -z "$(env_value SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS)" ]]; then upsert_env SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS "5"; fi

LIB_CORE_KEY="$(env_value SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY)"
if [[ -z "$LIB_CORE_KEY" ]] && docker ps --format '{{.Names}}' | grep -qx sc-core; then
  CORE_KEY="$(docker exec sc-core sh -lc 'printf %s "$SC_CORE_WRITE_API_KEY"' 2>/dev/null || true)"
  if [[ -n "$CORE_KEY" ]]; then
    upsert_env SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY "$CORE_KEY"
    unset CORE_KEY
  fi
fi
LIB_CORE_KEY="$(env_value SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY)"
[[ -n "$LIB_CORE_KEY" ]] || fail "Platform Core write key unavailable. Set SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY in $ROOT/.env to the same server credential used by SC_CORE_WRITE_API_KEY, then rerun."
unset LIB_CORE_KEY

# v5.13 retrieval defaults. No new credential is required to deploy: semantic
# search stays explicitly disabled/fallback-only until a real provider is set.
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_PROVIDER)" ]]; then
  if [[ -n "$(env_value SC_LIBRARY_EMBEDDING_API_KEY)" || -n "$(env_value GEMINI_API_KEY)" ]]; then
    upsert_env SC_LIBRARY_EMBEDDING_PROVIDER "gemini"
  else
    upsert_env SC_LIBRARY_EMBEDDING_PROVIDER "disabled"
  fi
fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_MODEL)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_MODEL "gemini-embedding-2"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_DIMENSIONS)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_DIMENSIONS "768"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_TIMEOUT_SECONDS)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_TIMEOUT_SECONDS "12"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_MAX_ATTEMPTS)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_MAX_ATTEMPTS "5"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_WORKER_ENABLED)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_WORKER_ENABLED "true"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_WORKER_INTERVAL_SECONDS)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_WORKER_INTERVAL_SECONDS "30"; fi
if [[ -z "$(env_value SC_LIBRARY_EMBEDDING_WORKER_BATCH_SIZE)" ]]; then upsert_env SC_LIBRARY_EMBEDDING_WORKER_BATCH_SIZE "10"; fi
if [[ -z "$(env_value SC_LIBRARY_HYBRID_CANDIDATE_MULTIPLIER)" ]]; then upsert_env SC_LIBRARY_HYBRID_CANDIDATE_MULTIPLIER "4"; fi
if [[ -z "$(env_value SC_LIBRARY_HYBRID_RRF_K)" ]]; then upsert_env SC_LIBRARY_HYBRID_RRF_K "60"; fi
if [[ -z "$(env_value SC_LIBRARY_HYBRID_LEXICAL_WEIGHT)" ]]; then upsert_env SC_LIBRARY_HYBRID_LEXICAL_WEIGHT "1.0"; fi
if [[ -z "$(env_value SC_LIBRARY_HYBRID_SEMANTIC_WEIGHT)" ]]; then upsert_env SC_LIBRARY_HYBRID_SEMANTIC_WEIGHT "1.0"; fi
chmod 600 "$ROOT/.env"

cd "$ROOT"
echo "=== BUILD / RECREATE LIBRARY BACKEND ==="
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  if [[ "$state" =~ ^(unhealthy|exited|dead)$ ]]; then docker compose logs --tail=180; fail "sc-library-backend entered state $state"; fi
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || { docker compose logs --tail=180; fail "sc-library-backend did not become healthy"; }

BASE=http://127.0.0.1:8087

echo "=== LIBRARY HEALTH ==="
health="$(curl -fsS "$BASE/health")"
printf '%s\n' "$health" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.24.0",d; c=d.get("capabilities",{}); assert c.get("hybrid_retrieval") is True; assert c.get("semantic_vector_store") is True; assert c.get("core_aware_search_results") is True; assert c.get("platform_core_research_bridge") is True' "$health"

echo "=== HYBRID RETRIEVAL READINESS ==="
retrieval="$(curl -fsS "$BASE/v1/search/readiness")"
printf '%s\n' "$retrieval" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("hybrid_retrieval") is True,d; assert d.get("lexical_retrieval") is True,d; assert d.get("core_aware_results") is True,d; s=d.get("semantic",{}); assert s.get("fake_embeddings") is False,d; assert s.get("platform_core_owns_vectors") is False,d' "$retrieval"

echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("configured") is True,d; assert d.get("reachable") is True,d; assert d.get("capability_count")==8,d; assert d.get("ready_capability_count")==8,d; p=d.get("promotion_policy",{}); assert p.get("raw_chunks_remain_library_local") is True; assert p.get("automatic_truth_promotion") is False' "$bridge"

echo "=== VERIFYING ADDITIVE RETRIEVAL / CORE TABLES ==="
docker exec -i sc-library-backend python - <<'PY'
import os
import psycopg
url=os.environ['DATABASE_URL']
with psycopg.connect(url) as conn, conn.cursor() as cur:
    for table in ('library_core_bindings','library_core_sync_outbox','library_record_embeddings','library_embedding_jobs'):
        cur.execute('SELECT to_regclass(%s)',(f'public.{table}',))
        value=cur.fetchone()[0]
        assert value==table,(table,value)
    cur.execute("SELECT count(*) FROM library_embedding_jobs")
    queued=cur.fetchone()[0]
print(f'PASS: Core bridge + semantic retrieval tables present; embedding job rows={queued}')
PY

echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
printf '%s\n' "$search" | python3 -m json.tool | head -120
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; r=d.get("retrieval",{}); assert r.get("requested_mode")=="hybrid",d; assert r.get("effective_mode") in {"hybrid","lexical-fallback"},d; assert r.get("platform_core_enrichment") is True,d' "$search"

semantic_enabled="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); print("yes" if d.get("semantic_retrieval") else "no")' "$retrieval")"
if [[ "$semantic_enabled" == "yes" ]]; then
  echo "PASS: semantic provider is configured; background embedding worker is active."
else
  echo "INFO: semantic provider is not configured. v5.13 is healthy in explicit lexical-fallback mode."
  echo "INFO: to activate Gemini later, set SC_LIBRARY_EMBEDDING_PROVIDER=gemini and SC_LIBRARY_EMBEDDING_API_KEY in $ROOT/.env, then recreate the service."
fi

echo "PASS: Library backend v2.24.0 Hybrid Research Retrieval & Core-Aware Results deployed."
