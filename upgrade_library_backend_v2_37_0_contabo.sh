#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.37.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2370.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.37.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.37.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.37.0"
grep -q 'sc-library-scientific-document-intelligence/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "scientific document contract missing"
grep -q 'sc-library-scientific-object/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "scientific object contract missing"
grep -q 'values_inferred_from_pixels.*False' "$SRC/app/scientific_document_intelligence.py" || fail "pixel-value inference boundary missing"
grep -q '@app.post("/v1/scientific-document-intelligence/analyze")' "$SRC/app/main.py" || fail "scientific analysis route missing"
grep -q '@app.get("/v1/scientific-document-intelligence/record/{record_id}")' "$SRC/app/main.py" || fail "record scientific intelligence route missing"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v5.25 graph query contract missing"
grep -q 'sc-library-evidence-pathfinding/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v5.25 pathfinding contract missing"
grep -q 'contains-scientific-object' "$SRC/app/research_graph_pathfinding.py" || fail "scientific object path relation missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v5.24 evidence synthesis contract missing"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.37.0-$stamp.tgz" "$(basename "$ROOT")"
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
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
c=d.get("capabilities",{})
print(json.dumps({
  "ok":d.get("ok"),"version":d.get("version"),"database":d.get("database"),
  "scientific_document_intelligence":c.get("scientific_document_intelligence"),
  "scientific_object_graph_overlay":c.get("scientific_object_graph_overlay"),
  "research_graph_query":c.get("publication_research_graph_query"),
  "evidence_pathfinding":c.get("publication_evidence_pathfinding")
},indent=2))
assert d.get("ok") is True
assert d.get("version")=="2.37.0"
assert c.get("scientific_document_intelligence") is True
assert c.get("scientific_document_figures_charts") is True
assert c.get("scientific_document_tables") is True
assert c.get("scientific_document_equations") is True
assert c.get("scientific_object_source_provenance") is True
assert c.get("scientific_object_graph_overlay") is True
assert c.get("scientific_visual_values_inferred_from_pixels") is False
assert c.get("publication_research_graph_query") is True
assert c.get("publication_evidence_pathfinding") is True
assert c.get("publication_analytical_path_edges_opt_in") is True
PY

cat > "$TMP/scientific-sample.json" <<'JSON'
{
  "document": {
    "record_id": "verification:scientific-document",
    "title": "Scientific object verification document",
    "source_content_hash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "scientific_objects": [
      {"kind":"table","label":"Table 1","page":3,"columns":["Year","Value"],"rows":[[2024,10.2],[2025,11.1]]},
      {"kind":"chart","label":"Figure 2","page":4,"data_series":[{"name":"Observed","points":[[2024,10.2],[2025,11.1]]}]},
      {"kind":"equation","label":"Equation 3","page":5,"latex":"E=mc^2"}
    ],
    "chunks": [
      {"ordinal":2,"heading":"Results","text":"Table 1 reports the observations. Figure 2 presents the series. Equation 3 is reproduced from the source.","metadata":{}}
    ]
  }
}
JSON
curl -fsS -X POST "$BASE/v1/scientific-document-intelligence/analyze" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data-binary @"$TMP/scientific-sample.json" -o "$TMP/scientific-result.json"
python3 - "$TMP/scientific-result.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
print(json.dumps({"schema":d.get("schema"),"metrics":d.get("metrics"),"boundaries":d.get("boundaries")},indent=2))
assert d.get("schema")=="sc-library-scientific-document-intelligence/1.0"
assert d.get("metrics",{}).get("scientific_object_count")==3
assert d.get("metrics",{}).get("reference_count")==3
assert d.get("metrics",{}).get("tables_with_structured_cells")==1
assert d.get("metrics",{}).get("equations_with_source_form")==1
b=d.get("boundaries",{})
assert b.get("values_inferred_from_pixels") is False
assert b.get("chart_trends_inferred") is False
assert b.get("claims_inferred_from_figures") is False
assert b.get("ocr_text_automatically_treated_as_verified") is False
assert b.get("equations_solved") is False
assert d.get("platform_core",{}).get("durable_research_object_authority")=="platform-core"
PY

CORPUS='{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":false,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}'
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data "$CORPUS" -o "$TMP/corpus.json"
python3 - "$TMP/corpus.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
s=d.get("evidence_synthesis",{}); g=d.get("research_graph",{}); m=d.get("scientific_document_intelligence",{})
print(json.dumps({
  "schema":d.get("schema"),"publications":d.get("metrics",{}).get("publication_count"),
  "research_graph_schema":g.get("schema"),"scientific_document_schema":m.get("schema"),
  "scientific_objects":m.get("metrics",{}).get("scientific_object_count")
},indent=2))
assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"
assert s.get("schema")=="sc-library-cross-publication-evidence-synthesis/1.0"
assert g.get("schema")=="sc-library-research-graph-query/1.0"
assert m.get("schema")=="sc-library-scientific-document-corpus/1.0"
assert m.get("boundaries",{}).get("values_inferred_from_pixels") is False
assert m.get("boundaries",{}).get("claims_inferred_from_scientific_objects") is False
assert d.get("boundaries",{}).get("scientific_values_inferred_from_pixels") is False
assert d.get("boundaries",{}).get("scientific_object_presence_is_claim_truth") is False
assert s.get("platform_core",{}).get("durable_synthesis_authority")=="platform-core"
PY

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 - "$TMP/core.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
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
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"
PY

echo "PASS: Library backend v2.37.0 Multimodal Scientific Document Intelligence deployed and verified."
