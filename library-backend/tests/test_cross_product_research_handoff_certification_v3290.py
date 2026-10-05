from app.cross_product_research_handoff_certification import (
    PRODUCTS, bootstrap, build_handoff, certify, compatibility_matrix, contract,
    failure_behavior_audit, product_contract, readiness, validate_handoff,
)


def test_contract_and_readiness():
    c = contract(); r = readiness(); b = bootstrap()
    assert c["library_version"] == "6.29.0"
    assert c["backend_version"] == "3.29.0"
    assert c["product_count"] == 7
    assert r["ready"] is True
    assert r["all_product_live_runtimes_certified"] is False
    assert b["route"] == "/research/integration-certification"


def test_product_registry_contains_required_products():
    assert set(PRODUCTS) == {"research-librarian", "workspace", "research-lab", "workbench", "site-intelligence", "decision-studio", "platform-core"}
    for key in PRODUCTS:
        c = product_contract(key)
        assert c["remote_runtime_certified"] is False
        assert c["live_runtime_observation_required"] is True


def test_handoff_is_authority_preserving_and_not_persisted():
    h = build_handoff({
        "target_product": "workspace",
        "library_project_id": "library-project:test",
        "target_project_id": "workspace-project:test",
        "object_refs": [{"object_id": "dataset:1", "object_type": "dataset", "authority": "library", "provenance": {"source": "test"}}],
    })
    assert h["persisted"] is False
    assert h["signed_persistence_required"] is True
    assert h["transport"]["automatic_push"] is False
    assert validate_handoff({"handoff": h})["valid"] is True


def test_handoff_rejects_authority_or_write_boundary_damage():
    h = build_handoff({"target_product": "workspace", "object_refs": [{"object_id": "x", "object_type": "artifact", "authority": "library"}]})
    h["signed_persistence_required"] = False
    assert validate_handoff(h)["valid"] is False


def test_compatibility_matrix_does_not_invent_live_runtime_observation():
    m = compatibility_matrix()
    assert m["all_structural_contracts_ready"] is True
    assert m["all_live_runtimes_observed"] is False
    assert m["live_runtime_observation_count"] == 0


def test_failure_behavior_is_fail_closed():
    audit = failure_behavior_audit()
    assert audit["state"] == "pass"
    assert audit["failure_count"] == 8
    assert all(row["data_loss_permitted"] is False for row in audit["audited"])


def test_structural_certification_passes_without_false_live_claim():
    c = certify()
    assert c["state"] == "certified"
    assert c["structural_contract_certification"] is True
    assert c["failure_behavior_certification"] is True
    assert c["all_product_live_runtimes_certified"] is False


def test_live_runtime_certification_requires_complete_observation():
    expected = product_contract("workspace")
    c = certify({"observations": {"workspace": {
        "reachable": True,
        "version": "3.76.0",
        "contract": expected["contract"],
        "authority_boundary_preserved": True,
        "provenance_preserved": True,
        "signed_write_boundary_preserved": True,
    }}})
    row = next(x for x in c["products"] if x["product_key"] == "workspace")
    assert row["live_runtime_certified"] is True
    assert c["all_product_live_runtimes_certified"] is False
