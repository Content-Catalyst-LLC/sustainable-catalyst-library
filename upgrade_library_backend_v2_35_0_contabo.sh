#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.35.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2350.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.35.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.35.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.35.0"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$SRC/app/evidence_synthesis.py" || fail "evidence synthesis contract missing"
grep -q 'build_cross_publication_synthesis' "$SRC/app/publication_corpus_maps.py" || fail "corpus synthesis integration missing"
grep -q '@app.post("/v1/publication-knowledge-maps/corpus")' "$SRC/app/main.py" || fail "POST corpus transport missing"
grep -q 'sc-library-linked-visual-query/1.0' "$SRC/app/publication_corpus_maps.py" || fail "linked visual-query contract missing"
grep -q 'sc-library-4d-knowledge-terrain/1.0' "$SRC/app/publication_corpus_maps.py" || fail "4D terrain contract missing"
grep -q 'sc-library-visual-research-session/1.0' "$SRC/app/visual_research_sessions.py" || fail "visual research session contract missing"
grep -q 'sc-library-visual-evidence-trace/1.0' "$SRC/app/visual_evidence_trace.py" || fail "visual evidence trace contract missing"
mkdir -p "$BACKUP_ROOT"
stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.35.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config --quiet; docker compose build; docker compose up -d --force-recreate
for i in $(seq 1 60); do state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)"; [[ "$state" == "healthy" ]] && break; [[ "$state" =~ ^(unhealthy|exited|dead)$ ]] && { docker compose logs --tail=220; fail "container state $state"; }; sleep 2; done
[[ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' sc-library-backend 2>/dev/null || true)" == "healthy" ]] || fail "backend did not become healthy"
BASE=http://127.0.0.1:8087
curl -fsS "$BASE/health" -o "$TMP/health.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"ok":d.get("ok"),"version":d.get("version"),"database":d.get("database")},indent=2)); assert d.get("ok") is True; assert d.get("version")=="2.35.0"; c=d.get("capabilities",{}); assert c.get("publication_cross_publication_evidence_synthesis") is True; assert c.get("publication_support_connected_structures") is True; assert c.get("publication_explicit_competing_hypotheses") is True; assert c.get("publication_argument_path_visualization") is True' < "$TMP/health.json"

curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/corpus" -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"source_key":"wordpress-main","record_ids":[],"include_citations":true,"include_semantic_similarity":false,"semantic_threshold":0.72,"max_publications":25,"max_topics_per_publication":36}' -o "$TMP/corpus.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); s=d.get("evidence_synthesis",{}); print(json.dumps({"schema":d.get("schema"),"publications":d.get("metrics",{}).get("publication_count"),"synthesis_schema":s.get("schema"),"synthesis_metrics":s.get("metrics"),"core_authority":s.get("platform_core",{}).get("durable_synthesis_authority")},indent=2)); assert d.get("schema")=="sc-library-publication-corpus-knowledge-map/1.0"; assert s.get("schema")=="sc-library-cross-publication-evidence-synthesis/1.0"; i=s.get("interpretation",{}); assert i.get("consensus_inferred") is False; assert i.get("hypotheses_inferred") is False; assert i.get("competing_hypotheses_require_explicit_metadata") is True; assert i.get("evidence_balance_is_truth_score") is False; assert i.get("synthesis_creates_new_claims") is False; assert s.get("platform_core",{}).get("durable_synthesis_authority")=="platform-core"' < "$TMP/corpus.json"

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("visual_query",{}).get("schema")=="sc-library-linked-visual-query/1.0"; assert d.get("knowledge_terrain_4d",{}).get("schema")=="sc-library-4d-knowledge-terrain/1.0"; assert d.get("research_overlays",{}).get("schema")=="sc-library-evidence-weighted-research-overlay/1.0"' < "$TMP/corpus.json"

curl -fsS -X POST "$BASE/v1/publication-knowledge-maps/session-package" -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"corpus_request":{"source_key":"wordpress-main","record_ids":[],"max_publications":12,"max_topics_per_publication":12},"visual_state":{"view":"evidence-synthesis","selected_node_ids":[],"mode":"highlight"},"target_workspace":true}' -o "$TMP/session.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-visual-research-session/1.0"; h=d.get("workspace_handoff",{}); assert h.get("schema")=="sc-library-workspace-visual-research-handoff/1.0"; assert h.get("references_only") is True; assert h.get("requires_user_acceptance") is True; assert h.get("automatic_workspace_write") is False' < "$TMP/session.json"

curl -fsS "$BASE/v1/platform-core/readiness" -o "$TMP/core.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); print(json.dumps({"core_version":d.get("core_version"),"ready":d.get("ready_capability_count"),"total":d.get("capability_count")},indent=2)); assert d.get("reachable") is True; assert d.get("ready_capability_count")==8' < "$TMP/core.json"
curl -fsS --get "$BASE/v1/search" --data-urlencode 'q=sustainability' --data-urlencode 'mode=hybrid' --data-urlencode 'include_core=true' --data-urlencode 'limit=3' -o "$TMP/search.json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-library-hybrid-retrieval/1.0"' < "$TMP/search.json"

echo "PASS: Library backend v2.35.0 Cross-Publication Evidence Synthesis & Competing Hypotheses deployed."
