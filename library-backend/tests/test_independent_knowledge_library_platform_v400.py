from app.independent_knowledge_library_platform import (
    LIBRARY_VERSION, BACKEND_VERSION, WEB_VERSION, SDK_VERSION,
    contract, release_manifest, default_evidence, evaluate, export_certification, guardrails
)

def test_generations_and_platform_identity():
    c = contract()
    assert (LIBRARY_VERSION, BACKEND_VERSION, WEB_VERSION, SDK_VERSION) == ("7.0.0", "4.0.0", "3.0.0", "2.0.0")
    assert c["generation"] == 7
    assert c["state"] == "stable-platform-baseline"
    assert c["application_mode"] == "independent-primary"
    assert c["release_policy"]["maintenance_line"] == "7.0.x"
    assert c["release_policy"]["next_feature_release"] is None

def test_api_v1_and_authority_guardrails():
    g = guardrails()
    assert g["api_v1_remains_stability_boundary"] is True
    assert g["api_v1_breaking_change"] is False
    assert g["python_backend_is_application_runtime_authority"] is True
    assert g["postgresql_is_structured_state_authority"] is True
    assert g["library_web_is_public_origin_authority"] is True
    assert g["wordpress_required"] is False
    assert g["wordpress_authoritative"] is False

def test_release_manifest_is_non_destructive():
    r = release_manifest()
    assert r["release_name"] == "Independent Knowledge Library Platform"
    assert r["database_migration_required"] is False
    assert r["destructive_database_migration"] is False
    assert r["api_v1_breaking_change"] is False
    assert r["feature_line_status"] == "stabilized"

def test_default_evidence_certifies():
    result = evaluate(default_evidence())
    assert result["certified"] is True
    assert result["state"] == "certified"
    assert result["errors"] == []

def test_missing_predecessor_certification_blocks():
    evidence = default_evidence()
    evidence["production_assertions"]["predecessor_639_certified"] = False
    result = evaluate(evidence)
    assert result["certified"] is False
    assert any("predecessor_639_certified" in e for e in result["errors"])

def test_generation_drift_blocks():
    evidence = default_evidence()
    evidence["generations"]["web"] = "2.39.0"
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "generation-mismatch:web" in result["errors"]

def test_wordpress_authority_blocks():
    evidence = default_evidence()
    evidence["authority"]["wordpress_authoritative"] = True
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "authority-assertion-failed:wordpress_authoritative" in result["errors"]

def test_truth_promotion_guardrail_blocks():
    evidence = default_evidence()
    evidence["guardrails"]["automatic_truth_promotion"] = True
    result = evaluate(evidence)
    assert result["certified"] is False
    assert "guardrail-failed:automatic_truth_promotion" in result["errors"]

def test_export_is_deterministic():
    a = export_certification(default_evidence())
    b = export_certification(default_evidence())
    assert a["sha256"] == b["sha256"]
    assert a["content"] == b["content"]
    assert a["certified"] is True
