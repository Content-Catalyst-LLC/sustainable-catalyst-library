from app.energy_grid_storage import EnergyGridStorageReliabilityRegistry
from app.energy_systems import EnergySystemsKnowledgeFoundation


def test_v160_grid_storage_framework_contract():
    x = EnergyGridStorageReliabilityRegistry().framework()
    assert x["version"] == "1.6.0"
    assert x["execution_authority"]["workbench"]["minimum_version"] == "6.3.0"
    assert x["execution_authority"]["lab"]["minimum_version"] == "0.103.0"
    assert x["execution_authority"]["site_intelligence"]["minimum_version"] == "4.41.0"
    assert len(x["operations"]) == 7
    assert x["guardrails"]["missing_parameter_inference"] is False
    assert x["guardrails"]["real_grid_reliability_declaration"] is False


def test_v160_templates_require_human_review():
    r = EnergyGridStorageReliabilityRegistry()
    assert r.storage_scenario_template()["template"]["review"]["human_review_required"] is True
    assert r.reliability_scenario_template()["template"]["review"]["human_review_required"] is True


def test_v160_manifest_integrates_new_layer():
    m = EnergySystemsKnowledgeFoundation().manifest()
    assert m["subsystem"]["version"] == "1.6.0"
    assert m["subsystem"]["backend_version"] == "2.22.0"
    assert m["grid_storage_reliability_analysis"]["version"] == "1.6.0"
    assert m["counts"]["energy_grid_storage_operations"] == 7
