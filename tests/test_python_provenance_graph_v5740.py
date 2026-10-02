from pathlib import Path
import ast,json
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text()

def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.85.0"'
    src=read('library-backend/app/provenance_graph_service.py'); ast.parse(src)
    for needle in [
        'LIBRARY_VERSION = "5.74.0"','BACKEND_VERSION = "2.85.0"',
        'CONTRACT = "sc-library-python-provenance-citation-evidence-graph/1.0"',
        'def record_provenance(','def citations_for_record(','def evidence_graph(','def readiness(',
        'CitationCreateRequest.model_validate(','citation_graph(','graph_neighborhood(',
        '"python_is_provenance_authority": True','"python_is_citation_authority": True',
        '"python_is_evidence_graph_authority": True','"graph_relationship_implies_causality": False',
    ]: assert needle in src, needle
    domain=read('library-backend/app/domain_authority.py')
    assert '"provenance-citation-evidence-graph": {' in domain
    assert '"authority": "python-backend", "state": "authoritative"' in domain
    runtime=read('library-backend/app/runtime_independence.py')
    assert '"provenance-graph"' in runtime and '5.74.0' in runtime and '2.85.0' in runtime
    main=read('library-backend/app/main.py')
    for route in ['/api/library/v1/provenance','/api/library/v1/provenance/readiness','/api/library/v1/provenance/records/{record_id:path}','/api/library/v1/citations/{record_id:path}','/api/library/v1/evidence-graph/{record_id:path}','/api/library/v1/admin/citations']:
        assert route in main, route
    assert '"python_provenance_graph_authority": True' in main
    api=read('library-backend/app/independent_api.py')
    assert '"library_version": "5.74.0"' in api and '"backend_version": "2.85.0"' in api
    assert '"provenance-graph": {"resources":' in api
    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in ['/provenance','/provenance/readiness','/provenance/records/{record_id}','/citations/{record_id}','/evidence-graph/{record_id}','/admin/citations']:
        assert route in spec['paths'], route
    cf=read('library-backend/app/client_framework.py')
    assert 'SDK_VERSION = "0.6.0"' in cf and '"provenance_citation_evidence_graph_client": True' in cf
    pyclient=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def provenance(','def provenance_readiness(','def record_provenance(','def citations(','def evidence_graph(','def create_citation(']: assert needle in pyclient, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['provenance()','provenanceReadiness()','recordProvenance(','citations(','evidenceGraph(','createCitation(']: assert needle in js, needle
    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for authority in ['provenance-authority','citation-authority','evidence-graph-authority']: assert authority in adapter, authority
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.74.0' in plugin and "SC_LIBRARY_VERSION', '5.74.0'" in plugin
    for p in ['docs/schemas/library-provenance-citation-evidence-graph.json','docs/schemas/library-record-provenance.json','docs/schemas/library-evidence-graph.json','docs/php-domain-retirement-inventory.json']:
        json.load(open(ROOT/p))
    print('PASS: v5.74.0 Python provenance/citation/evidence-graph authority, stable API, SDK clients, and WordPress boundary')
if __name__=='__main__': main()
