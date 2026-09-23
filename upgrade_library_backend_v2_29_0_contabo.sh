#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.29.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2290.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.29.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.29.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.29.0"
grep -q 'four_dimensional_ready' "$SRC/app/publication_corpus_maps.py" || fail "4D analytical contract missing"
grep -q 'publication_relationships' "$SRC/app/publication_corpus_maps.py" || fail "cross-publication relationships missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.29.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config --quiet; docker compose build; docker compose up -d --force-recreate
for i in $(seq 1 60); do state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"; [[ "$state" == "healthy" ]] && break; [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }; sleep 2; done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"ok":d.get("ok"),"version":d.get("version"),"database":d.get("database")},indent=2)); assert d.get("ok") is True; assert d.get("version")=="2.29.0"; c=d.get("capabilities",{}); assert c.get("multi_publication_knowledge_landscape") is True; assert c.get("publication_topic_regions") is True; assert c.get("publication_temporal_dynamics") is True; assert c.get("publication_relationship_matrix") is True' < "$TMP/health.json"
curl -fsS "$BASE/v1/publication-knowledge-maps/readiness" -o "$TMP/readiness.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("storage_ready") is True; assert d.get("multi_publication_knowledge_landscape") is True; assert d.get("topic_region_analysis") is True; assert d.get("temporal_topic_dynamics") is True; assert d.get("four_dimensional_knowledge_model_ready") is True; print(json.dumps({"storage_ready":d.get("storage_ready"),"counts":d.get("counts")},indent=2))' < "$TMP/readiness.json"
curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" --data-urlencode 'source_key=wordpress-main' --data-urlencode 'max_publications=250' --data-urlencode 'max_topics_per_publication=36' -o "$TMP/corpus.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("corpus",{}); m=d.get("multi_publication_analysis",{}); print(json.dumps({"schema":d.get("schema"),"corpus":c,"metrics":d.get("metrics"),"topic_regions":len(d.get("topic_regions",[])),"publication_relationships":len(d.get("publication_relationships",[])),"years":d.get("temporal_dynamics",{}).get("years",[])},indent=2)); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"; assert c.get("selection")=="wordpress-post-fallback"; assert c.get("eligible_publication_count",0)>0; assert isinstance(d.get("topic_regions"),list); assert isinstance(d.get("publication_relationships"),list); assert isinstance(d.get("temporal_dynamics",{}).get("bins"),list); assert "relationship_matrix" in d.get("linked_views",{}); assert "four_dimensional_ready" in d.get("analytical_dimensions",{})' < "$TMP/corpus.json"
curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("reachable") is True; assert d.get("ready_capability_count")==8; assert "visual-research-object.create" in set(d.get("operations",[]))' < "$TMP/core.json"
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"' < "$TMP/search.json"
echo "PASS: Library backend v2.29.0 Multi-Publication Knowledge Landscape deployed."
