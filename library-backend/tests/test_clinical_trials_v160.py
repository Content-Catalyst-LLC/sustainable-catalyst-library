from unittest.mock import patch

import pytest

from app.clinical_trials import ClinicalTrialIntelligence


SAMPLE = {
    "protocolSection": {
        "identificationModule": {"nctId":"NCT12345678","briefTitle":"Example Trial","officialTitle":"Example Official Trial"},
        "statusModule": {
            "overallStatus":"COMPLETED",
            "startDateStruct":{"date":"2022-01-01"},
            "completionDateStruct":{"date":"2024-01-01"},
            "resultsFirstPostDate":{"date":"2025-01-01"},
        },
        "descriptionModule": {"briefSummary":"Summary"},
        "conditionsModule": {"conditions":["Heart Failure"],"keywords":["cardiology"]},
        "designModule": {
            "studyType":"INTERVENTIONAL","phases":["PHASE3"],"enrollmentInfo":{"count":1200,"type":"ACTUAL"},
            "designInfo":{"allocation":"RANDOMIZED","interventionModel":"PARALLEL","primaryPurpose":"TREATMENT","maskingInfo":{"masking":"DOUBLE","whoMasked":["PARTICIPANT","INVESTIGATOR"]}},
        },
        "armsInterventionsModule": {
            "armGroups":[{"label":"Drug","type":"EXPERIMENTAL","interventionNames":["DRUG: Example"]}],
            "interventions":[{"type":"DRUG","name":"Example","armGroupLabels":["Drug"]}],
        },
        "outcomesModule": {"primaryOutcomes":[{"measure":"Primary endpoint","timeFrame":"52 weeks"}],"secondaryOutcomes":[]},
        "eligibilityModule": {"sex":"ALL","minimumAge":"18 Years","maximumAge":"80 Years","healthyVolunteers":False,"eligibilityCriteria":"Adults"},
        "sponsorCollaboratorsModule": {"leadSponsor":{"name":"Example Sponsor","class":"INDUSTRY"}},
        "contactsLocationsModule": {"locations":[{"facility":"Example Center","city":"Chicago","state":"Illinois","country":"United States"}]},
        "referencesModule": {"references":[{"pmid":"12345","type":"RESULT","citation":"Example citation","retractions":[{"pmid":"999","source":"Retraction notice"}]}]},
    },
    "derivedSection": {"conditionBrowseModule":{"meshes":[{"id":"D0001","term":"Heart Failure"}]},"interventionBrowseModule":{"meshes":[{"id":"D0002","term":"Example"}]}},
    "resultsSection": {
        "participantFlowModule":{"groups":[{"id":"FG000","title":"Drug"}],"periods":[{}]},
        "baselineCharacteristicsModule":{"groups":[{}],"measures":[{}]},
        "outcomeMeasuresModule":{"outcomeMeasures":[{"type":"PRIMARY","title":"Primary endpoint","timeFrame":"52 weeks","units":"events","analyses":[{"paramType":"HAZARD_RATIO","paramValue":"0.80","pValue":"0.01","statisticalMethod":"Cox regression"}]}]},
        "adverseEventsModule":{"eventGroups":[{"id":"EG000","title":"Drug"}],"seriousEvents":[{}],"otherEvents":[{},{}]},
    },
    "hasResults": True,
}


def fake_get(self, path, params=None):
    if path == "/studies":
        assert params["query.cond"] == "heart failure"
        assert params["query.intr"] == "example"
        assert params["filter.overallStatus"] == "COMPLETED"
        assert params["filter.advanced"] == "AREA[Phase]PHASE3 AND AREA[StudyType]INTERVENTIONAL"
        return {"studies":[SAMPLE],"totalCount":1,"nextPageToken":"NEXT"}
    if path == "/studies/NCT12345678":
        return SAMPLE
    if path == "/studies/NCT87654321":
        other = {**SAMPLE, "protocolSection": {**SAMPLE["protocolSection"], "identificationModule":{"nctId":"NCT87654321","briefTitle":"Comparator"}, "conditionsModule":{"conditions":["Heart Failure","Diabetes"]}}}
        return other
    raise AssertionError(path)


def test_manifest_preserves_governance_boundaries():
    m=ClinicalTrialIntelligence().manifest()
    assert m["source"]["key"] == "clinicaltrials-intelligence"
    assert m["source"]["governance"]["participant_level_data_exposed"] is False
    assert m["governance"]["absence_of_linked_publication_does_not_prove_unpublished"] is True


def test_structured_search_builds_supported_filters_and_normalizes_trial():
    c=ClinicalTrialIntelligence()
    with patch.object(ClinicalTrialIntelligence,"_get_json",new=fake_get):
        out=c.search(condition="heart failure",intervention="example",status="completed",phase="PHASE3",study_type="interventional")
    row=out["results"][0]
    assert out["total"] == 1 and out["next_cursor"] == "NEXT"
    assert row["nct_id"] == "NCT12345678"
    assert row["study_design"]["allocation"] == "RANDOMIZED"
    assert row["eligibility"]["minimum_age"] == "18 Years"
    assert row["publications"]["results_reference_count"] == 1
    assert row["publications"]["retraction_signal_count"] == 1
    assert row["results_state"]["has_results"] is True
    assert row["handoffs"]["lab"]["eligible"] is True


def test_detail_includes_aggregate_results_not_participant_level_data():
    c=ClinicalTrialIntelligence()
    with patch.object(ClinicalTrialIntelligence,"_get_json",new=fake_get):
        row=c.get_study("NCT12345678")
    assert row["results_summary"]["outcome_measures"][0]["analyses"][0]["param_value"] == "0.80"
    assert row["results_summary"]["participant_level_data_exposed"] is False
    assert row["results_summary"]["adverse_events"]["serious_event_term_count"] == 1


def test_comparison_is_descriptive_and_preserves_shared_condition():
    c=ClinicalTrialIntelligence()
    with patch.object(ClinicalTrialIntelligence,"_get_json",new=fake_get):
        out=c.compare(["NCT12345678","NCT87654321"])
    assert out["common"]["conditions"] == ["heart failure"]
    assert len(out["matrix"]) == 2
    assert out["governance"]["comparative_effectiveness_conclusion_generated"] is False


def test_invalid_nct_and_phase_are_bounded():
    c=ClinicalTrialIntelligence()
    with pytest.raises(ValueError): c.get_study("123")
    with pytest.raises(ValueError): c.search(query="x",phase="PHASE9")
