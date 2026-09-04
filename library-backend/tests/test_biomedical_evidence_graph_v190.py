from copy import deepcopy

from app.biomedical_evidence_graph import BiomedicalEvidenceGraphEngine


def publication(pmid="12345", title="Heart failure randomized evidence", retrieved_at="2026-09-03T12:00:00+00:00"):
    return {
        "source_key": "pubmed",
        "record_type": "literature",
        "identifier": f"PMID:{pmid}",
        "title": title,
        "journal": "Example Journal",
        "publication_types": ["Randomized Controlled Trial"],
        "evidence_profile": {
            "study_design": {"family": "randomized-interventional"},
            "integrity": {"signals": [], "requires_review": False},
        },
        "source_url": f"https://pubmed.ncbi.nlm.nih.gov/{pmid}/",
        "provenance": {"source_key": "pubmed", "steward": "NLM", "retrieved_at": retrieved_at},
    }


def trial(retrieved_at="2026-09-03T12:00:01+00:00"):
    return {
        "source_key": "clinicaltrials-intelligence",
        "record_type": "clinical-trial",
        "nct_id": "NCT12345678",
        "identifier": "NCT12345678",
        "title": "Heart failure trial",
        "overall_status": "COMPLETED",
        "last_update_posted": "2026-08-30",
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
        "provenance": {"source_key": "clinicaltrials.gov", "retrieved_at": retrieved_at, "source_updated_at": "2026-08-30"},
    }


class Evidence:
    def __init__(self, pubs=None):
        self.pubs = pubs or [publication()]

    def search_body(self, query, *, literature_limit=8, trial_limit=8):
        return {
            "summary": {"design_distribution": {"randomized-interventional": 2}, "integrity_review_flag_count": 0},
            "literature": deepcopy(self.pubs[:literature_limit]),
            "trials": [deepcopy(trial())][:trial_limit],
            "errors": [],
        }

    def profile_trial_record(self, row):
        return {"study_design": {"family": "randomized-interventional"}, "certainty": {"formal_grade_generated": False}, "integrity": {"requires_review": False}}


class Trials:
    def get_study(self, nct_id):
        assert nct_id == "NCT12345678"
        return deepcopy(trial())


class Terminology:
    def __init__(self, fail=False, retrieved_at="2026-09-03T12:00:02+00:00"):
        self.fail = fail
        self.retrieved_at = retrieved_at

    def resolve(self, query, *, limit=3):
        if self.fail:
            raise RuntimeError("contained terminology failure")
        return {
            "groups": [{"source": {"key": "mesh"}, "results": [{
                "label": "Heart Failure", "identifier": "D006333", "uri": "https://id.nlm.nih.gov/mesh/D006333",
                "provenance": {"source_key": "mesh", "retrieved_at": self.retrieved_at, "vocabulary_year": "2026"},
            }]}],
            "errors": [],
            "crosswalk": {"state": "candidate-alignment", "semantic_equivalence_asserted": False},
        }


class FDA:
    def unified_search(self, query, *, limit=2, source_keys=None):
        return {
            "groups": [{"source": {"key": "drugsfda", "name": "Drugs@FDA", "evidence_class": "regulatory-approval"}, "results": [{
                "record_type": "fda-drug-application", "title": "Example Drug", "identifier": "NDA000001",
                "source_url": "https://example.test/fda", "provenance": {"source_key": "drugsfda", "retrieved_at": "2026-09-03T12:00:03+00:00"},
            }]}],
            "errors": [],
            "governance": {"research_only": True},
        }


def engine(*, pubs=None, terminology=None):
    return BiomedicalEvidenceGraphEngine(Evidence(pubs), Trials(), terminology or Terminology(), FDA())


def test_manifest_advertises_v11_reliability_contract():
    out = engine().manifest()
    assert out["schema"].endswith("/1.1")
    assert out["framework"]["version"] == "1.1"
    assert out["reliability_policy"]["title_only_merge"] is False
    assert out["reliability_policy"]["deterministic_ordering"] is True


def test_exact_pmid_observations_consolidate_without_title_merge():
    out = engine().build_graph("heart failure")
    pubs = [n for n in out["graph"]["nodes"] if n["type"] == "publication" and n.get("identifier") == "PMID:12345"]
    assert len(pubs) == 1
    assert pubs[0]["identity"]["merge_basis"] == "exact-identifier"
    assert pubs[0]["identity"]["observations"] == 2
    assert out["reliability"]["duplicate_observation_consolidation_count"] >= 1


def test_same_title_different_pmids_are_not_merged():
    same_title = "Same title"
    out = engine(pubs=[publication("11111", same_title), publication("22222", same_title)]).build_graph("heart failure")
    pubs = [n for n in out["graph"]["nodes"] if n["type"] == "publication"]
    ids = {n.get("identifier") for n in pubs}
    assert "PMID:11111" in ids and "PMID:22222" in ids
    assert all(n["identity"]["title_only_merge_used"] is False for n in pubs)


def test_every_edge_has_provenance_ledger_and_no_dangling_edges():
    out = engine().build_graph("heart failure")
    assert out["reliability"]["edge_missing_provenance_count"] == 0
    assert out["reliability"]["dangling_edge_count"] == 0
    assert all(edge["provenance_records"] for edge in out["graph"]["edges"])
    assert set(out["provenance_ledger"]["edges"]) == {e["id"] for e in out["graph"]["edges"]}


def test_graph_order_is_deterministic():
    out = engine().build_graph("heart failure")
    assert [n["id"] for n in out["graph"]["nodes"]] == sorted(n["id"] for n in out["graph"]["nodes"])
    assert [e["id"] for e in out["graph"]["edges"]] == sorted(e["id"] for e in out["graph"]["edges"])
    assert out["reliability"]["deterministic_ordering"] is True


def test_retrieval_timestamp_only_change_does_not_change_fingerprint():
    a = engine(terminology=Terminology(retrieved_at="2026-09-03T12:00:02+00:00")).build_graph("heart failure")
    b = engine(terminology=Terminology(retrieved_at="2026-09-03T13:00:02+00:00")).build_graph("heart failure")
    assert a["reproducibility"]["graph_content_fingerprint"] == b["reproducibility"]["graph_content_fingerprint"]
    assert a["reproducibility"]["volatile_retrieval_timestamps_excluded_from_fingerprints"] is True


def test_content_change_changes_fingerprint():
    a = engine(pubs=[publication("12345", "Original title")]).build_graph("heart failure")
    b = engine(pubs=[publication("12345", "Changed title")]).build_graph("heart failure")
    assert a["reproducibility"]["graph_content_fingerprint"] != b["reproducibility"]["graph_content_fingerprint"]


def test_partial_source_failure_is_contained_and_reported():
    out = engine(terminology=Terminology(fail=True)).build_graph("heart failure")
    assert out["source_status"]["medical-terminology"]["state"] == "error"
    assert out["reliability"]["partial_source_failure_count"] >= 1
    assert any(n["type"] == "publication" for n in out["graph"]["nodes"])
    assert any(n["type"] == "clinical-trial" for n in out["graph"]["nodes"])


def test_source_freshness_reports_without_inventing_staleness():
    out = engine().build_graph("heart failure")
    assert "clinicaltrials-intelligence" in out["source_freshness"] or "clinicaltrials.gov" in out["source_freshness"]
    assert all(row["staleness_inferred"] is False for row in out["source_freshness"].values())


def test_reproducibility_capsule_is_bounded_and_has_fingerprints():
    out = engine().reproducibility_capsule("heart failure")
    assert out["schema"] == "sc-biomedical-evidence-graph-reproducibility-capsule/1.0"
    assert len(out["reproducibility"]["graph_content_fingerprint"]) == 64
    assert "graph" not in out
    assert out["reliability"]["integrity_state"] == "pass"


def test_trial_neighborhood_has_reliability_fingerprint():
    out = engine().trial_neighborhood("NCT12345678")
    assert out["schema"].endswith("/1.1")
    assert len(out["reproducibility"]["graph_content_fingerprint"]) == 64
    assert out["reliability"]["edge_missing_provenance_count"] == 0
