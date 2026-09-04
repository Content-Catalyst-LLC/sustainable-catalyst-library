from app.evidence_grading import EvidenceGradingEngine


class FakePubMed:
    def search(self, query, *, limit=8):
        assert query == "heart failure"
        return {
            "results": [
                {
                    "source_key": "pubmed",
                    "record_type": "literature",
                    "identifier": "PMID:1",
                    "title": "Randomized example",
                    "publication_types": ["Randomized Controlled Trial", "Multicenter Study"],
                    "journal": "Example Journal",
                },
                {
                    "source_key": "pubmed",
                    "record_type": "literature",
                    "identifier": "PMID:2",
                    "title": "Review example",
                    "publication_types": ["Systematic Review", "Meta-Analysis"],
                },
            ][:limit]
        }


class FakeRegistry:
    def get(self, key):
        assert key == "pubmed"
        return FakePubMed()


TRIAL = {
    "source_key": "clinicaltrials-intelligence",
    "record_type": "clinical-trial",
    "nct_id": "NCT12345678",
    "title": "Randomized registered trial",
    "overall_status": "COMPLETED",
    "study_design": {
        "study_type": "INTERVENTIONAL",
        "allocation": "RANDOMIZED",
        "masking": "DOUBLE",
        "phases": ["PHASE3"],
        "enrollment": 1200,
    },
    "arm_groups": [{"label": "A"}, {"label": "B"}],
    "publications": {
        "results_reference_count": 1,
        "retraction_signal_count": 1,
        "publication_link_state": "linked-results-publication",
    },
    "results_state": {"has_results": True, "registry_results_are_not_peer_review": True},
    "results_summary": {
        "outcome_measures": [
            {"analyses": [{"ci_lower_limit": "0.70", "ci_upper_limit": "0.92", "p_value": "0.01"}]}
        ]
    },
}


class FakeTrials:
    def search(self, *, query="", limit=8, **kwargs):
        assert query == "heart failure"
        return {"results": [TRIAL][:limit]}

    def get_study(self, nct_id):
        assert nct_id == "NCT12345678"
        return TRIAL


def engine():
    return EvidenceGradingEngine(FakeRegistry(), FakeTrials())


def test_manifest_never_claims_automated_formal_grade():
    out = engine().manifest()
    gov = out["framework"]["governance"]
    assert gov["formal_grade_generated"] is False
    assert gov["formal_risk_of_bias_judgment_generated"] is False
    assert "risk_of_bias" in out["certainty_domains"]
    assert "publication_bias" in out["certainty_domains"]


def test_publication_design_classification_preserves_integrity_signal():
    out = engine().classify_publication_types(["Randomized Controlled Trial", "Retracted Publication"])
    assert out["family"] == "randomized-interventional"
    assert "retraction-related-publication-type" in out["integrity_signals"]
    assert out["human_review_required"] is True


def test_literature_profile_separates_design_from_certainty():
    row = {
        "source_key": "pubmed", "record_type": "literature", "identifier": "PMID:1",
        "publication_types": ["Systematic Review", "Meta-Analysis"],
    }
    profile = engine().profile_literature_record(row)
    assert profile["study_design"]["family"] == "evidence-synthesis"
    assert profile["certainty"]["formal_grade"] is None
    assert profile["certainty"]["formal_grade_generated"] is False


def test_trial_profile_uses_registry_design_as_signal_not_verified_conduct():
    profile = engine().profile_trial_record(TRIAL)
    design = profile["study_design"]
    assert design["family"] == "randomized-interventional"
    assert design["conduct_verified"] is False
    assert "randomization-reported-in-registry" in profile["certainty"]["domains"]["risk_of_bias"]["signals"]
    assert "confidence-interval-reported" in profile["certainty"]["domains"]["imprecision"]["signals"]
    assert profile["integrity"]["requires_review"] is True


def test_evidence_body_maps_design_distribution_without_formal_certainty():
    out = engine().search_body("heart failure", literature_limit=5, trial_limit=5)
    summary = out["summary"]
    assert summary["record_count"] == 3
    assert summary["design_distribution"]["randomized-interventional"] == 2
    assert summary["design_distribution"]["evidence-synthesis"] == 1
    assert summary["formal_certainty_assessment_generated"] is False
    assert len(summary["review_requirements"]) == 5


def test_trial_profile_endpoint_payload_contains_source_trial_and_profile():
    out = engine().trial_profile("NCT12345678")
    assert out["trial"]["nct_id"] == "NCT12345678"
    assert out["evidence_profile"]["certainty"]["assessment_state"] == "not-formally-assessed"
