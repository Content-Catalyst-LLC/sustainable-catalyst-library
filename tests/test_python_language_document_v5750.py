from pathlib import Path
import ast,json
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text()
def main():
    assert read('library-backend/app/__init__.py').strip() == '__version__ = "2.86.0"'
    src=read('library-backend/app/language_document_service.py'); ast.parse(src)
    for needle in ['LIBRARY_VERSION = "5.75.0"','BACKEND_VERSION = "2.86.0"','CONTRACT = "sc-library-python-language-document-service/1.0"','def create_capture(','def create_derivation(','def create_corpus(','def create_resolution_case(','def create_alignment(','def scientific_document(','"original_language_remains_canonical": True','"translation_is_derived_representation": True','"automated_language_processing_implies_semantic_correctness": False']:
        assert needle in src, needle
    domain=read('library-backend/app/domain_authority.py')
    assert '"language-document-intelligence": {' in domain and '"authority": "python-backend", "state": "authoritative"' in domain
    runtime=read('library-backend/app/runtime_independence.py'); assert '"language-document"' in runtime and '5.75.0' in runtime and '2.86.0' in runtime
    main=read('library-backend/app/main.py')
    for route in ['/api/library/v1/language','/api/library/v1/language/readiness','/api/library/v1/language/captures/{capture_id:path}','/api/library/v1/language/derivations/{run_id:path}','/api/library/v1/language/corpora/{corpus_id:path}','/api/library/v1/language/entity-resolution/{case_id:path}','/api/library/v1/language/alignments/{matrix_id:path}','/api/library/v1/language/documents/{record_id:path}/intelligence','/api/library/v1/admin/language/captures','/api/library/v1/admin/language/alignments']:
        assert route in main, route
    assert '"python_language_document_authority": True' in main
    api=read('library-backend/app/independent_api.py'); assert '"library_version": "5.75.0"' in api and '"backend_version": "2.86.0"' in api and '"language-document": {"resources":' in api
    spec=json.loads(read('docs/library-api-v1-openapi.json'))
    for route in ['/language','/language/readiness','/language/captures/{capture_id}','/language/derivations/{run_id}','/language/corpora/{corpus_id}','/language/entity-resolution/{case_id}','/language/alignments/{matrix_id}','/language/documents/{record_id}/intelligence','/admin/language/captures','/admin/language/alignments']:
        assert route in spec['paths'], route
    cf=read('library-backend/app/client_framework.py'); assert 'SDK_VERSION = "0.7.0"' in cf and '"language_document_processing_client": True' in cf
    pyc=read('clients/python/sustainable_catalyst_library/client.py')
    for needle in ['def language(','def language_readiness(','def language_capture(','def language_derivation(','def linguistic_corpus(','def create_language_alignment(']: assert needle in pyc, needle
    js=read('clients/javascript/src/index.js')
    for needle in ['language()','languageReadiness()','languageCapture(','languageDerivation(','linguisticCorpus(','createLanguageAlignment(']: assert needle in js, needle
    adapter=read('sustainable-catalyst-library/includes/class-sc-library-wordpress-thin-adapter.php')
    for authority in ['language-intelligence-authority','original-language-authority','ocr-htr-transcription-authority','linguistic-corpus-authority','cross-language-resolution-authority','translation-alignment-authority','document-intelligence-authority']: assert authority in adapter, authority
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php'); assert 'Version: 5.75.0' in plugin and "SC_LIBRARY_VERSION', '5.75.0'" in plugin
    for path in ['docs/library-api-v1-openapi.json','docs/php-domain-retirement-inventory.json','docs/schemas/library-language-document-service.json','docs/schemas/library-language-document-readiness.json']: json.load(open(ROOT/path))
    print('PASS: v5.75.0 Python language/document authority, stable API, SDK clients, and WordPress boundary')
if __name__=='__main__': main()
