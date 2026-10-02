from pathlib import Path
import ast,json
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text()
def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.87.0"'
    src=read('library-backend/app/research_package_service.py'); ast.parse(src)
    for needle in ['LIBRARY_VERSION = "5.76.0"','BACKEND_VERSION = "2.87.0"','CONTRACT = "sc-library-python-research-package-reproducibility-service/1.0"','PACKAGE_CONTRACT = "sc-library-research-reproducibility-package/1.0"','def validate_package_payload(','def build_package(','def create_package(','def get_package(','def verify_package(','"research_package_is_second_artifact_store": False','"package_verification_reruns_research_by_default": False','"artifact_integrity_implies_research_truth": False']: assert needle in src,needle
    domain=read('library-backend/app/domain_authority.py'); assert '"research-package-reproducibility": {' in domain and '"authority": "python-backend", "state": "authoritative"' in domain
    runtime=read('library-backend/app/runtime_independence.py'); assert '"reproducibility-service"' in runtime and '5.76.0' in runtime and '2.87.0' in runtime
    main_src=read('library-backend/app/main.py')
    for route in ['/api/library/v1/reproducibility','/api/library/v1/reproducibility/readiness','/api/library/v1/reproducibility/runtime','/api/library/v1/reproducibility/packages/{package_id:path}','/api/library/v1/admin/reproducibility/packages/validate','/api/library/v1/admin/reproducibility/packages','/api/library/v1/admin/reproducibility/packages/{package_id:path}/verify']: assert route in main_src,route
    assert '"python_research_package_reproducibility_authority": True' in main_src
    api=read('library-backend/app/independent_api.py'); assert '"library_version": "5.76.0"' in api and '"backend_version": "2.87.0"' in api and '"research-package-reproducibility": {"resources":' in api
    cf=read('library-backend/app/client_framework.py'); assert 'SDK_VERSION = "0.8.0"' in cf and '"research_package_reproducibility_client": True' in cf
    pyc=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def reproducibility(','def reproducibility_readiness(','def research_package(','def create_research_package(','def verify_research_package(']: assert needle in pyc,needle
    js=read('clients/javascript/src/index.js')
    for needle in ['reproducibility()','reproducibilityReadiness()','researchPackage(','createResearchPackage(','verifyResearchPackage(']: assert needle in js,needle
    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for a in ['research-package-authority','reproducibility-authority']: assert a in adapter,a
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php'); assert 'Version: 5.76.0' in plugin and "SC_LIBRARY_VERSION', '5.76.0'" in plugin
    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for path in ['/reproducibility','/reproducibility/readiness','/reproducibility/runtime','/reproducibility/packages/{package_id}','/admin/reproducibility/packages/validate','/admin/reproducibility/packages','/admin/reproducibility/packages/{package_id}/verify']: assert path in spec['paths'],path
    for path in ['docs/php-domain-retirement-inventory.json','docs/schemas/library-research-package-service.json','docs/schemas/library-research-reproducibility-package.json']: json.load(open(ROOT/path))
    print('PASS: v5.76.0 Python research-package/reproducibility authority, stable API, SDK clients, and WordPress boundary')
if __name__=='__main__': main()
