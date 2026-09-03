from unittest.mock import patch

from app.fda_regulatory import build_fda_regulatory_registry


def fake_get(self, endpoint, params=None):
    base_meta={"results":{"total":1,"skip":0,"limit":1},"last_updated":"2026-09-01"}
    if endpoint.endswith('/drug/drugsfda.json'):
        return {"meta":base_meta,"results":[{"application_number":"NDA123456","sponsor_name":"Example Pharma","products":[{"product_number":"001","brand_name":"ExampleMed","active_ingredients":[{"name":"EXAMPLE","strength":"10MG"}],"dosage_form":"TABLET","route":"ORAL","marketing_status":"Prescription"}],"submissions":[{"submission_type":"ORIG","submission_number":"1","submission_status":"AP","submission_status_date":"20260101"}],"openfda":{"brand_name":["ExampleMed"],"generic_name":["example"],"rxcui":["123"]}}]}
    if endpoint.endswith('/drug/label.json'):
        return {"meta":base_meta,"results":[{"set_id":"abc-set","effective_time":"20260801","indications_and_usage":["For research example."],"boxed_warning":["Example boxed warning."],"openfda":{"brand_name":["ExampleMed"],"generic_name":["example"]}}]}
    if endpoint.endswith('/drug/ndc.json'):
        return {"meta":base_meta,"results":[{"product_ndc":"12345-678","brand_name":"ExampleMed","generic_name":"example","dosage_form":"TABLET","route":["ORAL"],"active_ingredients":[{"name":"EXAMPLE","strength":"10 mg"}]}]}
    if endpoint.endswith('/drug/event.json'):
        return {"meta":base_meta,"results":[{"safetyreportid":"FAERS-1","receivedate":"20260831","serious":"1","seriousnesshospitalization":"1","patient":{"reaction":[{"reactionmeddrapt":"NAUSEA"}],"drug":[{"medicinalproduct":"EXAMPLEMED","drugcharacterization":"1","openfda":{"brand_name":["ExampleMed"]}}]}}]}
    if endpoint.endswith('/drug/enforcement.json'):
        return {"meta":base_meta,"results":[{"recall_number":"D-123-2026","classification":"Class II","status":"Ongoing","recalling_firm":"Example Pharma","product_description":"ExampleMed tablets","reason_for_recall":"Example reason"}]}
    if endpoint.endswith('/drug/drugshortages.json'):
        return {"meta":base_meta,"results":[{"package_ndc":"12345-678-01","generic_name":"example","proprietary_name":"ExampleMed","company_name":"Example Pharma","availability":"Limited Supply","update_type":"Update"}]}
    if endpoint.endswith('/drug/orangebook.json'):
        return {"meta":base_meta,"results":[{"products":[{"application_number":"N123456","brand_name":"ExampleMed","ingredient":"EXAMPLE","therapeutic_equivalence_codes":["AB"]}],"patents":[{"patent_no":"9999999"}],"exclusivity":[{"exclusivity_code":"NCE"}]}]}
    raise AssertionError(endpoint)


def test_registry_has_governed_fda_sources():
    r=build_fda_regulatory_registry()
    keys=[x['key'] for x in r.list_sources()]
    assert keys == ['drugsfda','fda-labels','fda-ndc','fda-adverse-events','fda-recalls','fda-shortages','orange-book']
    assert all(x['governance']['research_only'] for x in r.list_sources())
    assert all(not x['governance']['clinical_decision_support'] for x in r.list_sources())
    assert all(not x['governance']['causality_inference_from_adverse_events'] for x in r.list_sources())


def test_drugsfda_label_and_ndc_normalization():
    r=build_fda_regulatory_registry()
    with patch('app.fda_regulatory._OpenFDAHTTP._get_json', new=fake_get):
        app=r.get('drugsfda').search('example')['results'][0]
        label=r.get('fda-labels').search('example')['results'][0]
        ndc=r.get('fda-ndc').search('example')['results'][0]
    assert app['identifier'] == 'NDA123456'
    assert app['regulatory_interpretation']['approval_record'] is True
    assert label['set_id'] == 'abc-set'
    assert label['sections']['boxed_warning'] == ['Example boxed warning.']
    assert ndc['identifier'] == 'NDC:12345-678'
    assert ndc['regulatory_interpretation']['approval_equivalence'] is False


def test_faers_report_never_claims_causality_or_incidence():
    r=build_fda_regulatory_registry()
    with patch('app.fda_regulatory._OpenFDAHTTP._get_json', new=fake_get):
        row=r.get('fda-adverse-events').search('example')['results'][0]
    assert row['reactions'] == ['NAUSEA']
    assert row['regulatory_interpretation']['causality_established'] is False
    assert row['regulatory_interpretation']['incidence_rate_available'] is False
    assert row['regulatory_interpretation']['signal_only'] is True
    assert 'does not establish' in row['regulatory_interpretation']['warning']


def test_recall_shortage_and_orange_book_normalization():
    r=build_fda_regulatory_registry()
    with patch('app.fda_regulatory._OpenFDAHTTP._get_json', new=fake_get):
        recall=r.get('fda-recalls').search('example')['results'][0]
        shortage=r.get('fda-shortages').search('example')['results'][0]
        orange=r.get('orange-book').search('example')['results'][0]
    assert recall['classification'] == 'Class II'
    assert shortage['availability'] == 'Limited Supply'
    assert orange['regulatory_interpretation']['therapeutic_equivalence_reference'] is True
    assert orange['regulatory_interpretation']['legal_status_action'] is False


def test_fda_unified_search_preserves_evidence_classes():
    r=build_fda_regulatory_registry()
    with patch('app.fda_regulatory._OpenFDAHTTP._get_json', new=fake_get):
        result=r.unified_search('example',limit=1)
    assert len(result['groups']) == 7
    classes=[g['source']['evidence_class'] for g in result['groups']]
    assert 'regulatory-approval' in classes
    assert 'safety-report' in classes
    assert 'supply-intelligence' in classes
    assert result['governance']['clinical_decision_support'] is False
    assert 'do not establish' in result['governance']['adverse_event_causality_warning']
