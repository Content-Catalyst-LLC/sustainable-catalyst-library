#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.40.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2400.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.40.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.40.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.40.0"
grep -q 'sc-library-temporal-knowledge-evolution/1.0' "$SRC/app/temporal_knowledge.py" || fail "temporal contract missing"
grep -q 'later_event_projected_backward_by_default' "$SRC/app/temporal_knowledge.py" || fail "temporal guardrail missing"
grep -q 'sc-library-retrieval-evaluation/1.0' "$SRC/app/retrieval_evaluation.py" || fail "v2.39 retrieval contract missing"
grep -q 'sc-library-source-identity-resolution/1.0' "$SRC/app/source_identity_resolution.py" || fail "v2.38 identity contract missing"
grep -q 'sc-library-scientific-document-intelligence/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "v2.37 scientific contract missing"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v2.36 graph contract missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v2.35 synthesis contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.40.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
docker compose config --quiet
docker compose build
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 - "$TMP/health.json" <<'PYHEALTH'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); c=d.get('capabilities',{})
print(json.dumps({'ok':d.get('ok'),'version':d.get('version'),'database':d.get('database'),'temporal':c.get('temporal_knowledge_evolution'),'historical':c.get('temporal_historical_availability_snapshots'),'retrospective':c.get('temporal_retrospective_status_lens'),'retrieval':c.get('retrieval_evaluation'),'identity':c.get('source_identity_resolution')},indent=2))
assert d.get('ok') is True and d.get('version')=='2.40.0'
assert c.get('temporal_knowledge_evolution') is True
assert c.get('temporal_historical_availability_snapshots') is True
assert c.get('temporal_retrospective_status_lens') is True
assert c.get('temporal_later_events_projected_backward_by_default') is False
assert c.get('temporal_coincidence_implies_causality') is False
assert c.get('retrieval_evaluation') is True
assert c.get('source_identity_resolution') is True
assert c.get('scientific_document_intelligence') is True
assert c.get('publication_research_graph_query') is True
assert c.get('publication_cross_publication_evidence_synthesis') is True
PYHEALTH
cat > "$TMP/temporal.json" <<'JSONTEMP'
{"records":[{"record_id":"a","title":"Study A","published_at":"2019-01-01","indexed_at":"2019-01-02","metadata":{"temporal_events":[{"type":"correction","date":"2021-05-01"}]}},{"record_id":"b","title":"Study B","published_at":"2020-03-01","indexed_at":"2020-03-02","metadata":{"retracted_at":"2022-06-01"}},{"record_id":"c","title":"Study C","published_at":"2023-01-01"}],"as_of":"2020-12-31","lens":"historical-availability","from_date":"2019-12-31","to_date":"2023-12-31"}
JSONTEMP
curl -fsS -X POST "$BASE/v1/temporal-knowledge/analyze" -H 'Content-Type: application/json' --data-binary @"$TMP/temporal.json" -o "$TMP/temporal-out.json"
python3 - "$TMP/temporal-out.json" <<'PYTEMP'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); s=d.get('snapshot',{}); c=d.get('change_set',{})
print(json.dumps({'schema':d.get('schema'),'events':d.get('metrics',{}).get('event_count'),'snapshot_records':s.get('record_count'),'lens':s.get('lens'),'change_metrics':c.get('metrics'),'guardrails':d.get('guardrails')},indent=2))
assert d.get('schema')=='sc-library-temporal-knowledge-evolution/1.0'
assert s.get('record_count')==2 and s.get('lens')=='historical-availability'
assert {x.get('status_as_of') for x in s.get('records',[])}=={'active'}
assert c.get('metrics',{}).get('added')==2
assert d.get('guardrails',{}).get('later_event_projected_backward_by_default') is False
PYTEMP
curl -fsS -X POST "$BASE/v1/temporal-knowledge/analyze" -H 'Content-Type: application/json' --data '{"records":[{"record_id":"a","published_at":"2019-01-01","metadata":{"corrected_at":"2021-01-01"}}],"as_of":"2020-01-01","lens":"retrospective-status"}' -o "$TMP/retro.json"
python3 - "$TMP/retro.json" <<'PYRETRO'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); r=d.get('snapshot',{}).get('records',[{}])[0]
assert r.get('status_as_of')=='corrected'
assert d.get('snapshot',{}).get('guardrails',{}).get('retrospective_status_may_surface_later_corrections') is True
PYRETRO
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PYCORE'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); print(json.dumps({'core_version':d.get('core_version'),'reachable':d.get('reachable'),'ready':d.get('ready_capability_count'),'total':d.get('capability_count')},indent=2)); assert d.get('reachable') is True; assert int(d.get('ready_capability_count') or 0)>0
PYCORE
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=5' -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PYSEARCH'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8')); assert d.get('schema')=='sc-library-hybrid-retrieval/1.0'
PYSEARCH
echo "PASS: Library backend v2.40.0 Temporal Knowledge & Research Evolution deployed and verified."
