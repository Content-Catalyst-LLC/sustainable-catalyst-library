from app.energy_systems import EnergySystemsKnowledgeFoundation


def test_v140_manifest_and_uncertainty_registry():
    es = EnergySystemsKnowledgeFoundation()
    d = es.manifest()
    assert d["subsystem"]["version"] == "1.4.0"
    assert d["subsystem"]["backend_version"] == "2.20.0"
    assert d["subsystem"]["release"] == "Energy Modeling & Uncertainty"
    u = d["energy_modeling_uncertainty"]
    assert u["version"] == "1.4.0"
    assert u["execution_authorities"]["lab"]["minimum_version"] == "0.102.0"
    assert u["execution_authorities"]["workbench"]["minimum_version"] == "6.2.0"
    assert u["guardrails"]["automatic_workbench_execution"] is False
    assert u["guardrails"]["technology_ranking"] is False


def test_v140_runtime_registry_certifies_lab_modeling():
    es = EnergySystemsKnowledgeFoundation()
    f = es.runtime_framework()
    assert f["version"] == "1.4.0"
    assert f["counts"]["modeling_analysis_targets"] == 1
    lab = es.runtime_target("lab")["target"]
    assert lab["minimum_target_version"] == "0.102.0"
    assert lab["execution_state"] == "certified-energy-modeling-and-uncertainty"
    assert lab["execution_framework_route"] == "/v1/energy-modeling/framework"
    assert lab["execution_plan_route"] == "/v1/energy-modeling/plan"
    assert lab["execution_route"] == "/v1/energy-modeling/analyze"
    assert lab["result_validation_route"] == "/v1/energy-modeling/validate-result"


def test_v140_uncertainty_template_is_explicit_and_non_recommending():
    es = EnergySystemsKnowledgeFoundation()
    t = es.uncertainty_study_template()
    assert t["version"] == "1.4.0"
    assert t["template"]["uncertainty"]["design"]["seed"] == 2026
    assert t["template"]["uncertainty"]["variables"][0]["distribution"] == "triangular"
    assert "not recommended technical assumptions" in t["guardrail"]
