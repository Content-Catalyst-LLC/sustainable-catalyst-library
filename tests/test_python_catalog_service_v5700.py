from pathlib import Path
import ast, json
ROOT=Path(__file__).resolve().parents[1]


def read(rel):
    return (ROOT/rel).read_text()


def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.81.0"'
    catalog=read('library-backend/app/catalog_service.py')
    ast.parse(catalog)
    for needle in [
        'LIBRARY_VERSION = "5.70.0"',
        'BACKEND_VERSION = "2.81.0"',
        'CONTRACT = "sc-library-python-catalog-service/1.0"',
        'RESEARCH_OBJECT_CONTRACT = "sc-library-research-object/1.0"',
        'def validate_upsert_payload(',
        'def research_object_envelope(',
        'def upsert_record(',
        'def delete_catalog_record(',
        'ingest_records(RecordBatch(',
        '"python_is_catalog_domain_authority": True',
        '"wordpress_php_is_catalog_domain_authority": False',
        '"postgresql_is_catalog_state_authority": True',
    ]:
        assert needle in catalog, needle

    domain=read('library-backend/app/domain_authority.py')
    assert '"publication-catalog-write": {' in domain
    assert '"authority": "python-backend", "state": "authoritative"' in domain
    assert '"wordpress_role": "presentation-and-api-client-only"' in domain

    runtime=read('library-backend/app/runtime_independence.py')
    assert '"catalog-service"' in runtime
    assert 'LIBRARY_VERSION = "5.70.0"' in runtime and 'BACKEND_VERSION = "2.81.0"' in runtime

    cf=read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.2.0"' in cf and '"catalog_domain_client": True' in cf

    main_py=read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/catalog',
        '/api/library/v1/catalog/readiness',
        '/api/library/v1/research-objects/{record_id:path}',
        '/api/library/v1/admin/catalog/records/validate',
        '/api/library/v1/admin/catalog/records',
        '/api/library/v1/admin/catalog/records/{record_id:path}',
    ]:
        assert route in main_py, route

    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in ['/catalog','/catalog/readiness','/research-objects/{record_id}','/admin/catalog/records/validate','/admin/catalog/records','/admin/catalog/records/{record_id}']:
        assert route in spec['paths'], route

    inv=json.loads(read('docs/php-domain-retirement-inventory.json'))
    actual={str(x.relative_to(ROOT)) for x in (ROOT/'sustainable-catalyst-library/includes').glob('*.php')}
    declared={x['path'] for x in inv['entries']}
    assert actual==declared, f'PHP inventory drift: added={sorted(actual-declared)} missing={sorted(declared-actual)}'
    by_path={x['path']:x for x in inv['entries']}
    for rel in [
        'sustainable-catalyst-library/includes/class-sc-library-publications.php',
        'sustainable-catalyst-library/includes/class-sc-library-indexer.php',
        'sustainable-catalyst-library/includes/class-sc-library-scanner.php',
        'sustainable-catalyst-library/includes/class-sc-library-relationships.php',
    ]:
        assert by_path[rel]['classification']=='retire-candidate', rel

    pyclient=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def catalog(', 'def catalog_readiness(', 'def research_object(', 'def validate_catalog_record(', 'def upsert_catalog_record(', 'def delete_catalog_record(']:
        assert needle in pyclient, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['catalog()', 'catalogReadiness()', 'researchObject(', 'validateCatalogRecord(', 'upsertCatalogRecord(', 'deleteCatalogRecord(']:
        assert needle in js, needle

    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.70.0' in plugin and "SC_LIBRARY_VERSION', '5.70.0'" in plugin
    print('PASS: v5.70.0 Python catalog authority, API surface, SDK clients, and PHP retirement boundary')

if __name__=='__main__':
    main()
