from app.research_package_composer_workspace import (
    completeness_audit, compose_package, dependency_map, export_package_draft,
    guardrails, provenance_audit, publishing_handoff_preview,
    reproducibility_handoff_preview,
)

SAMPLE = {
    "title": "Enhanced weathering research package",
    "research_question": "Under what conditions does enhanced weathering deliver durable net carbon removal?",
    "description": "Cross-workspace research package draft.",
    "project_id": "project:ew",
    "components": [
        {
            "component_id":"investigation",
            "component_type":"investigation",
            "title":"Research investigation",
            "state":"reviewed",
            "required":True,
            "source_route":"/research/investigation",
            "source_contract":"sc-library-structured-investigation-plan/1.0",
            "source_version":"6.21.0",
            "provenance_state":"complete",
            "provenance":{"source":"library"},
            "record_ids":["record:1"],
            "payload":{"research_question":"..."}
        },
        {
            "component_id":"evidence",
            "component_type":"evidence-matrix",
            "title":"Claim evidence",
            "state":"reviewed",
            "required":True,
            "source_route":"/research/evidence",
            "source_contract":"sc-library-claim-evidence-matrix/1.0",
            "source_version":"6.22.0",
            "provenance_state":"complete",
            "provenance":{"source":"library"},
            "dependencies":["investigation"],
            "payload":{"claim_count":3}
        },
        {
            "component_id":"data",
            "component_type":"statistical-evidence",
            "title":"Quantitative evidence",
            "state":"draft",
            "source_route":"/research/data",
            "source_contract":"sc-library-statistical-evidence-table/1.0",
            "source_version":"6.23.0",
            "provenance_state":"partial",
            "dependencies":["evidence"],
            "payload":{"result_count":2}
        }
    ],
    "section_order":["investigation","evidence","data"],
    "requirements":[
        {"component_type":"investigation","minimum":1},
        {"component_type":"evidence-matrix","minimum":1}
    ],
    "references":{
        "record_ids":["record:root"],
        "artifact_ids":["artifact:sha256:abc"],
        "pipeline_run_ids":["run:1"],
        "reproducibility_records":[]
    },
    "records":[{"record_id":"record:1","title":"Source record"}],
}

def test_guardrails():
    g=guardrails()
    assert g["existing_research_package_service_remains_authority"] is True
    assert g["draft_package_is_published_research"] is False
    assert g["package_completeness_implies_truth"] is False
    assert g["automatic_artifact_persistence"] is False

def test_compose_preserves_components():
    out=compose_package(SAMPLE)
    assert out["component_counts"]["investigation"]==1
    assert out["component_counts"]["evidence-matrix"]==1
    assert out["package_state"]=="draft-composition"
    assert out["published"] is False
    assert out["persisted"] is False

def test_dependency_map_explicit():
    out=dependency_map(SAMPLE)
    assert out["dependency_count"]==2
    assert out["missing_dependency_count"]==0
    assert out["automatic_dependency_inference"] is False

def test_completeness_is_declared_requirements_only():
    out=completeness_audit(SAMPLE)
    assert out["complete_against_declared_requirements"] is True
    assert out["completeness_implies_truth"] is False

def test_provenance_audit_preserves_partial_state():
    out=provenance_audit(SAMPLE)
    data=[x for x in out["rows"] if x["component_id"]=="data"][0]
    assert data["provenance_state"]=="partial"
    assert out["provenance_completeness_implies_source_validity"] is False

def test_publishing_handoff_preview_only():
    out=publishing_handoff_preview(SAMPLE)
    assert out["endpoint"]=="/api/library/v1/research-package-publishing/preview"
    assert out["preview_only"] is True
    assert out["automatic_submission"] is False
    assert out["automatic_persistence"] is False

def test_reproducibility_handoff_is_signed_and_preview_only():
    out=reproducibility_handoff_preview(SAMPLE)
    assert out["endpoint"]=="/api/library/v1/admin/reproducibility/packages"
    assert out["signed_request_required"] is True
    assert out["automatic_submission"] is False
    assert "record:1" in out["payload"]["record_ids"]

def test_export_nonpersistent():
    out=export_package_draft(SAMPLE)
    assert out["automatic_publication"] is False
    assert out["workspace_persisted"] is False
    assert out["content"]
