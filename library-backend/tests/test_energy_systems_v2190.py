import json
from app.energy_systems import EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_v130_manifest_and_guardrails():
    d = engine().manifest()
    assert d["subsystem"]["version"] == "1.3.0"
    assert d["subsystem"]["backend_version"] == "2.19.0"
    assert d["subsystem"]["release"] == "Energy Workbench Runtime"
    assert d["guardrails"]["workbench_execution_activated"] is True
    assert d["guardrails"]["workbench_automatic_execution_activated"] is False
    assert d["guardrails"]["workbench_automatic_persistence_activated"] is False
    assert d["guardrails"]["workbench_automatic_ranking_activated"] is False
    assert d["guardrails"]["workbench_automatic_recommendation_activated"] is False


def test_v130_runtime_registry_certifies_only_workbench_execution():
    d = engine().runtime_consumers()
    rows = {x["target_key"]: x for x in d["items"]}
    wb = rows["workbench"]
    assert wb["minimum_target_version"] == "6.2.0"
    assert wb["execution"]["state"] == "certified-explicit-input-calculation-execution"
    assert wb["execution"]["framework_route"] == "/v1/energy-runtime/execution-framework"
    assert wb["execution"]["execute_route"] == "/v1/energy-runtime/execute"
    assert wb["execution"]["automatic"] is False
    for key in ("research-librarian", "lab", "site-intelligence", "decision-studio"):
        assert rows[key]["execution"]["state"] == "not-certified"
        assert rows[key]["execution"]["execute_route"] == ""


def test_v130_workbench_template_exposes_explicit_calculation_request_slots():
    packet = engine().runtime_handoff_template(target_key="workbench")["packet"]
    payload = packet["payload"]
    assert payload["numeric_registry"]["calculation_requests"] == []
    assert payload["energy_balance"]["calculation_requests"] == []
    assert payload["economics"]["calculation_requests"] == []
    assert payload["bioenergy_and_carbon"]["calculation_requests"] == []


def test_v130_workbench_handoff_preserves_explicit_requests_and_claims_no_automatic_execution():
    study = engine().runtime_handoff_template(target_key="workbench")["packet"]["payload"]
    # Reconstruct full study from the target template plus blank sections through the public platform template.
    full = engine().platform_study_template()["study"]
    full["identity"].update({"study_id": "study-v130", "question": "What is generation?"})
    full["provenance"] = [{"source_ref": "source-v130"}]
    full["numeric_registry"]["calculation_requests"] = []
    full["energy_balance"]["calculation_requests"] = [{
        "schema": "sc-energy-workbench-calculation-request/1.0",
        "operation": "capacity-factor-generation",
        "inputs": {"capacity_kw": 100, "capacity_factor_pct": 50, "hours": 8760},
        "source_refs": ["source-v130"],
        "assumptions": [],
    }]
    full["economics"]["calculation_requests"] = []
    full["bioenergy_and_carbon"]["calculation_requests"] = []
    d = engine().runtime_handoff(target_key="workbench", study_json=json.dumps(full))
    assert d["packet"]["payload"]["energy_balance"]["calculation_requests"][0]["operation"] == "capacity-factor-generation"
    assert d["packet"]["target"]["minimum_target_version"] == "6.2.0"
    assert d["delivery"]["target_execution_claimed"] is True
    assert d["delivery"]["execution_is_automatic"] is False
    assert d["delivery"]["persistence_performed"] is False


def test_v130_methodology_boundary_is_explicit():
    rows = {x["key"]: x for x in engine().methodology_rules()["items"]}
    rule = rows["energy-workbench-runtime-boundary"]
    assert rule["current_default"] is False
    assert "does not infer missing inputs" in rule["rule"]
    assert "carbon-credit claim" in rule["rule"]
