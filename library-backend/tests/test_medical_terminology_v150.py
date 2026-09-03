from unittest.mock import patch

from app.biomedical_sources import build_biomedical_registry
from app.medical_terminology import MedicalTerminologyResolver, WHOICD11Connector


def fake_icd_get(self, path, params):
    assert '/2026-01/mms/search' in path
    assert params['medicalCodingMode'] == 'false'
    return {
        'destinationEntities': [
            {
                'id': 'http://id.who.int/icd/release/11/mms/257068234',
                'title': '<em class="found">Cholera</em>',
                'theCode': '1A00',
                'foundationUri': 'http://id.who.int/icd/entity/257068234',
                'score': 0.99,
            }
        ]
    }


def fake_biomedical_get(self, url, params=None):
    if url.endswith('/lookup/descriptor'):
        return [{'resource':'http://id.nlm.nih.gov/mesh/D002771','label':'Cholera','preferred':True}]
    if url.endswith('/approximateTerm.json'):
        return {'approximateGroup':{'candidate':[{'rxcui':'123','name':'Example drug','score':'98','rank':'1','source':'RXNORM'}]}}
    raise AssertionError(url)


def test_icd_descriptor_uses_2026_mms_and_governance():
    c=WHOICD11Connector(client_id='id',client_secret='secret',release_id='2026-01')
    d=c.descriptor()
    assert d['release_id']=='2026-01'
    assert d['name']=='WHO ICD-11 MMS'
    assert d['governance']['clinical_decision_support'] is False


def test_icd_search_normalizes_code_and_foundation_uri():
    c=WHOICD11Connector(client_id='id',client_secret='secret',release_id='2026-01')
    with patch.object(WHOICD11Connector,'_get_json',new=fake_icd_get):
        row=c.search('cholera',limit=5)['results'][0]
    assert row['label']=='Cholera'
    assert row['code']=='1A00'
    assert row['identifier']=='ICD11:1A00'
    assert row['foundation_uri'].endswith('/257068234')
    assert row['governance']['diagnosis'] is False


def test_local_icd_mode_does_not_require_cloud_credentials():
    c=WHOICD11Connector(base_url='http://sc-icd11',local_mode=True)
    assert c.configured() is True
    assert c._access_token()==''


def test_resolver_preserves_parallel_candidate_alignment():
    bio=build_biomedical_registry()
    icd=WHOICD11Connector(client_id='id',client_secret='secret')
    resolver=MedicalTerminologyResolver(icd,bio)
    with patch.object(WHOICD11Connector,'_get_json',new=fake_icd_get), patch('app.biomedical_sources._JsonHTTP._get_json',new=fake_biomedical_get):
        out=resolver.resolve('cholera',limit=3)
    assert len(out['groups'])==3
    assert out['crosswalk']['state']=='candidate-alignment'
    assert out['crosswalk']['semantic_equivalence_asserted'] is False
    assert out['crosswalk']['human_review_required'] is True
    assert out['governance']['patient_specific_diagnosis'] is False
