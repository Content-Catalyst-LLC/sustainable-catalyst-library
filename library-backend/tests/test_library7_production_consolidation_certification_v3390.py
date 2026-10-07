from copy import deepcopy
import hashlib

from app.library7_production_consolidation_certification import (
    contract,
    default_evidence,
    evaluate,
    export_certification,
    inventory,
    readiness,
)


def test_contract_targets_library7_without_auto_certifying():
    c = contract()
    r = readiness()
    assert c["library_version"] == "6.39.0"
    assert c["backend_version"] == "3.39.0"
    assert c["web_version"] == "2.39.0"
    assert c["sdk_version"] == "1.39.0"
    assert c["next_release"] == "7.0.0"
    assert r["ready"] is True
    assert r["library7_certified"] is False
    assert r["production_certification_required"] is True


def test_inventory_has_runtime_surface_authority_and_deployment_gates():
    i = inventory()
    assert "library-backend" in i["runtime_components"]
    assert "library-web" in i["runtime_components"]
    assert "research-audit" in i["surface_probes"]
    assert "research-federation" in i["surface_probes"]
    assert "wordpress_required_false" in i["authority_assertions"]
    assert "rollback_backup_available" in i["deployment_assertions"]
    assert i["bind_contract"]["backend"] == "127.0.0.1:8087"
    assert i["bind_contract"]["web"] == "127.0.0.1:8095"


def test_complete_evidence_certifies_release_readiness():
    result = evaluate(default_evidence())
    assert result["certified"] is True
    assert result["library7_ready"] is True
    assert result["errors"] == []
    assert result["next_release"] == "7.0.0"


def test_missing_critical_surface_blocks_certification():
    evidence = default_evidence()
    evidence["surface_probes"]["research-audit"] = False
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "critical-surface-not-ready:research-audit" in result["errors"]


def test_wordpress_dependency_or_authority_blocks_certification():
    evidence = default_evidence()
    evidence["authority_assertions"]["wordpress_required_false"] = False
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "authority-boundary-failed:wordpress_required_false" in result["errors"]


def test_wrong_generation_or_bind_blocks_certification():
    evidence = default_evidence()
    evidence["generations"]["web"] = "2.38.0"
    evidence["deployment_assertions"]["web_bind"] = "127.0.0.1:8094"
    result = evaluate(evidence)
    assert result["certified"] is False
    assert any(x.startswith("generation-mismatch:web:") for x in result["errors"])
    assert any(x.startswith("deployment-bind-mismatch:web_bind:") for x in result["errors"])


def test_rollback_or_guardrail_failure_blocks_certification():
    evidence = default_evidence()
    evidence["deployment_assertions"]["rollback_backup_available"] = False
    evidence["preserved_guardrails"]["automatic_truth_promotion_false"] = False
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "deployment-assertion-failed:rollback_backup_available" in result["errors"]
    assert "preserved-guardrail-failed:automatic_truth_promotion_false" in result["errors"]


def test_export_is_deterministic_and_does_not_promote_truth():
    evidence = default_evidence()
    one = export_certification(evidence)
    two = export_certification(deepcopy(evidence))
    assert one["content_sha256"] == two["content_sha256"]
    assert hashlib.sha256(one["content"].encode()).hexdigest() == one["content_sha256"]
    assert one["certified"] is True
    g = one["guardrails"]
    assert g["certification_implies_research_truth"] is False
    assert g["certification_implies_scientific_validity"] is False
    assert g["automatic_truth_promotion"] is False
    assert g["automatic_platform_core_promotion"] is False
