from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import translation_alignment as ta


def read(p): return (ROOT/p).read_text(encoding='utf-8')

def sample(kind='translation'):
    source_text='Bonjour le monde.'
    target_text='Hello world.' if kind=='translation' else 'Bonjour le monde.'
    t1={'start':0,'end':5,'text':'Hello'} if kind=='translation' else {'start':0,'end':7,'text':'Bonjour'}
    t3={'start':6,'end':11,'text':'world'} if kind=='translation' else {'start':11,'end':16,'text':'monde'}
    return {
      'source':{'representation_id':'representation:source','representation_kind':'original','language_bcp47':'fr','script_iso15924':'Latn','text':source_text},
      'target':{'representation_id':'representation:target','representation_kind':kind,'language_bcp47':'en' if kind=='translation' else 'fr-Latn','script_iso15924':'Latn','text':target_text,'transformation_id':'transform:1'},
      'transformation_kind':kind,'transformation_id':'transform:1','alignment_method':'manual','review_state':'human-reviewed',
      'links':[
        {'sequence':1,'source_spans':[{'start':0,'end':7,'text':'Bonjour'}],'target_spans':[t1],'relation_type':'aligned','confidence':0.94,'review_state':'human-reviewed'},
        {'sequence':2,'source_spans':[{'start':8,'end':10,'text':'le'}],'target_spans':[],'relation_type':'omitted','review_state':'human-reviewed'},
        {'sequence':3,'source_spans':[{'start':11,'end':16,'text':'monde'}],'target_spans':[t3],'relation_type':'aligned','review_state':'human-reviewed'},
      ]
    }

def test_release_identity_routes_and_plugin():
    assert 'Version: 5.54.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert '__version__ = "2.65.0"' in read('library-backend/app/__init__.py')
    m=read('library-backend/app/main.py')
    for route in ['/v1/translation-alignment/readiness','/v1/translation-alignment/validate','/v1/translation-alignment/matrix','/v1/translation-alignment/package','/v1/admin/translation-alignments']:
        assert route in m
    assert (ROOT/'sustainable-catalyst-library/includes/class-sc-library-translation-alignment.php').exists()

def test_translation_matrix_is_deterministic_and_hash_bound():
    a=ta.build_alignment_matrix(sample()); b=ta.build_alignment_matrix(sample())
    assert a['matrix_id']==b['matrix_id'] and a['matrix_fingerprint_sha256']==b['matrix_fingerprint_sha256']
    assert len(a['source']['text_sha256'])==64 and len(a['target']['text_sha256'])==64

def test_offsets_are_checked_against_exact_text():
    p=sample(); p['links'][0]['source_spans'][0]['text']='Wrong'
    x=ta.validate_alignment_payload(p)
    assert x['valid'] is False and 'link-1-source-span-1-text-mismatch' in x['errors']

def test_translation_and_transliteration_are_derived_targets():
    assert ta.validate_alignment_payload(sample('translation'))['valid'] is True
    x=sample('transliteration'); x['target']['language_bcp47']='fr'; assert ta.validate_alignment_payload(x)['valid'] is True
    bad=sample(); bad['target']['representation_kind']='original'; assert ta.validate_alignment_payload(bad)['valid'] is False

def test_alignment_does_not_generate_language_transformations():
    p=sample(); p['generate_translation']=True
    x=ta.validate_alignment_payload(p)
    assert x['valid'] is False and 'automatic-translation-generation-prohibited' in x['errors']
    assert ta.guardrails()['alignment_generates_translation'] is False and ta.guardrails()['alignment_generates_transliteration'] is False

def test_alignment_confidence_is_not_truth_probability():
    x=ta.build_alignment_matrix(sample())
    assert x['links'][0]['confidence']==0.94
    assert x['guardrails']['alignment_confidence_is_truth_probability'] is False
    assert x['guardrails']['alignment_equivalence_implies_semantic_identity'] is False

def test_cardinality_and_unaligned_relations_are_preserved():
    p=sample(); p['links'].append({'sequence':4,'source_spans':[],'target_spans':[{'start':11,'end':12,'text':'.'}],'relation_type':'added'})
    x=ta.build_alignment_matrix(p)
    assert x['relation_counts']['omitted']==1 and x['relation_counts']['added']==1
    assert any(l['cardinality']=='0:1' for l in x['links'])

def test_invalid_offsets_and_duplicate_sequences_are_rejected():
    p=sample(); p['links'][0]['source_spans'][0]['end']=999; p['links'][1]['sequence']=1
    x=ta.validate_alignment_payload(p)
    assert x['valid'] is False and 'duplicate-link-sequence' in x['errors'] and any('out-of-range' in e for e in x['errors'])

def test_persistence_tables_bind_text_representation_and_transformation_lineage():
    s=read('library-backend/app/schema.sql')
    assert 'CREATE TABLE IF NOT EXISTS library_text_alignment_matrices' in s
    assert 'REFERENCES library_text_representations' in s and 'REFERENCES library_text_transformations' in s
    assert 'CREATE TABLE IF NOT EXISTS library_text_alignment_links' in s

def test_language_align_is_active_worker_capability_and_broker_visible():
    s=read('library-backend/app/specialized_worker_runtime.py')
    assert "'language.align'" in s and "cap=='language.align'" in s
    from app.distributed_compute_broker import capability_catalog
    rows={p['worker_class']:p for p in capability_catalog()['profiles']}
    assert 'language.align' in rows['python.research']['capabilities'] and rows['python.research']['profile_active'] is True

def test_wordpress_surface_is_read_only_and_backend_persistence_is_signed():
    wp=read('sustainable-catalyst-library/includes/class-sc-library-translation-alignment.php')
    assert 'sc_translation_alignment_status' in wp and '/v1/translation-alignment/readiness' in wp
    main=read('library-backend/app/main.py')
    assert 'authorize_write' in main[main.index('/v1/admin/translation-alignments'):main.index('/v1/execution-fabric/readiness')]

def test_package_preserves_multiple_matrices_without_canonicalizing_translation():
    a=sample(); b=sample('transliteration'); b['target']['language_bcp47']='fr'
    p=ta.build_alignment_package({'matrices':[a,b]})
    assert p['matrix_count']==2 and p['guardrails']['original_language_remains_canonical'] is True

def test_clean_repository_root_preserved():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
