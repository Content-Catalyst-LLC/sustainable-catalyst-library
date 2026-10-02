from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]

def read(rel):
    return (ROOT / rel).read_text()


def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.88.0"'
    src = read('library-backend/app/connector_federation_service.py')
    ast.parse(src)
    for needle in [
        'LIBRARY_VERSION = "5.77.0"',
        'BACKEND_VERSION = "2.88.0"',
        'CONTRACT = "sc-library-python-connector-federation-runtime/1.0"',
        'def plan_execution(',
        'def connector_status(',
        'def federation_certification(',
        '"wordpress_connector_fallback_allowed": False',
        '"pending_adapter_is_silently_executed": False',
        '"source_quality_signals_separate_from_user_trust_choices": True',
    ]:
        assert needle in src, needle

    domain = read('library-backend/app/domain_authority.py')
    assert '"connector-federation-runtime": {' in domain
    assert '"state": "authoritative"' in domain
    assert '/api/library/v1/federation' in domain

    runtime = read('library-backend/app/runtime_independence.py')
    assert '"connector-federation"' in runtime
    assert '5.77.0' in runtime and '2.88.0' in runtime

    main_src = read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/federation',
        '/api/library/v1/federation/readiness',
        '/api/library/v1/federation/sources/{source_id}',
        '/api/library/v1/federation/connectors/{connector_id}',
        '/api/library/v1/federation/connectors/{connector_id}/status',
        '/api/library/v1/federation/collections',
        '/api/library/v1/federation/certification',
        '/api/library/v1/admin/federation/connectors/validate',
        '/api/library/v1/admin/federation/plan',
        '/api/library/v1/admin/federation/certifications/validate',
        '/api/library/v1/admin/federation/certifications',
    ]:
        assert route in main_src, route
    assert '"python_connector_federation_authority": True' in main_src
    assert '"wordpress_connector_fallback_allowed": False' in main_src

    api = read('library-backend/app/independent_api.py')
    assert '"library_version": "5.77.0"' in api
    assert '"backend_version": "2.88.0"' in api
    assert '"connector-federation-runtime": {"resources":' in api

    cf = read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.9.0"' in cf
    assert '"connector_federation_client": True' in cf

    pyc = read('clients/python/sustainable_catalyst_library/client.py')
    for needle in [
        'def federation(', 'def federation_readiness(', 'def federation_source(',
        'def federation_connector(', 'def federation_connector_status(',
        'def plan_federation_connector(', 'def validate_federation_connector(',
    ]:
        assert needle in pyc, needle

    js = read('clients/javascript/src/index.js')
    for needle in [
        'federation(filters={})', 'federationReadiness()', 'federationSource(',
        'federationConnector(', 'federationConnectorStatus(',
        'planFederationConnector(', 'validateFederationConnector(',
    ]:
        assert needle in js, needle

    adapter = read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for name in ['connector-routing-authority', 'connector-execution-policy-authority', 'global-source-registry-authority']:
        assert name in adapter, name
    plugin = read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.77.0' in plugin
    assert "SC_LIBRARY_VERSION', '5.77.0'" in plugin

    spec = json.loads(read('docs/library-api-v1-openapi.json'))
    for path in [
        '/federation', '/federation/readiness', '/federation/sources/{source_id}',
        '/federation/connectors/{connector_id}', '/federation/connectors/{connector_id}/status',
        '/federation/collections', '/federation/certification',
        '/admin/federation/connectors/validate', '/admin/federation/plan',
        '/admin/federation/certifications/validate', '/admin/federation/certifications',
    ]:
        assert path in spec['paths'], path

    for path in [
        'docs/php-domain-retirement-inventory.json',
        'docs/schemas/library-connector-federation-runtime.json',
        'docs/schemas/library-connector-execution-plan.json',
        'docs/schemas/library-connector-runtime-status.json',
    ]:
        json.load(open(ROOT / path))

    print('PASS: v5.77.0 Python connector/federation authority, routing policy, API, SDK clients, and WordPress boundary')

if __name__ == '__main__':
    main()
