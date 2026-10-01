from pathlib import Path
import ast, json
ROOT=Path(__file__).resolve().parents[1]

def read(rel): return (ROOT/rel).read_text()

def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.82.0"'
    state=read('library-backend/app/research_state.py'); ast.parse(state)
    for needle in [
        'LIBRARY_VERSION = "5.71.0"', 'BACKEND_VERSION = "2.82.0"',
        'CONTRACT = "sc-library-python-research-state-service/1.0"',
        'def upsert_project(', 'def add_project_reference(', 'def create_source_bundle(',
        'def create_saved_search(', 'def create_watchlist(', 'def enqueue_research_item(',
        'def create_collection(', 'def add_collection_item(', 'def owner_state(',
        '"python_is_research_state_authority": True',
        '"wordpress_php_is_research_state_authority": False',
        '"source_bundles_are_references_only": True',
        '"watchlists_are_passive_without_background_monitoring": True',
    ]: assert needle in state, needle

    schema=read('library-backend/app/schema.sql')
    for table in [
        'library_research_projects','library_project_references','library_source_bundles',
        'library_source_bundle_members','library_saved_searches','library_watchlists',
        'library_research_queue_items','library_research_collections','library_research_collection_items']:
        assert f'CREATE TABLE IF NOT EXISTS {table}' in schema, table

    domain=read('library-backend/app/domain_authority.py')
    assert '"research-projects-collections-saved-state": {' in domain
    assert '"api": ["/api/library/v1/research-state", "/api/library/v1/admin/research-state/owners/{owner_identity_id}"]' in domain

    runtime=read('library-backend/app/runtime_independence.py')
    assert '"research-state"' in runtime
    assert 'LIBRARY_VERSION="5.71.0"' in runtime or 'LIBRARY_VERSION = "5.71.0"' in runtime
    assert 'BACKEND_VERSION="2.82.0"' in runtime or 'BACKEND_VERSION = "2.82.0"' in runtime

    main=read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/research-state', '/api/library/v1/research-state/readiness',
        '/api/library/v1/admin/research-state/owners/{owner_identity_id:path}',
        '/api/library/v1/admin/research-state/projects',
        '/api/library/v1/admin/research-state/projects/{project_id:path}/references',
        '/api/library/v1/admin/research-state/projects/{project_id:path}/bundles',
        '/api/library/v1/admin/research-state/saved-searches',
        '/api/library/v1/admin/research-state/watchlists',
        '/api/library/v1/admin/research-state/queue',
        '/api/library/v1/admin/research-state/collections',
        '/api/library/v1/admin/research-state/collections/{collection_id:path}/items',
    ]: assert route in main, route

    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in [
        '/research-state','/research-state/readiness','/admin/research-state/owners/{owner_identity_id}',
        '/admin/research-state/projects','/admin/research-state/projects/{project_id}/references',
        '/admin/research-state/projects/{project_id}/bundles','/admin/research-state/saved-searches',
        '/admin/research-state/watchlists','/admin/research-state/queue','/admin/research-state/collections',
        '/admin/research-state/collections/{collection_id}/items']:
        assert route in spec['paths'], route

    cf=read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.3.0"' in cf and '"research_state_client": True' in cf
    pyclient=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def get_signed(', 'def research_state(', 'def research_state_for_owner(', 'def create_research_project(', 'def save_search(', 'def save_watchlist(', 'def enqueue_research(', 'def create_collection(']: assert needle in pyclient, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['getSigned(', 'researchState()', 'researchStateForOwner(', 'createResearchProject(', 'saveSearch(', 'saveWatchlist(', 'enqueueResearch(', 'createCollection(']: assert needle in js, needle

    inv=json.loads(read('docs/php-domain-retirement-inventory.json'))
    by={x['path']:x for x in inv['entries']}
    for rel in [
        'sustainable-catalyst-library/includes/class-sc-library-unified-research-projects-source-bundles.php',
        'sustainable-catalyst-library/includes/class-sc-library-saved-searches-watchlists-queue.php',
        'sustainable-catalyst-library/includes/class-sc-library-personal-collections-recommendations.php',
        'sustainable-catalyst-library/includes/class-sc-library-research-collections-curated-spaces.php',
    ]: assert by[rel]['classification']=='retire-candidate', rel

    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for authority in ['research-state-authority','project-state-authority','collection-state-authority','saved-research-state-authority']:
        assert authority in adapter, authority
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.71.0' in plugin and "SC_LIBRARY_VERSION', '5.71.0'" in plugin
    print('PASS: v5.71.0 Python research projects, collections, saved-state authority, SDK clients, and PHP retirement boundary')

if __name__=='__main__': main()
