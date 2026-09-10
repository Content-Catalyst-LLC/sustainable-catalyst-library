#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.6.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v260.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.6.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.6.0-$ts"
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || { echo "ERROR: could not find backend package root" >&2; exit 1; }
rsync -a --delete --exclude='.env' "$source_dir/" "$LIVE/"
cp "$env_backup" "$LIVE/.env"
chmod 600 "$LIVE/.env"

cd "$LIVE"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate
container="sc-library-backend"
healthy=0
for _ in $(seq 1 45); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || true)"
  case "$state" in
    healthy) healthy=1; break ;;
    unhealthy|exited|dead)
      echo "ERROR: $container entered state: $state" >&2
      docker logs --tail=150 "$container" >&2 || true
      exit 1
      ;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker logs --tail=150 "$container" >&2 || true; exit 1; }

health_json="$(curl -fsS http://127.0.0.1:8087/health)"
manifest_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature)"
model_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature/project-object-model)"
object_types_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature/project-object-types)"
provenance_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature/provenance-event-types)"
template_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature/project-packet-template)"
graph_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/evidence-graph?node_type=methodology&node_key=soc-direct-measurement&limit=100')"
librarian_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-librarian')"
intents_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-librarian/intents')"
roles_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-librarian/source-roles')"
guidance_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-librarian/guidance?q=soil%20carbon%20MRV%20national%20GHG%20inventory&limit=12')"
context_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-context?q=parcel%20baseline%20soil%20carbon%20monitoring%20MRV&limit=8')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$manifest_json" | python3 -m json.tool
printf '%s\n' "$model_json" | python3 -m json.tool
printf '%s\n' "$object_types_json" | python3 -m json.tool
printf '%s\n' "$provenance_json" | python3 -m json.tool
printf '%s\n' "$template_json" | python3 -m json.tool
printf '%s
' "$graph_json" | python3 -m json.tool
printf '%s
' "$librarian_json" | python3 -m json.tool
printf '%s
' "$intents_json" | python3 -m json.tool
printf '%s
' "$roles_json" | python3 -m json.tool
printf '%s
' "$guidance_json" | python3 -m json.tool
printf '%s
' "$context_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.5.0" ]] || { echo "ERROR: expected backend v2.6.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("afolu_research_librarian_intelligence") is True; assert c.get("afolu_research_intent_classification") is True; assert c.get("afolu_research_source_planning") is True; assert c.get("afolu_evidence_gap_diagnostics") is True; assert c.get("afolu_policy_market_freshness_flags") is True; assert c.get("automatic_afolu_research_conclusion_generation") is False; assert c.get("carbon_project_packet_persistence") is False; assert c.get("private_organizational_knowledge") is True' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); g=d.get("governance",{}); cov=d.get("coverage",{}); assert d.get("schema")=="sc-carbon-nature-knowledge-foundation/1.4"; assert d.get("subsystem",{}).get("version")=="0.5.0"; assert cov.get("project_object_type_count")==10; assert cov.get("research_intent_count")==11; assert cov.get("research_source_role_count")==8; assert g.get("project_packet_persistence") is False; assert g.get("automatic_research_conclusion_generation") is False; assert g.get("automatic_current_rule_assertion") is False; assert g.get("carbon_credit_issuance") is False' <<<"$manifest_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-project-object-model/1.0"; assert len(d.get("object_types",[]))==10; assert len(d.get("provenance_event_types",[]))==9; assert len(d.get("link_types",[]))==9; assert d.get("governance",{}).get("project_packet_persistence") is False' <<<"$model_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-project-object-type-registry/1.0"; assert d.get("count")==10' <<<"$object_types_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-project-provenance/1.0"; assert d.get("count")==9; assert d.get("guardrails",{}).get("fingerprint_is_not_digital_signature") is True' <<<"$provenance_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-project-packet-template/1.0"; assert d.get("template",{}).get("schema")=="sc-carbon-project-packet/1.0"; assert d.get("guardrails",{}).get("template_is_not_a_valid_project_claim") is True' <<<"$template_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-evidence-methodology-graph/1.0"; assert d.get("edge_count",0)>0; assert d.get("governance",{}).get("edge_inference_enabled") is False' <<<"$graph_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-afolu-research-librarian-intelligence/1.0"; assert d.get("subsystem_version")=="0.5.0"; assert d.get("intent_count")==11; assert d.get("source_role_count")==8; assert d.get("integration",{}).get("project_aware_augmentation_sends_question_only") is True' <<<"$librarian_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-afolu-research-intent-registry/1.0"; assert d.get("count")==11' <<<"$intents_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-afolu-research-source-role-registry/1.0"; assert d.get("count")==8' <<<"$roles_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); keys={x.get("key") for x in d.get("detected_intents",[])}; assert d.get("schema")=="sc-afolu-research-guidance/1.0"; assert "mrv-methodology" in keys; assert "inventory-accounting" in keys; assert d.get("freshness_review",{}).get("current_source_check_required") is True; assert d.get("guardrails",{}).get("deterministic_guidance_is_not_research_conclusion") is True' <<<"$guidance_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-nature-research-context/1.4"; assert d.get("subsystem_version")=="0.5.0"; assert d.get("handoff",{}).get("domain_aware_reasoning_enabled") is True; assert d.get("research_librarian",{}).get("detected_intents"); assert d.get("guardrails",{}).get("project_object_validation_is_not_verification") is True' <<<"$context_json"

docker exec -i "$container" python - <<'PY'
from app.carbon_nature import CarbonNatureKnowledgeFoundation
engine = CarbonNatureKnowledgeFoundation()
result = engine.validate_project_packet(engine.project_packet_template()["template"])
assert result["valid"] is True, result
assert result["guardrails"]["packet_not_persisted"] is True
print("PASS: in-container Carbon Project packet validation")
PY

printf '%s\n' 'PASS - Library backend v2.6.0 upgraded; Carbon & Nature Intelligence v0.5.0 AFOLU Research Librarian Intelligence is active.'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
