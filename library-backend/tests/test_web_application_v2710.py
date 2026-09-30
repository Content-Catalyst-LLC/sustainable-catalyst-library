from app.web_application import WEB_VERSION, application_contract, readiness


def test_contract_identity():
    c=application_contract()
    assert c["schema"]=="sc-library-web-application/1.0"
    assert c["library_version"]=="5.60.0"
    assert c["backend_version"]=="2.71.0"
    assert c["web_version"]==WEB_VERSION=="1.0.0"
    assert c["deployment_model"]=="independent-static-web-service"


def test_wordpress_is_not_a_web_runtime_dependency():
    c=application_contract()
    assert c["wordpress"]["required"] is False
    assert c["wordpress"]["request_path_dependency"] is False
    assert c["guardrails"]["api_v1_is_authoritative_service_boundary"] is True
    assert c["guardrails"]["research_state_owned_by_web_client"] is False
    assert c["guardrails"]["client_side_secrets_permitted"] is False


def test_foundation_surfaces():
    c=application_contract(); by_id={x["id"]:x for x in c["surfaces"]}
    assert set(by_id)=={"search","reader","discover","system"}
    assert by_id["search"]["api"]=="/api/library/v1/search"
    assert by_id["reader"]["api"]=="/api/library/v1/records/{record_id}"


def test_readiness():
    r=readiness()
    assert r["state"]=="ready"
    assert r["surface_count"]==4
    assert r["wordpress_required"] is False
