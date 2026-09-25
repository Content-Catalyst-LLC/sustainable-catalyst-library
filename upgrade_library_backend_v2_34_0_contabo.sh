#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.34.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2340.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.34.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.34.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.34.0"
grep -q '@app.post("/v1/publication-knowledge-maps/corpus")' "$SRC/app/main.py" || fail "POST corpus transport missing"
grep -q 'sc-library-linked-visual-query/1.0' "$SRC/app/publication_corpus_maps.py" || fail "linked visual-query contract missing"
grep -q 'sc-library-4d-knowledge-terrain/1.0' "$SRC/app/publication_corpus_maps.py" || fail "4D terrain contract missing"
grep -q 'sc-library-visual-research-session/1.0' "$SRC/app/visual_research_sessions.py" || fail "visual research session contract missing"
grep -q 'sc-library-visual-evidence-trace/1.0' "$SRC/app/visual_evidence_trace.py" || fail "visual evidence trace contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.34.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config --quiet; docker compose build; docker compose up -d --force-recreate
for i in $(seq 1 60); do state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"; [[ "$state" == "healthy" ]] && break; [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }; sleep 2; done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"ok":d.get("ok"),"version":d.get("version"),"database":d.get("database")},indent=2)); assert d.get("ok") is True; assert d.get("version")=="2.34.0"; c=d.get("capabilities",{}); assert c.get("publication_linked_scientific_views") is True; assert c.get("publication_visual_query_contract") is True; assert c.get("publication_async_corpus_transport") is True; assert c.get("publication_corpus_post_transport") is True; assert c.get("publication_renderer_visibility_repair") is True; assert c.get("publication_4d_terrain_recovery") is True; assert c.get("publication_peak_preserving_terrain_surface") is True; assert c.get("publication_reproducible_visual_sessions") is True; assert c.get("publication_workspace_visual_handoff_package") is True; assert c.get("publication_visual_evidence_trace") is True; assert c.get("publication_source_drilldown") is True; assert c.get("publication_evidence_weighted_findings_claims") is True; assert c.get("publication_explicit_reviewed_contradiction_overlays") is True' < "$TMP/health.json"
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":true,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}' -o "$TMP/corpus-post.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); q=d.get("visual_query",{}); t=d.get("knowledge_terrain_4d",{}); print(json.dumps({"schema":d.get("schema"),"selection":d.get("corpus",{}).get("selection"),"publications":d.get("metrics",{}).get("publication_count"),"query_schema":q.get("schema"),"terrain_schema":t.get("schema")},indent=2)); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"; assert q.get("schema")=="sc-library-linked-visual-query/1.0"; assert t.get("schema")=="sc-library-4d-knowledge-terrain/1.0"' < "$TMP/corpus-post.json"
# Preserve legacy GET compatibility for diagnostics and direct clients.
curl -fsS --get "$BASE/v1/publication-knowledge-maps/corpus" --data-urlencode 'source_key=wordpress-main' --data-urlencode 'max_publications=5' --data-urlencode 'max_topics_per_publication=12' -o "$TMP/corpus-get.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"' < "$TMP/corpus-get.json"
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/session-package" -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"corpus_request":{"source_key":"wordpress-main","record_ids":[],"max_publications":12,"max_topics_per_publication":12},"visual_state":{"view":"knowledge-terrain-4d","selected_node_ids":[],"mode":"highlight"},"target_workspace":true}' -o "$TMP/session.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"schema":d.get("schema"),"session_id":d.get("session_id"),"fingerprint":d.get("corpus_snapshot",{}).get("fingerprint_sha256"),"workspace":d.get("workspace_handoff",{}).get("schema")},indent=2)); assert d.get("schema")=="sc-library-visual-research-session/1.0"; assert d.get("corpus_snapshot",{}).get("fingerprint_sha256"); h=d.get("workspace_handoff",{}); assert h.get("schema")=="sc-library-workspace-visual-research-handoff/1.0"; assert h.get("references_only") is True; assert h.get("requires_user_acceptance") is True; assert h.get("automatic_workspace_write") is False' < "$TMP/session.json"

python3 - <<'PYTRACE' "$TMP/corpus-post.json" "$TMP/trace-payload.json"
import json,sys
corpus=json.load(open(sys.argv[1]))
nodes=corpus.get("nodes") or []
assert nodes, "corpus contains no nodes for evidence-trace smoke"
node_id=str(nodes[0].get("id"))
payload={"corpus_request":{"source_key":"wordpress-main","record_ids":[],"max_publications":25,"max_topics_per_publication":36},"visual_state":{"visual_query":{"selected_node_ids":[node_id]}}}
json.dump(payload,open(sys.argv[2],"w"))
PYTRACE
curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/evidence-trace" -H 'Accept: application/json' -H 'Content-Type: application/json' --data-binary @"$TMP/trace-payload.json" -o "$TMP/trace.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"schema":d.get("schema"),"selected":d.get("metrics",{}).get("selected_node_count"),"sources":d.get("metrics",{}).get("source_record_count"),"passages":d.get("metrics",{}).get("source_passage_count")},indent=2)); assert d.get("schema")=="sc-library-visual-evidence-trace/1.0"; assert d.get("metrics",{}).get("selected_node_count",0)>=1; i=d.get("interpretation",{}); assert i.get("trace_creates_new_claims") is False; assert i.get("relationship_implies_causality") is False' < "$TMP/trace.json"

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("reachable") is True; assert d.get("ready_capability_count")==8' < "$TMP/core.json"
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"' < "$TMP/search.json"

echo "=== REVIEWED FINDING/CLAIM OVERLAY CONTRACT ==="
curl -fsS -X POST http://127.0.0.1:8087/v1/publication-knowledge-maps/corpus -H 'Content-Type: application/json' -d '{"source_key":"wordpress-main","max_publications":3,"include_semantic_similarity":false}' > "$TMP/corpus-overlay.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); o=d.get("research_overlays",{}); print(json.dumps({"schema":o.get("schema"),"metrics":o.get("metrics"),"interpretation":o.get("interpretation")},indent=2)); assert o.get("schema")=="sc-library-evidence-weighted-research-overlay/1.0"; i=o.get("interpretation",{}); assert i.get("overlay_creates_new_claims") is False; assert i.get("weight_means_truth") is False; assert i.get("contradiction_requires_explicit_reviewed_relation") is True' < "$TMP/corpus-overlay.json"

echo "PASS: Library backend v2.34.0 Evidence-Weighted Findings, Claims & Contradiction Overlays deployed."
