from app.release_engineering import (
    LIBRARY_VERSION,BACKEND_VERSION,WEB_VERSION,WORDPRESS_VERSION,contract,guardrails,
    validate_release_manifest,build_deployment_plan,validate_preflight,build_certification
)

def sample_manifest():
    return {"release_version":LIBRARY_VERSION,"backend_version":BACKEND_VERSION,"web_version":WEB_VERSION,"wordpress_version":WORDPRESS_VERSION,"artifacts":[
        {"component":"repository","name":"repo.zip","sha256":"1"*64,"size_bytes":100},
        {"component":"backend","name":"backend.zip","sha256":"2"*64,"size_bytes":200},
        {"component":"library-web","name":"web.zip","sha256":"3"*64,"size_bytes":50},
        {"component":"wordpress","name":"wp.zip","sha256":"4"*64,"size_bytes":300},
    ]}

def test_contract_identity_and_guardrails():
    c=contract(); assert c["library_version"]=="5.66.0" and c["backend_version"]=="2.77.0"; assert c["authority"]=="library-service"; assert c["wordpress_required_for_release_engineering"] is False; assert guardrails()["rollback_plan_required_before_apply"] is True

def test_manifest_requires_all_artifacts_and_hashes():
    r=validate_release_manifest(sample_manifest()); assert r["valid"] and len(r["artifacts"])==4 and len(r["manifest_sha256"])==64
    bad=sample_manifest(); bad["artifacts"][1]["sha256"]="bad"; assert not validate_release_manifest(bad)["valid"]

def test_deployment_plan_enforces_predecessor_and_snapshot():
    p=build_deployment_plan({"environment":"production","manifest":sample_manifest(),"current":{"library_version":"5.65.0","backend_version":"2.76.0"},"snapshot_retained":True}); assert p["valid"] and p["rollback"]["available"] is True; assert p["phases"][2]["phase"]=="backend"
    p2=build_deployment_plan({"environment":"production","manifest":sample_manifest(),"current":{"library_version":"5.64.0","backend_version":"2.75.0"},"snapshot_retained":True}); assert not p2["valid"]

def test_preflight_is_fail_closed():
    ok={"artifact_hashes_verified":True,"database_ready":True,"backend_health_ready":True,"rollback_snapshot_retained":True,"disk_capacity_ready":True,"port_allocation_safe":True}; assert validate_preflight(ok)["valid"]; ok["database_ready"]=False; assert not validate_preflight(ok)["valid"]

def test_certification_requires_postflight_and_rollback():
    plan=build_deployment_plan({"environment":"production","manifest":sample_manifest(),"current":{"library_version":"5.65.0","backend_version":"2.76.0"},"snapshot_retained":True})
    pre={"artifact_hashes_verified":True,"database_ready":True,"backend_health_ready":True,"rollback_snapshot_retained":True,"disk_capacity_ready":True,"port_allocation_safe":True}
    post={"backend_ready":True,"api_ready":True,"database_ready":True,"runtime_authority_preserved":True}
    c=build_certification({"plan":plan,"preflight":pre,"postflight":post}); assert c["certified"] and len(c["certification_sha256"])==64
