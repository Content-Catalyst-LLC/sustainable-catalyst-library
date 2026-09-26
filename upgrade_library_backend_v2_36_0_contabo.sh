#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.36.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2360.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.36.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.36.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.36.0"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "research graph query contract missing"
grep -q 'sc-library-evidence-pathfinding/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "evidence pathfinding contract missing"
grep -q 'ANALYTICAL_RELATIONSHIPS' "$SRC/app/research_graph_pathfinding.py" || fail "analytical relationship policy missing"
grep -q '@app.post("/v1/publication-knowledge-maps/research-graph-query")' "$SRC/app/main.py" || fail "research graph query route missing"
grep -q '@app.post("/v1/publication-knowledge-maps/evidence-pathfind")' "$SRC/app/main.py" || fail "evidence pathfinding route missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v5.24 evidence synthesis contract missing"
grep -q 'sc-library-visual-evidence-trace/1.0' "$SRC/app/visual_evidence_trace.py" || fail "visual evidence trace contract missing"
grep -q 'sc-library-visual-research-session/1.0' "$SRC/app/visual_research_sessions.py" || fail "visual research session contract missing"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.36.0-$stamp.tgz" "$(basename "$ROOT")"
fi

ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then
  ENV_TMP="$TMP/existing.env"
  cp "$ROOT/.env" "$ENV_TMP"
fi
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
python3 - "$TMP/health.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
c=d.get("capabilities",{})
print(json.dumps({
  "ok":d.get("ok"),
  "version":d.get("version"),
  "database":d.get("database"),
  "research_graph_query":c.get("publication_research_graph_query"),
  "evidence_pathfinding":c.get("publication_evidence_pathfinding"),
  "direction_aware":c.get("publication_direction_aware_graph_traversal"),
  "analytical_opt_in":c.get("publication_analytical_path_edges_opt_in"),
},indent=2))
assert d.get("ok") is True
assert d.get("version")=="2.36.0"
assert c.get("release_certification_alignment") is True
assert c.get("publication_cross_publication_evidence_synthesis") is True
assert c.get("publication_research_graph_query") is True
assert c.get("publication_evidence_pathfinding") is True
assert c.get("publication_direction_aware_graph_traversal") is True
assert c.get("publication_analytical_path_edges_opt_in") is True
PY

CORPUS='{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":false,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}'
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data "$CORPUS" -o "$TMP/corpus.json"
python3 - "$TMP/corpus.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
s=d.get("evidence_synthesis",{})
g=d.get("research_graph",{})
print(json.dumps({
  "schema":d.get("schema"),
  "publications":d.get("metrics",{}).get("publication_count"),
  "synthesis_schema":s.get("schema"),
  "research_graph_schema":g.get("schema"),
  "nodes":g.get("node_count"),
  "edges":g.get("edge_count"),
},indent=2))
assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"
assert s.get("schema")=="sc-library-cross-publication-evidence-synthesis/1.0"
i=s.get("interpretation",{})
assert i.get("consensus_inferred") is False
assert i.get("hypotheses_inferred") is False
assert i.get("competing_hypotheses_require_explicit_metadata") is True
assert i.get("evidence_balance_is_truth_score") is False
assert i.get("synthesis_creates_new_claims") is False
assert s.get("platform_core",{}).get("durable_synthesis_authority")=="platform-core"
assert g.get("schema")=="sc-library-research-graph-query/1.0"
gi=g.get("interpretation",{})
assert gi.get("graph_path_implies_truth") is False
assert gi.get("graph_path_implies_causality") is False
assert gi.get("graph_path_implies_consensus") is False
assert gi.get("support_requires_explicit_reviewed_relation") is True
assert gi.get("contradiction_requires_explicit_reviewed_relation") is True
assert gi.get("analytical_edges_are_evidence_relations") is False
PY

curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/research-graph-query" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data "{\"corpus_request\":$CORPUS,\"query\":{\"text\":\"\",\"kinds\":[],\"include_analytical\":false,\"neighborhood_depth\":0,\"limit\":10}}" \
  -o "$TMP/graph-query.json"
python3 - "$TMP/graph-query.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
assert d.get("schema")=="sc-library-research-graph-query/1.0"
assert d.get("query",{}).get("include_analytical") is False
assert d.get("interpretation",{}).get("query_is_deterministic") is True
assert d.get("interpretation",{}).get("analytical_edges_opt_in") is True
assert d.get("interpretation",{}).get("results_create_new_claims") is False
PY

curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/evidence-pathfind" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data "{\"corpus_request\":$CORPUS,\"query\":{\"start_node_ids\":[],\"target_node_ids\":[],\"target_kinds\":[\"publication\"],\"include_analytical\":false,\"max_hops\":4,\"max_paths\":12,\"direction\":\"both\"}}" \
  -o "$TMP/pathfind.json"
python3 - "$TMP/pathfind.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
assert d.get("schema")=="sc-library-evidence-pathfinding/1.0"
assert d.get("query",{}).get("include_analytical") is False
i=d.get("interpretation",{})
assert i.get("path_is_deterministic_graph_traversal") is True
assert i.get("path_creates_new_claims") is False
assert i.get("path_implies_truth") is False
assert i.get("path_implies_causality") is False
assert i.get("path_implies_consensus") is False
assert i.get("support_requires_explicit_reviewed_relation") is True
assert i.get("contradiction_requires_explicit_reviewed_relation") is True
assert i.get("analytical_relationships_opt_in") is True
assert i.get("absence_of_path_means_no_relationship_exists") is False
assert d.get("platform_core",{}).get("durable_research_object_authority")=="platform-core"
assert d.get("platform_core",{}).get("automatic_core_write") is False
PY

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
print(json.dumps({"core_version":d.get("core_version"),"reachable":d.get("reachable"),"ready":d.get("ready_capability_count"),"total":d.get("capability_count")},indent=2))
assert d.get("reachable") is True
assert int(d.get("ready_capability_count") or 0) > 0
PY

curl -fsS --get "$BASE/v1/search" \
  --data-urlencode 'q=sustainability' \
  --data-urlencode 'mode=hybrid' \
  --data-urlencode 'include_core=true' \
  --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 - "$TMP/search.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh:
    d=json.load(fh)
assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"
PY

echo "PASS: Library backend v2.36.0 Research Graph Query & Evidence Pathfinding deployed and verified."
