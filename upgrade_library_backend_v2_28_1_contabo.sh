#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.28.1.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2281.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.28.1"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.28.1"' "$SRC/app/__init__.py" || fail "payload is not backend v2.28.1"
grep -q 'CORPUS_KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-corpus-knowledge-map/1.0"' "$SRC/app/publication_corpus_maps.py" || fail "corpus map engine missing"
grep -q '@app.get("/v1/publication-knowledge-maps/corpus")' "$SRC/app/main.py" || fail "corpus route missing"
grep -q 'visual-research-object.create' "$SRC/app/platform_core.py" || fail "Core visual research operation missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.28.1-$stamp.tgz" "$(basename "$ROOT")"
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
health="$(curl -fsS "$BASE/health")"
printf '%s\n' "$health" | python3 -m json.tool | head -260
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("version")=="2.28.1",d; c=d.get("capabilities",{}); assert c.get("publication_knowledge_mapping") is True; assert c.get("publication_corpus_integration") is True; assert c.get("publication_corpus_default_source")=="wordpress-main"; assert c.get("publication_corpus_live_library_records") is True; assert c.get("publication_semantic_similarity")=="stored-embeddings-only"' "$health"

echo "=== SCIENTIFIC KNOWLEDGE MAP READINESS ==="
ready="$(curl -fsS "$BASE/v1/publication-knowledge-maps/readiness")"
printf '%s\n' "$ready" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("publication_corpus_integration") is True,d; assert d.get("default_corpus_source")=="wordpress-main",d; assert d.get("live_library_records") is True,d; assert d.get("storage_ready") is True,d; assert d.get("counts",{}).get("wordpress_publications",0)>=0' "$ready"

echo "=== LIVE PUBLICATION CORPUS KNOWLEDGE MAP ==="
corpus="$(curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" --data-urlencode 'source_key=wordpress-main' --data-urlencode 'include_citations=true' --data-urlencode 'include_semantic_similarity=true' --data-urlencode 'semantic_threshold=0.72' --data-urlencode 'max_publications=250' --data-urlencode 'max_topics_per_publication=36')"
printf '%s\n' "$corpus" | python3 -m json.tool | head -320
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0",d; assert d.get("scope")=="corpus",d; c=d.get("corpus",{}); assert c.get("source_key")=="wordpress-main",c; assert c.get("eligible_publication_count",0)>0,c; assert c.get("analyzed_publication_count",0)>0,c; pubs=[n for n in d.get("nodes",[]) if n.get("kind")=="publication"]; assert pubs,pubs; assert all(n.get("source_key")=="wordpress-main" for n in pubs),pubs[:3]; b=d.get("boundaries",{}); assert b.get("corpus_is_live_library_records") is True; assert b.get("llm_inferred_edges") is False; assert b.get("semantic_edges_are_truth_claims") is False' "$corpus"
record_id="$(python3 -c 'import json,sys; d=json.loads(sys.argv[1]); p=[n for n in d.get("nodes",[]) if n.get("kind")=="publication"]; print(p[0].get("id","") if p else "")' "$corpus")"

echo "=== SINGLE-PUBLICATION DRILL-DOWN REGRESSION ==="
if [[ -n "$record_id" ]]; then
  one="$(curl -fsS --get "$BASE/v1/publication-knowledge-maps" --data-urlencode "record_id=$record_id" --data-urlencode 'include_citations=true' --data-urlencode 'include_semantic_similarity=true' --data-urlencode 'semantic_threshold=0.72' --data-urlencode 'max_neighbors=25')"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); expected=sys.argv[2]; assert d.get("schema")=="sc-library-publication-knowledge-map/1.0",d; assert d.get("record_id")==expected,(expected,d); assert isinstance(d.get("nodes"),list); assert isinstance(d.get("edges"),list)' "$one" "$record_id"
fi

echo "=== PUBLICATION VISUALIZATION FOUNDATION ==="
visual="$(curl -fsS "$BASE/v1/publication-visualizations/readiness")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("storage_ready") is True,d; assert d.get("human_review_required") is True,d' "$visual"

echo "=== PLATFORM CORE BRIDGE ==="
bridge="$(curl -fsS "$BASE/v1/platform-core/readiness")"
printf '%s\n' "$bridge" | python3 -m json.tool
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("reachable") is True,d; assert d.get("ready_capability_count")==8,d; ops=set(d.get("operations",[])); assert "visual-research-object.create" in ops' "$bridge"

echo "=== SEARCH CONTRACT SMOKE TEST ==="
search="$(curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3')"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0",d; assert d.get("retrieval",{}).get("platform_core_enrichment") is True,d' "$search"

echo "PASS: Library backend v2.28.1 Publication Corpus Integration deployed."
