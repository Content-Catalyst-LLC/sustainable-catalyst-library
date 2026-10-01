from pathlib import Path
import ast, json
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text()

def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.84.0"'
    src=read('library-backend/app/retrieval_orchestration.py'); ast.parse(src)
    for needle in [
        'LIBRARY_VERSION = "5.73.0"', 'BACKEND_VERSION = "2.84.0"',
        'CONTRACT = "sc-library-python-retrieval-orchestration/1.0"',
        'PLAN_CONTRACT = "sc-library-retrieval-plan/1.0"',
        'def normalize_request(', 'def plan(', 'def execute(', 'def facet_snapshot(', 'def readiness(',
        'hybrid_search_records(', 'rerank_candidates(', 'rerank_results(', 'facets()',
        '"python_is_retrieval_orchestration_authority": True',
        '"wordpress_php_is_retrieval_orchestration_authority": False',
        '"legacy_search_routes_converge_on_orchestrator": True',
    ]: assert needle in src, needle

    domain=read('library-backend/app/domain_authority.py')
    assert '"retrieval-orchestration": {' in domain
    assert '"state": "authoritative"' in domain
    assert '"/api/library/v1/retrieval/search"' in domain

    runtime=read('library-backend/app/runtime_independence.py')
    assert '"retrieval-orchestration"' in runtime
    assert '5.73.0' in runtime and '2.84.0' in runtime

    main=read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/retrieval', '/api/library/v1/retrieval/readiness',
        '/api/library/v1/retrieval/facets', '/api/library/v1/retrieval/search',
        '/api/library/v1/admin/retrieval/plan', '/api/library/v1/admin/retrieval/search',
    ]: assert route in main, route
    assert 'result=orchestrate_library_search(' in main
    assert 'return orchestrate_library_search({' in main
    assert '"library_version":"5.73.0"' in main
    assert '"python_retrieval_orchestration_authority": True' in main

    api=read('library-backend/app/independent_api.py')
    assert '"library_version": "5.73.0"' in api and '"backend_version": "2.84.0"' in api
    assert '"retrieval-orchestration": {"resources":' in api

    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in ['/retrieval','/retrieval/readiness','/retrieval/facets','/retrieval/search','/admin/retrieval/plan','/admin/retrieval/search']:
        assert route in spec['paths'], route

    cf=read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.5.0"' in cf and '"retrieval_orchestration_client": True' in cf
    pyclient=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def retrieval(', 'def retrieval_readiness(', 'def retrieval_facets(', 'def retrieval_search(', 'def retrieval_plan(', 'def retrieval_search_advanced(']: assert needle in pyclient, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['retrieval()', 'retrievalReadiness()', 'retrievalFacets()', 'retrievalSearch(', 'retrievalPlan(', 'retrievalSearchAdvanced(']: assert needle in js, needle

    inv=json.loads(read('docs/php-domain-retirement-inventory.json')); by={x['path']:x for x in inv['entries']}
    for rel in [
        'sustainable-catalyst-library/includes/class-sc-library-dynamic-explorer.php',
        'sustainable-catalyst-library/includes/class-sc-library-retrieval-evaluation.php',
        'sustainable-catalyst-library/includes/class-sc-library-global-research-discovery-federated-search.php',
        'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php',
    ]: assert by[rel]['classification']=='retire-candidate', rel

    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for authority in ['retrieval-orchestration-authority','search-ranking-authority']:
        assert authority in adapter, authority
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.73.0' in plugin and "SC_LIBRARY_VERSION', '5.73.0'" in plugin
    print('PASS: v5.73.0 Python retrieval/search orchestration authority, stable-route convergence, SDK clients, and PHP retirement boundary')

if __name__=='__main__': main()
