#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.28.3.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2283.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.28.3"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.28.3"' "$SRC/app/__init__.py" || fail "payload is not backend v2.28.3"
grep -q 'selection_mode = "publication-library-manifest"' "$SRC/app/publication_corpus_maps.py" || fail "canonical publication manifest selector missing"
grep -q 'selection_mode = "wordpress-post-fallback"' "$SRC/app/publication_corpus_maps.py" || fail "safe WordPress post fallback missing"
grep -q '@app.get("/v1/publication-knowledge-maps/corpus")' "$SRC/app/main.py" || fail "corpus route missing"
grep -q 'visual-research-object.create' "$SRC/app/platform_core.py" || fail "Core visual research operation missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.28.3-$stamp.tgz" "$(basename "$ROOT")"
fi
ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"
echo "=== BUILD / RECREATE LIBRARY BACKEND ==="
docker compose config --quiet
docker compose build
docker compose up -d --force-recreate
for i in $(seq 1 60); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"
  [[ "$state" == "healthy" ]] && break
  if [[ "$state" =~ ^(unhealthy|exited|dead)$ ]]; then docker compose logs --tail=220; fail "sc-library-backend entered state $state"; fi
  sleep 2
done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || { docker compose logs --tail=220; fail "sc-library-backend did not become healthy"; }
BASE=http://127.0.0.1:8087

echo "=== LIBRARY HEALTH ==="
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"ok":d.get("ok"),"service":d.get("service"),"version":d.get("version"),"environment":d.get("environment"),"database":d.get("database")},indent=2)); assert d.get("ok") is True,d; assert d.get("version")=="2.28.3",d; c=d.get("capabilities",{}); assert c.get("publication_knowledge_mapping") is True; assert c.get("publication_corpus_integration") is True; assert c.get("publication_corpus_canonical_manifest") is True; assert c.get("publication_corpus_nonpublication_types_excluded") is True' < "$TMP/health.json"

echo "=== SCIENTIFIC KNOWLEDGE MAP READINESS ==="
curl -fsS "$BASE/v1/publication-knowledge-maps/readiness" -o "$TMP/readiness.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"schema":d.get("schema"),"storage_ready":d.get("storage_ready"),"canonical_publication_manifest_supported":d.get("canonical_publication_manifest_supported"),"safe_backend_fallback_object_type":d.get("safe_backend_fallback_object_type"),"counts":d.get("counts")},indent=2)); assert d.get("publication_corpus_integration") is True,d; assert d.get("canonical_publication_manifest_supported") is True,d; assert d.get("safe_backend_fallback_object_type")=="post",d; assert d.get("storage_ready") is True,d' < "$TMP/readiness.json"

echo "=== SAFE FALLBACK CORPUS ==="
curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" \
  --data-urlencode 'source_key=wordpress-main' \
  --data-urlencode 'include_citations=true' \
  --data-urlencode 'include_semantic_similarity=true' \
  --data-urlencode 'semantic_threshold=0.72' \
  --data-urlencode 'max_publications=250' \
  --data-urlencode 'max_topics_per_publication=36' \
  -o "$TMP/corpus.json"
echo "=== CORPUS SUMMARY ==="
python3 -c 'import json,sys; d=json.load(sys.stdin); pubs=[n for n in d.get("nodes",[]) if n.get("kind")=="publication"]; summary={"schema":d.get("schema"),"scope":d.get("scope"),"corpus":d.get("corpus"),"metrics":d.get("metrics"),"sample_publications":[{"id":n.get("id"),"title":n.get("label"),"object_type":n.get("object_type")} for n in pubs[:5]]}; print(json.dumps(summary,indent=2)); c=d.get("corpus",{}); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0",d; assert c.get("selection")=="wordpress-post-fallback",c; assert c.get("object_type")=="post",c; assert c.get("eligible_publication_count",0)>0,c; assert pubs,pubs; assert all(n.get("object_type")=="post" for n in pubs),pubs[:5]; assert all(n.get("source_key")=="wordpress-main" for n in pubs),pubs[:5]; b=d.get("boundaries",{}); assert b.get("non_publication_wordpress_types_excluded") is True,b' < "$TMP/corpus.json"

manifest_ids="$(python3 -c 'import json,sys; d=json.load(sys.stdin); p=[n.get("id") for n in d.get("nodes",[]) if n.get("kind")=="publication" and n.get("id")][:3]; print(",".join(p))' < "$TMP/corpus.json")"
[[ -n "$manifest_ids" ]] || fail "could not derive a production manifest smoke-test set"

echo "=== CANONICAL MANIFEST FILTER CONTRACT ==="
curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" \
  --data-urlencode 'source_key=wordpress-main' \
  --data-urlencode "record_ids=$manifest_ids" \
  --data-urlencode 'max_publications=10' \
  --data-urlencode 'include_semantic_similarity=false' \
  -o "$TMP/manifest.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); requested=set(sys.argv[1].split(",")); pubs=[n for n in d.get("nodes",[]) if n.get("kind")=="publication"]; ids={n.get("id") for n in pubs}; c=d.get("corpus",{}); print(json.dumps({"selection":c.get("selection"),"requested_manifest_count":c.get("requested_manifest_count"),"matched_publications":sorted(ids)},indent=2)); assert c.get("selection")=="publication-library-manifest",c; assert c.get("requested_manifest_count")==len(requested),c; assert ids and ids.issubset(requested),(ids,requested); assert d.get("boundaries",{}).get("publication_library_manifest_applied") is True,d.get("boundaries")' "$manifest_ids" < "$TMP/manifest.json"

record_id="$(python3 -c 'import json,sys; d=json.load(sys.stdin); p=[n for n in d.get("nodes",[]) if n.get("kind")=="publication"]; print(p[0].get("id","") if p else "")' < "$TMP/manifest.json")"
echo "=== SINGLE-PUBLICATION DRILL-DOWN REGRESSION ==="
if [[ -n "$record_id" ]]; then
  curl -fsS --get "$BASE/v1/publication-knowledge-maps" \
    --data-urlencode "record_id=$record_id" \
    --data-urlencode 'include_citations=true' \
    --data-urlencode 'include_semantic_similarity=true' \
    --data-urlencode 'semantic_threshold=0.72' \
    --data-urlencode 'max_neighbors=25' \
    -o "$TMP/single.json"
  python3 -c 'import json,sys; d=json.load(sys.stdin); expected=sys.argv[1]; assert d.get("schema")=="sc-library-publication-knowledge-map/1.0",d; assert d.get("record_id")==expected,(expected,d); assert isinstance(d.get("nodes"),list); assert isinstance(d.get("edges"),list)' "$record_id" < "$TMP/single.json"
fi

echo "=== PUBLICATION VISUALIZATION FOUNDATION ==="
curl -fsS "$BASE/v1/publication-visualizations/readiness" -o "$TMP/visual.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("storage_ready") is True,d; assert d.get("human_review_required") is True,d' < "$TMP/visual.json"

echo "=== PLATFORM CORE BRIDGE ==="
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"reachable":d.get("reachable"),"core_version":d.get("core_version"),"ready_capability_count":d.get("ready_capability_count"),"capability_count":d.get("capability_count")},indent=2)); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d; ops=set(d.get("operations",[])); assert "visual-research-object.create" in ops' < "$TMP/core.json"

echo "=== SEARCH CONTRACT SMOKE TEST ==="
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' < "$TMP/search.json"

echo "PASS: Library backend v2.28.3 Corpus Validator Argument-Length Repair deployed."
