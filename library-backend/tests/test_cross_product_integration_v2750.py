from app.cross_product_integration import (
    PRODUCTS, registry_contract, product_contract, validate_exchange,
    build_binding, readiness, guardrails,
)


def test_registry_identity_and_products():
    r=registry_contract()
    assert r['schema']=='sc-library-cross-product-service-integration/1.0'
    assert r['library_version']=='5.64.0' and r['backend_version']=='2.75.0'
    assert set(r['products'])=={'research-librarian','workspace','research-lab','workbench','decision-studio','site-intelligence'}
    assert r['product_count']==6


def test_every_product_is_direct_non_authoritative_client():
    for key in PRODUCTS:
        c=product_contract(key)
        assert c['direct_library_api'] is True and c['wordpress_required'] is False
        assert c['authoritative_for_library_state'] is False
        assert c['service_principal_template']['principal_type']=='service'
        assert c['service_principal_template']['credential_material_in_contract'] is False


def test_workspace_exchange_allows_scoped_artifact_handoff():
    x=validate_exchange('workspace',{'capability_family':'artifacts','handoff_type':'artifact-reference','requested_scopes':['library:read','artifacts:read']})
    assert x['valid'] is True and x['errors']==[]


def test_decision_studio_cannot_escalate_write_scope():
    x=validate_exchange('decision-studio',{'requested_scopes':['artifacts:write']})
    assert x['valid'] is False and any(e.startswith('scope-not-granted:') for e in x['errors'])


def test_exchange_rejects_authority_and_auto_core_promotion():
    x=validate_exchange('research-librarian',{'claim_library_authority':True,'automatic_platform_core_promotion':True})
    assert not x['valid']
    assert 'downstream-library-authority-prohibited' in x['errors']
    assert 'automatic-platform-core-promotion-prohibited' in x['errors']


def test_binding_contains_no_secret_material():
    b=build_binding('site-intelligence',{'client_base_url':'https://site.sustainablecatalyst.com','metadata':{'environment':'production'}})
    assert b['secret_material_persisted'] is False
    assert 'secret' not in b['metadata']
    assert b['status']=='active'


def test_guardrails_preserve_library_and_core_authority():
    g=guardrails()
    assert g['products_call_library_api_directly'] is True
    assert g['downstream_products_are_library_authorities'] is False
    assert g['library_object_ids_remain_library_owned'] is True
    assert g['platform_core_owns_governed_research_meaning'] is True
    assert g['library_auto_promotes_to_platform_core'] is False


def test_readiness_contract():
    r=readiness()
    assert r['state']=='ready' and r['product_count']==6 and r['wordpress_required'] is False
