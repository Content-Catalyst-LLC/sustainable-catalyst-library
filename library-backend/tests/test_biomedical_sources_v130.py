from unittest.mock import patch
from app.biomedical_sources import build_biomedical_registry


def fake_get(self, url, params=None):
    if "esearch.fcgi" in url:
        return {"esearchresult":{"count":"1","idlist":["123"]}}
    if "esummary.fcgi" in url:
        return {"result":{"123":{"uid":"123","title":"Randomized Trial of Example Therapy","authors":[{"name":"Ada Author"}],"pubdate":"2026","fulljournalname":"Example Journal","pubtype":["Randomized Controlled Trial"],"articleids":[{"idtype":"doi","value":"10.1/example"}]}}}
    if "clinicaltrials" in url:
        return {"studies":[{"protocolSection":{"identificationModule":{"nctId":"NCT00000001","briefTitle":"Example Trial"},"statusModule":{"overallStatus":"RECRUITING"},"designModule":{"studyType":"INTERVENTIONAL","phases":["PHASE3"],"enrollmentInfo":{"count":100}},"conditionsModule":{"conditions":["Example Disease"]},"armsInterventionsModule":{"interventions":[{"type":"DRUG","name":"Example Drug"}]},"sponsorCollaboratorsModule":{"leadSponsor":{"name":"Example Sponsor"}},"outcomesModule":{"primaryOutcomes":[{"measure":"Outcome"}]}}}],"nextPageToken":"NEXT"}
    if "id.nlm.nih.gov" in url:
        return [{"resource":"http://id.nlm.nih.gov/mesh/D000001","label":"Calcimycin"}]
    if "rxnav" in url:
        return {"approximateGroup":{"candidate":[{"rxcui":"12345","name":"Example Drug","score":"100","rank":"1","source":"RXNORM"}]}}
    raise AssertionError(url)


def test_registry_has_governed_first_wave_sources():
    r=build_biomedical_registry()
    keys=[x["key"] for x in r.list_sources()]
    assert keys == ["pubmed","pmc","clinicaltrials","mesh","rxnorm"]
    assert all(x["governance"]["research_only"] for x in r.list_sources())
    assert all(not x["governance"]["clinical_decision_support"] for x in r.list_sources())


def test_pubmed_evidence_normalization_and_handoffs():
    r=build_biomedical_registry()
    with patch("app.biomedical_sources._JsonHTTP._get_json", new=fake_get):
        result=r.get("pubmed").search("example")
    row=result["results"][0]
    assert row["identifier"] == "PMID:123"
    assert row["evidence"]["design"] == "randomized-controlled-trial"
    assert row["handoffs"]["research_librarian"]["eligible"] is True
    assert row["handoffs"]["lab"]["eligible"] is False


def test_clinical_trials_normalization():
    r=build_biomedical_registry()
    with patch("app.biomedical_sources._JsonHTTP._get_json", new=fake_get):
        result=r.get("clinicaltrials").search("example")
    row=result["results"][0]
    assert row["identifier"] == "NCT00000001"
    assert row["overall_status"] == "RECRUITING"
    assert row["phases"] == ["PHASE3"]
    assert result["next_cursor"] == "NEXT"


def test_mesh_and_rxnorm_concept_resolution():
    r=build_biomedical_registry()
    with patch("app.biomedical_sources._JsonHTTP._get_json", new=fake_get):
        mesh=r.get("mesh").search("calcimycin")["results"][0]
        rx=r.get("rxnorm").search("example drug")["results"][0]
    assert mesh["identifier"] == "D000001"
    assert rx["identifier"] == "RXCUI:12345"


def test_unified_search_fail_contains_individual_sources():
    r=build_biomedical_registry()
    with patch("app.biomedical_sources._JsonHTTP._get_json", new=fake_get):
        result=r.unified_search("example",limit=2)
    assert len(result["groups"]) == 5
    assert result["errors"] == []
    assert result["governance"]["clinical_decision_support"] is False
