#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.55.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2550.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.55.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.55.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.55.0"
[[ -f "$SRC/app/global_source_federation.py" ]] || fail "global source federation module missing"
grep -q 'sc-library-global-source-federation-registry/1.0' "$SRC/app/global_source_federation.py" || fail "global source federation registry contract missing"
grep -q 'sc-library-global-source-connector-contract/1.0' "$SRC/app/global_source_federation.py" || fail "connector contract missing"
grep -q 'sc-library-publication-embedding-map/1.0' "$SRC/app/publication_embedding_maps.py" || fail "preserved v5.43 embedding map contract missing"
grep -q 'sc-library-neural-reranking/1.0' "$SRC/app/neural_reranking.py" || fail "preserved v5.42 neural reranking contract missing"
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go ingestion runtime is not v0.1.0"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust graph runtime is not v0.2.0"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go artifact must not be shipped"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.55.0-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"

echo "=== CONFIGURATION ==="
echo "v5.44.0 adds registry/contracts only; it does not add or rotate connector credentials."
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
 'global_source_federation_registry','global_source_connector_contracts',
 'global_source_registry_reuses_legacy_connectors','global_source_registry_reuses_v4_8_federation_transport',
 'publication_embedding_maps','neural_reranking','semantic_similarity_representation_search',
 'scientific_embedding_governance','go_research_ingestion_job_fabric','native_graph_query_engine'
]
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'capabilities':{k:c.get(k) for k in keys}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.55.0'
for key in keys: assert c.get(key) is True, key
for key in [
 'global_source_registry_parallel_execution_stack','global_source_registry_membership_implies_endorsement',
 'global_source_registry_membership_implies_partnership','global_source_connector_health_implies_source_quality',
 'global_source_connector_health_implies_evidence_truth','global_source_automatic_import',
 'global_source_automatic_evidence_promotion','global_source_automatic_truth_promotion',
 'global_source_automatic_platform_core_promotion','global_source_automatic_translation'
]: assert c.get(key) is False, key
assert c.get('global_source_original_language_preserved_as_received') is True
PY

curl -fsS "$BASE/v1/global-source-federation/readiness" -o "$TMP/federation-ready.json"
python3 - "$TMP/federation-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-global-source-federation-readiness/1.0'
assert x.get('state')=='ready' and x.get('version')=='5.44.0'
assert x.get('sources')==26 and x.get('connector_contracts')==26 and x.get('collections')==6
assert len(x.get('registry_fingerprint_sha256',''))==64
g=x.get('governance') or {}
assert g.get('legacy_v4_8_federation_transport_reused') is True
assert g.get('legacy_v2_6_scholarly_connectors_reused') is True
assert g.get('parallel_connector_execution_stack_created') is False
assert g.get('registry_membership_implies_endorsement') is False
assert g.get('automatic_truth_promotion') is False
assert g.get('original_language_preserved_as_received') is True
assert g.get('automatic_translation') is False
PY

curl -fsS "$BASE/v1/global-source-federation/registry" -o "$TMP/registry.json"
python3 - "$TMP/registry.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
print(json.dumps({'schema':x.get('schema'),'counts':x.get('counts'),'fingerprint':x.get('registry_fingerprint_sha256')},indent=2))
assert x.get('schema')=='sc-library-global-source-federation-registry/1.0'
assert (x.get('counts') or {}).get('sources')==26
sources=x.get('sources') or []; connectors=x.get('connectors') or []
assert len(sources)==26 and len(connectors)==26
assert len({s.get('source_id') for s in sources})==26
assert len({c.get('connector_id') for c in connectors})==26
assert all(c.get('language_policy')=='preserve-as-received' and c.get('translation_behavior')=='none' for c in connectors)
assert all(c.get('automatic_truth_promotion') is False for c in connectors)
PY

curl -fsS "$BASE/v1/global-source-federation/registry?authority=browser-handoff" -o "$TMP/browser.json"
python3 - "$TMP/browser.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
ids={s.get('source_id') for s in x.get('sources') or []}
print(json.dumps({'browser_handoff_sources':sorted(ids)},indent=2))
assert ids=={'google-scholar','worldcat'}
PY

curl -fsS "$BASE/v1/global-source-federation/sources/johns-hopkins-dataverse" -o "$TMP/jhu.json"
python3 - "$TMP/jhu.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8'))
assert (x.get('source') or {}).get('source_id')=='johns-hopkins-dataverse'
assert (x.get('connector') or {}).get('execution_authority')=='python-backend-institutional'
print(json.dumps({'source':(x.get('source') or {}).get('name'),'connector':(x.get('connector') or {}).get('connector_id')},indent=2))
PY

curl -fsS "$BASE/v1/global-source-federation/connectors/python-jhu-dataverse" -o "$TMP/jhu-connector.json"
python3 - "$TMP/jhu-connector.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); c=x.get('connector') or {}
assert c.get('schema')=='sc-library-global-source-connector-contract/1.0'
assert c.get('source_id')=='johns-hopkins-dataverse'
assert c.get('raw_source_preservation') is True
assert c.get('automatic_import') is False
assert c.get('automatic_evidence_promotion') is False
print(json.dumps({'connector':c.get('connector_id'),'capabilities':c.get('capabilities')},indent=2))
PY

curl -fsS -X POST "$BASE/v1/global-source-federation/connectors/validate" \
  -H 'Content-Type: application/json' \
  --data '{"connector_id":"deployment-check","source_id":"deployment-source","execution_authority":"python-backend-test","transport":"rest-json","capabilities":["search","metadata"],"authentication":"none","pagination":"cursor","rate_limit_policy":"bounded","provenance_fields":["source_id","source_record_id","retrieved_at"]}' \
  -o "$TMP/validate.json"
python3 - "$TMP/validate.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'schema':x.get('schema'),'valid':x.get('valid'),'fingerprint':x.get('contract_fingerprint_sha256')},indent=2))
assert x.get('schema')=='sc-library-global-source-connector-validation/1.0'
assert x.get('valid') is True and not x.get('errors')
assert (x.get('normalized_contract') or {}).get('automatic_truth_promotion') is False
PY

curl -fsS -X POST "$BASE/v1/global-source-federation/connectors/validate" \
  -H 'Content-Type: application/json' \
  --data '{"connector_id":"unsafe-check","source_id":"deployment-source","execution_authority":"test","transport":"rest-json","capabilities":["search"],"authentication":"none","pagination":"none","rate_limit_policy":"bounded","provenance_fields":["source_id"],"automatic_truth_promotion":true}' \
  -o "$TMP/unsafe.json"
python3 - "$TMP/unsafe.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'valid':x.get('valid'),'errors':x.get('errors'),'violations':x.get('guardrail_violations')},indent=2))
assert x.get('valid') is False
assert 'governance-guardrail-violation' in (x.get('errors') or [])
assert 'automatic_truth_promotion' in (x.get('guardrail_violations') or [])
PY

curl -fsS "$BASE/v1/search/readiness" -o "$TMP/search-ready.json"
python3 - "$TMP/search-ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); fed=x.get('global_source_federation') or {}
print(json.dumps({'hybrid_retrieval':x.get('hybrid_retrieval'),'federation_state':fed.get('state'),'map_contract':x.get('publication_embedding_map_contract'),'rerank_contract':x.get('neural_reranking_contract')},indent=2))
assert x.get('hybrid_retrieval') is True
assert x.get('global_source_federation_contract')=='sc-library-global-source-federation-registry/1.0'
assert x.get('global_source_connector_contract')=='sc-library-global-source-connector-contract/1.0'
assert fed.get('state')=='ready'
assert x.get('publication_embedding_map_contract')=='sc-library-publication-embedding-map/1.0'
assert x.get('neural_reranking_contract')=='sc-library-neural-reranking/1.0'
PY

curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1],encoding='utf-8')); runtimes={r.get('engine'):r for r in x.get('runtimes',[])}
print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in runtimes.items()}},indent=2))
assert x.get('backend_version')=='2.55.0'
assert set(runtimes)=={'python','go','rust'}
assert runtimes['go'].get('runtime_version')=='0.1.0' and runtimes['go'].get('available') is True
assert runtimes['rust'].get('runtime_version')=='0.2.0' and runtimes['rust'].get('available') is True
PY

echo "PASS: Library backend v2.55.0 Global Source Federation Registry & Connector Contracts deployed and verified."
echo "NOTE: No connector credentials were added or rotated; no source was queried; no embedding backfill or external model request was initiated."
