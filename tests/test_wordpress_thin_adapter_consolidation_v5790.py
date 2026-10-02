from pathlib import Path
import ast, json

ROOT = Path(__file__).resolve().parents[1]
def read(rel): return (ROOT / rel).read_text()

def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.90.0"'
    adapter = read('library-backend/app/wordpress_thin_adapter.py')
    ast.parse(adapter)
    for needle in [
        'LIBRARY_VERSION = "5.79.0"','BACKEND_VERSION = "2.90.0"',
        'CONSOLIDATION_CONTRACT = "sc-library-wordpress-thin-adapter-consolidation/1.0"',
        'CERTIFICATION_CONTRACT = "sc-library-wordpress-thin-adapter-certification/1.0"',
        'def consolidation_manifest()','def certify_consolidation(',
        '"legacy_php_domain_modules_are_compatibility_only": True',
        '"new_domain_behavior_must_use_library_api_v1": True',
        '"php_domain_authority_files": 0',
    ]: assert needle in adapter, needle
    main_src = read('library-backend/app/main.py')
    for route in ['/api/library/v1/wordpress-adapter','/api/library/v1/wordpress-adapter/readiness','/api/library/v1/wordpress-adapter/consolidation','/api/library/v1/admin/wordpress-adapter/consolidation/certify']:
        assert route in main_src, route
    for needle in ['"wordpress_thin_adapter_consolidated": True','"wordpress_adapter_certification_authority": "python-backend"','"wordpress_required_for_library_runtime": False','"wordpress_legacy_domain_authority": False']:
        assert needle in main_src, needle
    runtime = read('library-backend/app/runtime_independence.py')
    assert '"thin-adapter-consolidation"' in runtime and '5.79.0' in runtime and '2.90.0' in runtime
    domain = read('library-backend/app/domain_authority.py')
    assert '"wordpress-thin-adapter": {' in domain and '"state": "consolidated"' in domain
    api = read('library-backend/app/independent_api.py')
    assert '"library_version": "5.79.0"' in api and '"backend_version": "2.90.0"' in api
    assert '/api/library/v1/wordpress-adapter/consolidation' in api and 'consolidation-certification' in api
    framework = read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.11.0"' in framework and '"wordpress_thin_adapter_consolidation_client": True' in framework
    pyc = read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def wordpress_adapter(self)','def wordpress_adapter_readiness(self)','def wordpress_adapter_consolidation(self)','def certify_wordpress_adapter_consolidation(']: assert needle in pyc, needle
    js = read('clients/javascript/src/index.js')
    for needle in ['wordpressAdapter()','wordpressAdapterReadiness()','wordpressAdapterConsolidation()','certifyWordpressAdapterConsolidation(payload)']: assert needle in js, needle
    dts = read('clients/javascript/src/index.d.ts')
    assert 'wordpressAdapter():Promise<any>;' in dts
    assert 'certifyWordpressAdapterConsolidation(payload:Record<string,unknown>):Promise<any>;' in dts
    php = read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for needle in ["public const VERSION = '5.79.0';",'local_consolidation_claim()',"'php_domain_authority_files' => 0","'retire_candidate_files' => 129",'adapter/consolidation']: assert needle in php, needle
    plugin = read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.79.0' in plugin and "SC_LIBRARY_VERSION', '5.79.0'" in plugin
    assert 'SC_LIBRARY_WORDPRESS_THIN_ADAPTER_CONSOLIDATED' in plugin and 'SC_LIBRARY_WORDPRESS_LEGACY_DOMAIN_AUTHORITY' in plugin
    bridge = read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    assert 'WordPress is an optional editorial/presentation adapter' in bridge
    assert 'WordPress remains authoritative for users, editorial state, and public URLs.' not in bridge
    inventory = json.loads(read('docs/php-domain-retirement-inventory.json'))
    assert inventory['library_version'] == '5.79.0'
    c = inventory.get('consolidation') or {}
    assert c.get('php_domain_authority_files') == 0 and c.get('retire_candidate_files') == 129
    assert c.get('next_gate') == 'v5.80.0-independent-library-application-certification'
    spec = json.loads(read('docs/library-api-v1-openapi.json'))
    for path in ['/wordpress-adapter','/wordpress-adapter/readiness','/wordpress-adapter/consolidation','/admin/wordpress-adapter/consolidation/certify']:
        assert path in spec['paths'], path
    print('PASS: v5.79.0 WordPress thin-adapter consolidation, certification, API/SDK clients and compatibility boundary')

if __name__ == '__main__': main()
