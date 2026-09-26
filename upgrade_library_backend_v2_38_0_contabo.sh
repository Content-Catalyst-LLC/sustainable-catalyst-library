#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.38.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2380.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.38.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.38.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.38.0"
grep -q 'sc-library-source-identity-resolution/1.0' "$SRC/app/source_identity_resolution.py" || fail "source identity contract missing"
grep -q 'sc-library-source-identity-cluster/1.0' "$SRC/app/source_identity_resolution.py" || fail "source identity cluster contract missing"
grep -q '"automatic_record_merge": False' "$SRC/app/source_identity_resolution.py" || fail "non-destructive merge boundary missing"
grep -q '"title_only_identity_merge": False' "$SRC/app/source_identity_resolution.py" || fail "title-only identity boundary missing"
grep -q '@app.post("/v1/source-identity/analyze")' "$SRC/app/main.py" || fail "source identity analysis route missing"
grep -q '@app.post("/v1/publication-knowledge-maps/source-identity")' "$SRC/app/main.py" || fail "corpus source identity route missing"
grep -q 'sc-library-scientific-document-intelligence/1.0' "$SRC/app/scientific_document_intelligence.py" || fail "v5.26 scientific document contract missing"
grep -q 'sc-library-research-graph-query/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v5.25 graph query contract missing"
grep -q 'sc-library-evidence-pathfinding/1.0' "$SRC/app/research_graph_pathfinding.py" || fail "v5.25 pathfinding contract missing"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "v5.24 evidence synthesis contract missing"

mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.38.0-$stamp.tgz" "$(basename "$ROOT")"
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
  "source_identity_resolution":c.get("source_identity_resolution"),
  "source_identity_version_family_detection":c.get("source_identity_version_family_detection"),
  "author_orcid_resolution":c.get("author_orcid_resolution"),
  "institution_ror_resolution":c.get("institution_ror_resolution"),
  "scientific_document_intelligence":c.get("scientific_document_intelligence"),
  "research_graph_query":c.get("publication_research_graph_query"),
  "evidence_pathfinding":c.get("publication_evidence_pathfinding")
},indent=2))
assert d.get("ok") is True
assert d.get("version")=="2.38.0"
assert c.get("source_identity_resolution") is True
assert c.get("source_identity_exact_doi_resolution") is True
assert c.get("source_identity_exact_content_hash_resolution") is True
assert c.get("source_identity_normalized_url_resolution") is True
assert c.get("source_identity_version_family_detection") is True
assert c.get("source_identity_duplicate_candidate_review") is True
assert c.get("author_orcid_resolution") is True
assert c.get("institution_ror_resolution") is True
assert c.get("dataset_doi_url_resolution") is True
assert c.get("source_identity_automatic_merge") is False
assert c.get("source_identity_title_only_merge") is False
assert c.get("scientific_document_intelligence") is True
assert c.get("publication_research_graph_query") is True
assert c.get("publication_evidence_pathfinding") is True
PY

cat > "$TMP/source-identity-sample.json" <<'JSON'
{
  "records": [
    {
      "record_id":"verification:identity:a",
      "source_key":"verification-source-a",
      "title":"Identity verification study",
      "canonical_url":"https://example.org/study/?utm_source=test",
      "content_hash":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "published_at":"2025-01-01",
      "authors":["Jane Doe"],
      "identifiers":{"doi":"10.5555/identity.1"},
      "metadata":{
        "authors":[{"name":"Jane Doe","orcid":"0000-0002-1825-0097"}],
        "institutions":[{"name":"Example University","ror":"03vek6s52"}],
        "datasets":[{"name":"Verification data","doi":"10.5555/data.1"}]
      }
    },
    {
      "record_id":"verification:identity:b",
      "source_key":"verification-source-b",
      "title":"Identity verification study — revised",
      "canonical_url":"https://example.org/study#latest",
      "content_hash":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
      "published_at":"2026-01-01",
      "authors":["J. Doe"],
      "identifiers":{"doi":"https://doi.org/10.5555/IDENTITY.1"},
      "metadata":{
        "authors":[{"name":"J. Doe","orcid":"https://orcid.org/0000-0002-1825-0097"}],
        "institutions":[{"name":"Example Univ.","ror":"https://ror.org/03vek6s52"}],
        "datasets":[{"name":"Verification dataset","doi":"https://doi.org/10.5555/DATA.1"}]
      }
    }
  ]
}
JSON
curl -fsS -X POST "$BASE/v1/source-identity/analyze" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data-binary @"$TMP/source-identity-sample.json" -o "$TMP/source-identity-result.json"
python3 - "$TMP/source-identity-result.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
clusters=[x for x in d.get("source_identity_clusters",[]) if int(x.get("member_count") or 0)>1]
entities=d.get("entity_resolution",{}).get("identities",[])
print(json.dumps({
  "schema":d.get("schema"),"metrics":d.get("metrics"),
  "multi_record_clusters":[{"classification":x.get("classification"),"members":x.get("member_record_ids")} for x in clusters],
  "resolved_entity_kinds":sorted({x.get("kind") for x in entities})
},indent=2))
assert d.get("schema")=="sc-library-source-identity-resolution/1.0"
assert len(clusters)==1
assert clusters[0].get("classification")=="same-work-version-family"
assert clusters[0].get("automatic_merge") is False
assert clusters[0].get("records_deleted") is False
assert {"author","institution","dataset"}.issubset({x.get("kind") for x in entities})
b=d.get("boundaries",{})
assert b.get("automatic_record_merge") is False
assert b.get("automatic_record_deletion") is False
assert b.get("title_only_identity_merge") is False
assert b.get("author_name_only_cross_source_merge") is False
assert b.get("source_identity_is_evidence_truth") is False
assert d.get("platform_core",{}).get("durable_research_object_authority")=="platform-core"
PY

CORPUS='{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":false,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}'
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data "$CORPUS" -o "$TMP/corpus.json"
python3 - "$TMP/corpus.json" <<'PY'
import json,sys
with open(sys.argv[1], encoding='utf-8') as fh: d=json.load(fh)
i=d.get("source_identity_resolution",{}); sci=d.get("scientific_document_intelligence",{}); g=d.get("research_graph",{}); s=d.get("evidence_synthesis",{})
print(json.dumps({
  "schema":d.get("schema"),"publications":d.get("metrics",{}).get("publication_count"),
  "source_identity_schema":i.get("schema"),"source_identity_metrics":i.get("metrics"),
  "scientific_schema":sci.get("schema"),"research_graph_schema":g.get("schema")
},indent=2))
assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"
assert i.get("schema")=="sc-library-source-identity-corpus/1.0"
assert i.get("boundaries",{}).get("automatic_record_merge") is False
assert i.get("boundaries",{}).get("title_only_identity_merge") is False
assert sci.get("schema")=="sc-library-scientific-document-corpus/1.0"
assert g.get("schema")=="sc-library-research-graph-query/1.0"
assert s.get("schema")=="sc-library-cross-publication-evidence-synthesis/1.0"
assert d.get("boundaries",{}).get("automatic_source_record_merge") is False
assert d.get("boundaries",{}).get("source_identity_is_evidence_truth") is False
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

echo "PASS: Library backend v2.38.0 Source Identity, Deduplication & Entity Resolution deployed and verified."
