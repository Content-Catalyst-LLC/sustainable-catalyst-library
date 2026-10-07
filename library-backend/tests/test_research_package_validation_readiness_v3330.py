from app.research_package_validation_readiness import (
    contract, readiness, validate_package, completeness_audit, provenance_audit,
    review_audit, publication_readiness, publishing_handoff, export_bundle,
)


def package():
    return {
        "schema": "sc-library-research-package-composition/1.0",
        "composition_id": "research-package-composition:test",
        "composition_fingerprint_sha256": "a" * 64,
        "title": "Test package",
        "components": [{
            "component_id": "analysis:1", "component_type": "statistical-evidence",
            "state": "final", "required": True, "payload": {"value": 1},
            "provenance_state": "complete", "source_object_id": "result:1",
        }],
        "section_order": ["analysis:1"],
        "dependency_map": {"missing_dependency_count": 0, "missing_dependencies": []},
        "completeness_audit": {"finding_count": 0, "findings": [], "complete_against_declared_requirements": True},
        "provenance_audit": {"finding_count": 0, "findings": []},
    }


def publication():
    return {
        "schema": "sc-library-research-publication-draft/1.0",
        "publication_draft_id": "research-publication-draft:test",
        "title": "Test publication",
        "readiness_audit": {"ready_against_selected_profile": True, "blocker_count": 0, "blockers": []},
    }


def test_contract_and_readiness():
    assert contract()["next_release"] == "6.34.0"
    assert readiness()["ready"] is True
    assert readiness()["automatic_publication"] is False


def test_package_validation_passes_structural_package():
    result = validate_package({"package_composition": package()})
    assert result["valid"] is True
    assert result["blocker_count"] == 0


def test_completeness_and_provenance_pass():
    payload = {"package_composition": package()}
    assert completeness_audit(payload)["complete_for_selected_policy"] is True
    assert provenance_audit(payload)["complete_for_selected_policy"] is True


def test_review_approval_is_descriptive_not_truth():
    result = review_audit({"review_decisions": [{"schema": "sc-library-research-review-decision/1.0", "decision": "approve"}]})
    assert result["explicit_approval_present"] is True
    assert result["approval_implies_truth"] is False


def test_publication_gate_passes_complete_inputs():
    result = publication_readiness({"package_composition": package(), "publication_draft": publication()})
    assert result["ready_for_publication_handoff"] is True
    assert result["published"] is False
    assert result["automatic_publication"] is False


def test_gate_blocks_missing_provenance_and_publication():
    p = package()
    p["components"][0]["provenance_state"] = "missing"
    p["components"][0]["source_object_id"] = None
    result = publication_readiness({"package_composition": p})
    assert result["ready_for_publication_handoff"] is False
    assert result["blocker_count"] >= 2


def test_handoff_and_export_never_auto_publish():
    payload = {"package_composition": package(), "publication_draft": publication()}
    handoff = publishing_handoff(payload)
    assert handoff["ready_for_handoff"] is True
    assert handoff["automatic_submission"] is False
    assert handoff["automatic_external_publication"] is False
    exported = export_bundle(payload)
    assert exported["media_type"] == "application/json"
    assert exported["automatic_publication"] is False
