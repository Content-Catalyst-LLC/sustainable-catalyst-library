from app.research_publication_studio import (
    asset_inventory,
    build_publication_draft,
    citation_inventory,
    editorial_audit,
    guardrails,
    publishing_handoff_preview,
    readiness_audit,
    section_inventory,
    export_publication_draft,
)

PACKAGE = {
    "schema": "sc-library-research-package-composition/1.0",
    "composition_id": "research-package-composition:test",
    "composition_fingerprint_sha256": "a" * 64,
    "title": "Enhanced weathering research package",
    "project_id": "project:ew",
    "package_state": "draft-composition",
    "components": [
        {"component_id": "investigation", "component_type": "investigation"},
        {"component_id": "evidence", "component_type": "evidence-matrix"},
    ],
    "completeness_audit": {"finding_count": 0},
    "provenance_audit": {"finding_count": 1},
}

SAMPLE = {
    "title": "Enhanced weathering: evidence, constraints and research needs",
    "publication_profile": "research-report",
    "project_id": "project:ew",
    "research_question": "Under what conditions does enhanced weathering deliver durable net carbon removal?",
    "abstract": "A structured publication draft.",
    "keywords": ["enhanced weathering", "carbon removal"],
    "package_composition": PACKAGE,
    "contributors": [{"contributor_id": "author:1", "name": "Researcher", "roles": ["writing"]}],
    "sections": [
        {"section_id": "abstract", "kind": "abstract", "content": "Abstract text.", "source_component_ids": ["investigation"]},
        {"section_id": "intro", "kind": "introduction", "content": "Introduction text.", "source_component_ids": ["investigation"]},
        {"section_id": "methods", "kind": "methods", "content": "Methods text.", "source_component_ids": ["investigation"]},
        {"section_id": "findings", "kind": "findings", "content": "Findings text.", "source_component_ids": ["evidence"], "citation_ids": ["cite:1"]},
        {"section_id": "limits", "kind": "limitations", "content": "Limitations text."},
        {"section_id": "refs", "kind": "references", "content": "References."},
    ],
    "figures": [{"figure_id": "fig:1", "title": "Evidence flow", "caption": "Evidence flow.", "source_component_ids": ["evidence"]}],
    "tables": [{"table_id": "tab:1", "title": "Evidence matrix", "source_component_ids": ["evidence"]}],
    "citations": [{"citation_id": "cite:1", "title": "Example source", "doi": "10.0000/example"}],
    "appendices": [{"appendix_id": "app:1", "title": "Methods appendix", "content": "Appendix text."}],
}


def test_guardrails_preserve_existing_authorities():
    g = guardrails()
    assert g["research_package_composer_remains_composition_authority"] is True
    assert g["research_package_publishing_remains_export_authority"] is True
    assert g["automatic_external_publication"] is False
    assert g["publication_readiness_implies_truth"] is False


def test_section_inventory():
    out = section_inventory(SAMPLE)
    assert out["section_count"] == 6
    assert out["counts_by_kind"]["methods"] == 1
    assert out["total_word_count"] > 0


def test_asset_inventory():
    out = asset_inventory(SAMPLE)
    assert out["counts"] == {"figures": 1, "tables": 1, "appendices": 1}
    assert out["automatic_artifact_persistence"] is False


def test_citation_inventory():
    out = citation_inventory(SAMPLE)
    assert out["citation_count"] == 1
    assert out["identifier_or_record_reference_count"] == 1
    assert out["citation_count_implies_quality"] is False


def test_editorial_audit_preserves_non_truth_boundary():
    out = editorial_audit(SAMPLE)
    assert out["editorial_findings_imply_research_invalidity"] is False
    assert not [x for x in out["findings"] if x["kind"] == "section-source-component-not-in-package"]


def test_readiness_profile():
    out = readiness_audit(SAMPLE)
    assert out["ready_against_selected_profile"] is True
    assert out["missing_required_sections"] == []
    assert out["package_observations"]["composer_provenance_finding_count"] == 1
    assert out["readiness_implies_peer_review"] is False


def test_build_draft_references_package_without_persistence():
    out = build_publication_draft(SAMPLE)
    assert out["source_package"]["composition_id"] == PACKAGE["composition_id"]
    assert out["state"] == "draft"
    assert out["published"] is False
    assert out["persisted"] is False


def test_publishing_handoff_preview_only():
    out = publishing_handoff_preview(SAMPLE)
    assert out["endpoint"] == "/api/library/v1/research-package-publishing/preview"
    assert out["preview_only"] is True
    assert out["automatic_submission"] is False
    assert out["automatic_external_publication"] is False


def test_export_is_deterministic_and_nonpublishing():
    a = export_publication_draft(SAMPLE)
    b = export_publication_draft(SAMPLE)
    assert a["export_fingerprint_sha256"] == b["export_fingerprint_sha256"]
    assert a["automatic_publication"] is False
    assert a["workspace_persisted"] is False
    assert a["content"]
