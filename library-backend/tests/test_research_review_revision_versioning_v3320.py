from app.research_review_revision_versioning import (
    bootstrap,
    compare_versions,
    export_bundle,
    review_decision,
    review_packet,
    revision_proposal,
    validate_chain,
    version_history,
    version_snapshot,
)


def subject(value=1):
    return {"object_id": "analysis:1", "type": "statistical-result", "authority": "statistical-evidence", "value": value}


def snap(label="v1", value=1, parent=None):
    return version_snapshot({
        "subject": subject(value),
        "version_label": label,
        "parent_version_id": parent,
        "supersedes_version_id": parent,
    })


def test_bootstrap_contract_and_guardrails():
    b = bootstrap()
    assert b["library_version"] == "6.32.0"
    assert b["backend_version"] == "3.32.0"
    assert b["readiness"]["ready"] is True
    assert b["readiness"]["server_side_review_persistence"] is False
    assert b["operations"] == ["snapshot", "compare", "review-packet", "revision-proposal", "review-decision", "version-history", "validate-chain", "export"]


def test_snapshot_and_comparison_are_deterministic():
    a = snap("v1", 1)
    b = snap("v2", 2, a["snapshot_id"])
    c = compare_versions({"before_snapshot": a, "after_snapshot": b})
    assert a == snap("v1", 1)
    assert c["changed"] is True
    assert c["change_count"] == 1
    assert c["changes"][0]["path"] == "$.value"
    assert c["change_count_implies_materiality"] is False
    assert b["lineage_relations"][0]["explicit"] is True


def test_review_is_human_asserted_and_not_truth_promotion():
    a = snap()
    r = review_packet({"snapshot": a, "reviewer": {"name": "Reviewer"}, "criteria": ["provenance"], "findings": [{"text": "Clarify source", "severity": "major"}]})
    d = review_decision({"review": r, "decision": "request-changes", "decided_by": {"name": "Reviewer"}, "rationale": "Needs clarification"})
    assert r["decision_automatic"] is False
    assert d["human_asserted"] is True
    assert d["automatic"] is False
    assert d["applied_to_subject"] is False
    assert d["resulting_review_state"] == "changes-requested"
    assert d["guardrails"]["review_approval_implies_truth"] is False


def test_revision_proposal_does_not_auto_apply():
    a = snap()
    p = revision_proposal({"base_snapshot": a, "candidate_subject": subject(3), "candidate_version_label": "v2", "rationale": "Update result"})
    assert p["automatic_application"] is False
    assert p["candidate_snapshot"]["parent_version_id"] == a["snapshot_id"]
    assert p["candidate_snapshot"]["supersedes_version_id"] == a["snapshot_id"]
    assert p["comparison"]["changed"] is True


def test_history_and_chain_validation():
    a = snap("v1", 1)
    b = snap("v2", 2, a["snapshot_id"])
    h = version_history({"snapshots": [a, b]})
    v = validate_chain({"snapshots": [a, b]})
    assert h["snapshot_count"] == 2
    assert all(row["content_fingerprint_valid"] for row in h["rows"])
    assert v["valid"] is True
    assert v["cycles"] == []


def test_invalid_fingerprint_and_export_guardrails():
    a = snap()
    bad = dict(a)
    bad["subject"] = dict(a["subject"], value=999)
    v = validate_chain({"snapshots": [bad]})
    assert v["valid"] is False
    assert bad["snapshot_id"] in v["invalid_content_fingerprints"]
    e = export_bundle({"snapshots": [a], "reviews": [], "revisions": [], "decisions": []})
    assert e["persisted"] is False
    assert e["media_type"] == "application/json"
    assert "sc-library-research-review-revision-versioning-export/1.0" in e["content"]
