from app.biomedical_evidence_graph import BiomedicalEvidenceGraphEngine


PUB = {
    "source_key": "pubmed",
    "record_type": "literature",
    "identifier": "PMID:12345",
    "title": "Heart failure randomized evidence",
    "journal": "Example Journal",
    "publication_types": ["Randomized Controlled Trial"],
    "evidence_profile": {
        "study_design": {"family": "randomized-interventional"},
        "integrity": {"signals": [], "requires_review": False},
    },
    "source_url": "https://pubmed.ncbi.nlm.nih.gov/12345/",
}

TRIAL = {
    "source_key": "clinicaltrials-intelligence",
    "record_type": "clinical-trial",
    "nct_id": "NCT12345678",
    "title": "Heart failure trial",
    "overall_status": "COMPLETED",
    "conditions": ["Heart Failure"],
    "interventions": [{"type": "DRUG", "name": "Example Drug"}],
    "primary_outcomes": [{"measure": "Cardiovascular death", "timeFrame": "52 weeks"}],
    "study_design": {"study_type": "INTERVENTIONAL", "allocation": "RANDOMIZED", "phases": ["PHASE3"], "enrollment": 1000},
    "results_state": {"has_results": True},
    "publications": {
        "references": [{"pmid": "12345", "type": "RESULT", "citation": "Heart failure randomized evidence", "retractions": []}],
        "results_reference_count": 1,
        "retraction_signal_count": 0,
    },
    "source_url": "https://clinicaltrials.gov/study/NCT12345678",
    "evidence_profile": {"study_design": {"family": "randomized-interventional"}, "integrity": {"requires_review": False}},
}


class FakeEvidenceGrading:
    def search_body(self, query, *, literature_limit=8, trial_limit=8):
        assert query == "heart failure"
        return {
            "summary": {"design_distribution": {"randomized-interventional": 2}, "integrity_review_flag_count": 0},
            "literature": [PUB][:literature_limit],
            "trials": [TRIAL][:trial_limit],
            "errors": [],
        }

    def profile_trial_record(self, trial):
        return {"study_design": {"family": "randomized-interventional"}, "certainty": {"formal_grade_generated": False}, "integrity": {"requires_review": False}}


class FakeTrials:
    def get_study(self, nct_id):
        assert nct_id == "NCT12345678"
        return TRIAL


class FakeTerminology:
    def resolve(self, query, *, limit=3):
        assert query == "heart failure"
        return {
            "groups": [
                {"source": {"key": "mesh"}, "results": [{"label": "Heart Failure", "identifier": "D006333", "uri": "https://id.nlm.nih.gov/mesh/D006333"}]},
                {"source": {"key": "rxnorm"}, "results": [{"label": "Example Drug", "identifier": "RXCUI:42", "rxcui": "42"}]},
            ],
            "errors": [],
            "crosswalk": {"state": "candidate-alignment", "semantic_equivalence_asserted": False},
        }


class FakeFDA:
    def unified_search(self, query, *, limit=2, source_keys=None):
        assert query == "heart failure"
        assert "drugsfda" in source_keys
        return {
            "groups": [
                {
                    "source": {"key": "drugsfda", "name": "Drugs@FDA", "evidence_class": "regulatory-approval"},
                    "results": [{"record_type": "fda-drug-application", "title": "Example Drug", "identifier": "NDA000001", "source_url": "https://example.test/fda"}],
                }
            ],
            "errors": [],
            "governance": {"research_only": True},
        }


def engine():
    return BiomedicalEvidenceGraphEngine(FakeEvidenceGrading(), FakeTrials(), FakeTerminology(), FakeFDA())


def test_manifest_exposes_graph_types_and_strict_governance():
    out = engine().manifest()
    assert "publication" in out["node_types"]
    assert "registry-links-publication" in out["edge_types"]
    gov = out["framework"]["governance"]
    assert gov["semantic_equivalence_asserted"] is False
    assert gov["causal_relationship_inferred"] is False
    assert gov["pooled_effect_generated"] is False
    assert gov["clinical_recommendation_generated"] is False


def test_graph_merges_exact_trial_pmid_into_publication_node():
    out = engine().build_graph("heart failure")
    nodes = out["graph"]["nodes"]
    edges = out["graph"]["edges"]
    publications = [n for n in nodes if n["type"] == "publication" and n.get("identifier") == "PMID:12345"]
    assert len(publications) == 1
    links = [e for e in edges if e["type"] == "registry-links-publication"]
    assert len(links) == 1
    assert links[0]["attributes"]["exact_identifier_match"] is True


def test_trial_structure_creates_condition_intervention_and_outcome_edges():
    out = engine().build_graph("heart failure")
    edge_types = {e["type"] for e in out["graph"]["edges"]}
    assert "studies-condition" in edge_types
    assert "evaluates-intervention" in edge_types
    assert "measures-outcome" in edge_types


def test_terminology_candidates_never_become_equivalence_edges():
    out = engine().build_graph("heart failure")
    concept_edges = [e for e in out["graph"]["edges"] if e["type"] == "candidate-concept-for-question"]
    assert len(concept_edges) == 2
    assert all(e["attributes"]["semantic_equivalence_asserted"] is False for e in concept_edges)
    assert not any("equivalent" in e["type"] for e in out["graph"]["edges"])


def test_regulatory_records_preserve_separate_evidence_class():
    out = engine().build_graph("heart failure")
    regs = [n for n in out["graph"]["nodes"] if n["type"] == "regulatory-record"]
    assert len(regs) == 1
    assert regs[0]["attributes"]["evidence_class"] == "regulatory-approval"
    assert regs[0]["source_key"] == "drugsfda"


def test_synthesis_is_descriptive_and_not_meta_analysis():
    out = engine().build_graph("heart failure")
    syn = out["synthesis"]
    assert syn["state"] == "descriptive-graph-derived"
    assert syn["coverage"]["exact_trial_publication_link_count"] == 1
    assert syn["coverage"]["result_bearing_trial_count"] == 1
    boundaries = syn["boundaries"]
    assert boundaries["pooled_effect_generated"] is False
    assert boundaries["formal_grade_generated"] is False
    assert boundaries["comparative_effectiveness_conclusion_generated"] is False
    assert boundaries["clinical_recommendation_generated"] is False


def test_synthesis_endpoint_shape_omits_full_graph_but_keeps_counts():
    out = engine().synthesis("heart failure")
    assert out["schema"] == "sc-biomedical-evidence-synthesis/1.0"
    assert out["graph_summary"]["node_count"] > 0
    assert out["graph_summary"]["edge_count"] > 0
    assert "graph" not in out


def test_trial_neighborhood_uses_exact_registry_structure():
    out = engine().trial_neighborhood("NCT12345678")
    assert out["center"].startswith("trial:")
    assert any(e["type"] == "registry-links-publication" for e in out["graph"]["edges"])
    assert out["evidence_profile"]["certainty"]["formal_grade_generated"] is False
