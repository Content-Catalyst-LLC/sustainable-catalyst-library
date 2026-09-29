#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.59.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2590.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.59.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.59.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.59.0"
grep -q 'sc-library-cross-language-resolution-readiness/1.0' "$SRC/app/cross_language_resolution.py" || fail "resolution contract missing"
for table in library_cross_language_entities library_entity_name_forms library_entity_resolution_cases library_entity_resolution_candidates library_entity_resolution_decisions; do grep -q "$table" "$SRC/app/schema.sql" || fail "$table schema missing"; done
grep -q 'version  = "0.1.0"' "$SRC/go-ingestion-runtime/main.go" || fail "Go runtime identity mismatch"
grep -q 'version = "0.2.0"' "$SRC/native-graph-runtime/Cargo.toml" || fail "Rust runtime identity mismatch"
[[ ! -d "$SRC/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be shipped"
[[ ! -f "$SRC/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go binary must not be shipped"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.59.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' --exclude='native-graph-runtime/target' --exclude='go-ingestion-runtime/sc-library-ingestion-runtime' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
echo "=== DEPLOYING LIBRARY BACKEND v2.59.0 ==="
docker compose config --quiet
docker compose build --no-cache
docker compose up -d --force-recreate
wait_healthy(){ local c="$1"; for i in $(seq 1 90); do local s; s="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$c" 2>/dev/null || true)"; [[ "$s" == healthy ]] && return 0; [[ "$s" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=300; fail "$c state $s"; }; sleep 2; done; fail "$c did not become healthy"; }
wait_healthy sc-library-ingestion
wait_healthy sc-library-backend
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); c=x.get('capabilities',{})
print(json.dumps({'ok':x.get('ok'),'version':x.get('version'),'cross_language':{k:c.get(k) for k in ['cross_language_entity_resolution','cross_language_name_forms','historical_toponym_validity_windows','explicit_transliteration_forms','entity_resolution_ambiguity_preserved','entity_resolution_automatic_merge','entity_resolution_automatic_truth_promotion']}},indent=2))
assert x.get('ok') is True and x.get('version')=='2.59.0'
for k in ['cross_language_entity_resolution','cross_language_name_forms','historical_toponym_validity_windows','explicit_transliteration_forms','entity_resolution_ambiguity_preserved']: assert c.get(k) is True,k
for k in ['entity_resolution_automatic_merge','entity_resolution_automatic_truth_promotion']: assert c.get(k) is False,k
PY
curl -fsS "$BASE/v1/cross-language-resolution/readiness" -o "$TMP/ready.json"
python3 - "$TMP/ready.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); print(json.dumps(x,indent=2))
assert x.get('schema')=='sc-library-cross-language-resolution-readiness/1.0'
assert x.get('version')=='5.48.0' and x.get('backend_version')=='2.59.0' and x.get('state')=='ready'
g=x.get('guardrails',{}); assert g.get('automatic_resolution') is False and g.get('ambiguity_preserved') is True and g.get('candidate_score_is_probability') is False
PY
cat > "$TMP/authority.json" <<'JSON'
{"title":"deployment validation","entities":[{"entity_id":"entity:istanbul-validation","entity_type":"place","canonical_name":"İstanbul","names":[{"text":"İstanbul","relation_type":"canonical","language_bcp47":"tr","script_iso15924":"Latn","valid_from_year":1930},{"text":"Istanbul","relation_type":"transliteration","language_bcp47":"en","script_iso15924":"Latn","transliteration_system":"declared-English"},{"text":"Constantinople","relation_type":"historical","language_bcp47":"en","script_iso15924":"Latn","valid_to_year":1929}]}]}
JSON
python3 - "$TMP/authority.json" > "$TMP/case.json" <<'PY'
import json,sys
p=json.load(open(sys.argv[1])); print(json.dumps({'authority':p,'query':{'name':'Constantinople','year':1910},'limit':10}))
PY
curl -fsS -X POST "$BASE/v1/cross-language-resolution/case" -H 'Content-Type: application/json' --data-binary @"$TMP/case.json" -o "$TMP/case-out.json"
python3 - "$TMP/case-out.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); print(json.dumps({'case_id':x.get('case_id'),'candidate_count':x.get('candidate_count'),'candidate':(x.get('candidates') or [None])[0],'ambiguity':x.get('ambiguity')},indent=2))
assert x.get('candidate_count')==1; c=x['candidates'][0]
assert c.get('entity_id')=='entity:istanbul-validation' and c.get('temporal_status')=='within-window'
assert c.get('candidate_is_resolved_identity') is False and c.get('score_is_probability') is False
assert x.get('ambiguity',{}).get('automatic_decision') is False
PY
curl -fsS -X POST "$BASE/v1/cross-language-resolution/validate-authority" -H 'Content-Type: application/json' --data '{"automatic_resolution":true,"automatic_translation":true,"entities":[{"entity_type":"place","canonical_name":"X","names":[{"text":"X","relation_type":"canonical"}]}]}' -o "$TMP/unsafe.json"
python3 - "$TMP/unsafe.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); print(json.dumps({'valid':x.get('valid'),'errors':x.get('errors')},indent=2)); assert x.get('valid') is False; assert 'automatic-resolution-prohibited' in x.get('errors',[]); assert 'automatic-translation-prohibited' in x.get('errors',[])
PY
# Verify additive schema without writing any sample authority/case/decision.
docker exec -i sc-library-backend python - <<'PY'
from app.db import get_pool
required=['library_cross_language_entities','library_entity_name_forms','library_entity_resolution_cases','library_entity_resolution_candidates','library_entity_resolution_decisions']
with get_pool().connection() as conn, conn.cursor() as cur:
 out={}
 for table in required:
  cur.execute("SELECT to_regclass(%s) AS r",('public.'+table,)); assert cur.fetchone()['r'] is not None,table
  cur.execute(f'SELECT count(*) AS n FROM {table}'); out[table]=int(cur.fetchone()['n'])
 print({'cross_language_schema_tables':out})
PY
curl -fsS "$BASE/v1/linguistic-corpus/readiness" -o "$TMP/ling.json"
python3 - "$TMP/ling.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); assert x.get('state')=='ready' and x.get('version')=='5.47.0'; print({'linguistic_corpus':x.get('version'),'state':x.get('state')})
PY
curl -fsS "$BASE/v1/runtime/research/status" -o "$TMP/runtime.json"
python3 - "$TMP/runtime.json" <<'PY'
import json,sys
x=json.load(open(sys.argv[1])); r={i.get('engine'):i for i in x.get('runtimes',[])}; print(json.dumps({'backend_version':x.get('backend_version'),'runtimes':{k:{'version':v.get('runtime_version'),'available':v.get('available')} for k,v in r.items()}},indent=2)); assert x.get('backend_version')=='2.59.0'; assert r['go'].get('runtime_version')=='0.1.0' and r['go'].get('available') is True; assert r['rust'].get('runtime_version')=='0.2.0' and r['rust'].get('available') is True
PY
echo "PASS: Library backend v2.59.0 Cross-Language Entity, Name & Historical Toponym Resolution deployed and verified."
echo "NOTE: Deployment performed stateless candidate tests only; it persisted no sample authority, resolution case, candidate, or decision and invoked no translation/transliteration/model provider."
