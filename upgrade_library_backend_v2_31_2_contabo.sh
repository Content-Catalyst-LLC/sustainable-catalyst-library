#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.31.2.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2312.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.31.2"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.31.2"' "$SRC/app/__init__.py" || fail "payload is not backend v2.31.2"
grep -q '@app.post("/v1/publication-knowledge-maps/corpus")' "$SRC/app/main.py" || fail "POST corpus transport missing"
grep -q 'sc-library-linked-visual-query/1.0' "$SRC/app/publication_corpus_maps.py" || fail "linked visual-query contract missing"
grep -q 'sc-library-4d-knowledge-terrain/1.0' "$SRC/app/publication_corpus_maps.py" || fail "4D terrain contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.31.2-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config --quiet; docker compose build; docker compose up -d --force-recreate
for i in $(seq 1 60); do state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"; [[ "$state" == "healthy" ]] && break; [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }; sleep 2; done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"ok":d.get("ok"),"version":d.get("version"),"database":d.get("database")},indent=2)); assert d.get("ok") is True; assert d.get("version")=="2.31.2"; c=d.get("capabilities",{}); assert c.get("publication_linked_scientific_views") is True; assert c.get("publication_visual_query_contract") is True; assert c.get("publication_async_corpus_transport") is True; assert c.get("publication_corpus_post_transport") is True; assert c.get("publication_renderer_visibility_repair") is True; assert c.get("publication_4d_terrain_recovery") is True; assert c.get("publication_peak_preserving_terrain_surface") is True' < "$TMP/health.json"
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":true,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}' -o "$TMP/corpus-post.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); q=d.get("visual_query",{}); t=d.get("knowledge_terrain_4d",{}); print(json.dumps({"schema":d.get("schema"),"selection":d.get("corpus",{}).get("selection"),"publications":d.get("metrics",{}).get("publication_count"),"query_schema":q.get("schema"),"terrain_schema":t.get("schema")},indent=2)); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"; assert q.get("schema")=="sc-library-linked-visual-query/1.0"; assert t.get("schema")=="sc-library-4d-knowledge-terrain/1.0"' < "$TMP/corpus-post.json"
# Preserve legacy GET compatibility for diagnostics and direct clients.
curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" --data-urlencode 'source_key=wordpress-main' --data-urlencode 'max_publications=5' --data-urlencode 'max_topics_per_publication=12' -o "$TMP/corpus-get.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"' < "$TMP/corpus-get.json"
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("reachable") is True; assert d.get("ready_capability_count")==8' < "$TMP/core.json"
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"' < "$TMP/search.json"
echo "PASS: Library backend v2.31.2 Scientific Renderer Visibility & 4D Terrain Recovery deployed."
