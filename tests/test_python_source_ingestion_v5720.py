from pathlib import Path
import ast, json
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text()

def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.83.0"'
    src=read('library-backend/app/source_ingestion.py'); ast.parse(src)
    for needle in [
        'LIBRARY_VERSION = "5.72.0"', 'BACKEND_VERSION = "2.83.0"',
        'CONTRACT = "sc-library-python-source-ingestion-service/1.0"',
        'NORMALIZATION_CONTRACT = "sc-library-source-normalization/1.0"',
        'def normalize_payload(', 'def ingest_normalized(', 'def source_state(', 'def readiness(',
        'RecordBatch.model_validate(', 'ingest_records(batch, request_hash)',
        '"python_is_source_ingestion_authority": True',
        '"wordpress_php_is_source_ingestion_authority": False',
        '"normalization_lineage_persisted": True',
        '"legacy_v1_ingest_routes_use_python_normalization": True',
    ]: assert needle in src, needle

    schema=read('library-backend/app/schema.sql')
    assert 'CREATE TABLE IF NOT EXISTS library_ingestion_normalization_runs' in schema

    domain=read('library-backend/app/domain_authority.py')
    assert '"source-ingestion-normalization": {' in domain
    assert '"api": ["/api/library/v1/ingestion", "/api/library/v1/admin/ingestion/records"]' in domain

    runtime=read('library-backend/app/runtime_independence.py')
    assert '"ingestion-service"' in runtime
    assert '5.72.0' in runtime and '2.83.0' in runtime

    main=read('library-backend/app/main.py')
    for route in [
        '/api/library/v1/ingestion', '/api/library/v1/ingestion/readiness',
        '/api/library/v1/admin/ingestion/normalize', '/api/library/v1/admin/ingestion/records',
        '/api/library/v1/admin/ingestion/sources/{source_key:path}',
    ]: assert route in main, route
    assert 'return ingest_library_normalized_records(legacy_payload, sha256_hex(body))' in main

    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in ['/ingestion','/ingestion/readiness','/admin/ingestion/normalize','/admin/ingestion/records','/admin/ingestion/sources/{source_key}']:
        assert route in spec['paths'], route

    cf=read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.4.0"' in cf and '"source_ingestion_client": True' in cf
    pyclient=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def ingestion(', 'def ingestion_readiness(', 'def normalize_ingestion(', 'def ingest_source_records(', 'def source_ingestion_state(']: assert needle in pyclient, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['ingestion()', 'ingestionReadiness()', 'normalizeIngestion(', 'ingestSourceRecords(', 'sourceIngestionState(']: assert needle in js, needle

    inv=json.loads(read('docs/php-domain-retirement-inventory.json')); by={x['path']:x for x in inv['entries']}
    for rel in [
        'sustainable-catalyst-library/includes/class-sc-library-indexer.php',
        'sustainable-catalyst-library/includes/class-sc-library-scanner.php',
        'sustainable-catalyst-library/includes/class-sc-library-ingestion-job-fabric.php',
        'sustainable-catalyst-library/includes/class-sc-library-global-source-federation-registry.php',
        'sustainable-catalyst-library/includes/class-sc-library-institutional-research-sources.php',
    ]: assert by[rel]['classification']=='retire-candidate', rel

    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for authority in ['source-ingestion-authority','source-normalization-authority']:
        assert authority in adapter, authority
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.72.0' in plugin and "SC_LIBRARY_VERSION', '5.72.0'" in plugin
    print('PASS: v5.72.0 Python source-ingestion authority, normalization lineage, SDK clients, legacy-route convergence, and PHP retirement boundary')

if __name__=='__main__': main()
