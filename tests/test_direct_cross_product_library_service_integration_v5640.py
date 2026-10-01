from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app.cross_product_integration import registry_contract, product_contract, validate_exchange
from app.independent_api import service_contract


def read(path): return (ROOT/path).read_text(encoding='utf-8')


def test_release_identity_and_versions():
    assert '__version__ = "2.75.0"' in read('library-backend/app/__init__.py')
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.64.0' in plugin and "SC_LIBRARY_VERSION', '5.64.0'" in plugin
    assert 'webVersion: "1.2.0"' in read('library-web/config.js')


def test_six_product_contracts_are_registered():
    r=registry_contract()
    expected={'research-librarian','workspace','research-lab','workbench','decision-studio','site-intelligence'}
    assert set(r['products'])==expected and r['product_count']==6


def test_direct_integration_api_routes_exist():
    main=read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/integrations',
        '/api/library/v1/integrations/readiness',
        '/api/library/v1/integrations/{product_key}',
        '/api/library/v1/integrations/{product_key}/exchange/validate',
        '/api/library/v1/admin/integrations/{product_key}/bindings',
    ]: assert route in main
    c=service_contract()
    assert 'cross-product-integration' in c['capabilities']


def test_cross_product_endpoints_do_not_depend_on_wordpress():
    main=read('library-backend/app/main.py')
    wp=read('sustainable-catalyst-library/includes/class-sc-library-cross-product-service-integration.php')
    assert 'wp-json' not in main[main.index('/api/library/v1/integrations'):main.index('/api/library/v1/public-routing')].lower()
    assert "WP_REST_Server::READABLE" in wp
    assert "'methods' => 'POST'" not in wp and "'methods' => 'PUT'" not in wp and "'methods' => 'DELETE'" not in wp


def test_scoped_exchange_validation_blocks_escalation():
    assert validate_exchange('workspace',{'capability_family':'artifacts','requested_scopes':['library:read','artifacts:read']})['valid']
    assert not validate_exchange('decision-studio',{'requested_scopes':['artifacts:write']})['valid']


def test_service_bindings_persist_no_secrets_by_contract():
    mod=read('library-backend/app/cross_product_integration.py')
    schema=read('library-backend/app/schema.sql')
    assert 'secret_material_persisted' in mod and 'service_credentials_are_never_returned_by_contract' in mod
    assert 'library_cross_product_service_bindings' in schema and 'library_cross_product_service_events' in schema
    assert 'secret_hash' not in schema[schema.index('library_cross_product_service_bindings'):]


def test_platform_core_boundary_is_preserved():
    r=registry_contract()
    assert r['platform_core_boundary']=='governed-research-meaning-and-exchange'
    for c in r['products'].values(): assert c['ownership']['governed_research_meaning']=='platform-core'


def test_machine_readable_registry_and_client_example_exist():
    reg=ROOT/'docs/cross-product-library-service-registry.json'
    assert reg.exists() and 'decision-studio' in reg.read_text()
    assert (ROOT/'docs/examples/cross_product_library_client.py').exists()
    for n in ['cross-product-service-integration.json','cross-product-service-readiness.json','cross-product-client.json','cross-product-exchange-validation.json','cross-product-service-binding.json']:
        assert (ROOT/'docs/schemas'/n).exists()


def test_runtime_authority_includes_decision_studio():
    assert '"decision-studio"' in read('library-backend/app/runtime_authority.py')


def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
