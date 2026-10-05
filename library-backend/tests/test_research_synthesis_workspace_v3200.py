from app.research_synthesis_workspace import (
    build_synthesis, contradiction_ledger, convergence_summary, evidence_matrix,
    export_synthesis, gap_analysis, guardrails, publishing_handoff_preview, source_attribution,
)

SAMPLE = {
    "title": "Accelerated weathering synthesis",
    "research_question": "What does the assembled evidence say about accelerated weathering?",
    "sources": [
        {"source_id":"s1","kind":"publication","title":"Study A","record_id":"record:a"},
        {"source_id":"s2","kind":"publication","title":"Study B","citation_id":"citation:b"},
        {"source_id":"s3","kind":"annotation","title":"Research note","annotation_id":"annotation:c"},
    ],
    "claims": [
        {"claim_id":"c1","text":"The intervention increases measured weathering rate."},
        {"claim_id":"c2","text":"The effect size is stable across contexts."},
        {"claim_id":"c3","text":"Long-term ecosystem effects are resolved."},
    ],
    "relationships": [
        {"claim_id":"c1","source_id":"s1","stance":"supports","rationale":"Measured increase."},
        {"claim_id":"c1","source_id":"s2","stance":"contradicts","rationale":"No increase in second setting."},
        {"claim_id":"c2","source_id":"s1","stance":"qualifies","rationale":"Context-specific conditions."},
        {"claim_id":"c2","source_id":"s3","stance":"contextualizes","rationale":"Method note."},
    ],
    "unresolved_questions": ["What are the long-term ecosystem effects?"],
}

def test_guardrails():
    g=guardrails()
    assert g["synthesis_is_composition_layer"] is True
    assert g["synthesis_is_new_truth_store"] is False
    assert g["support_count_is_truth_probability"] is False
    assert g["mixed_evidence_is_collapsed_to_single_answer"] is False

def test_matrix_and_contradiction():
    m=evidence_matrix(SAMPLE)
    row=[x for x in m["rows"] if x["claim_id"]=="c1"][0]
    assert row["stance_counts"]["supports"]==1
    assert row["stance_counts"]["contradicts"]==1
    ledger=contradiction_ledger(SAMPLE)
    assert ledger["count"]==1
    assert ledger["items"][0]["automatic_resolution"] is False

def test_convergence_is_descriptive_not_truth():
    out=convergence_summary(SAMPLE)
    c1=[x for x in out["items"] if x["claim_id"]=="c1"][0]
    assert c1["pattern"]=="mixed"
    assert c1["truth_status"] is None
    assert c1["winner_selected"] is False

def test_attribution_and_gaps():
    att=source_attribution(SAMPLE)
    assert att["source_count"]==3
    gaps=gap_analysis(SAMPLE)
    kinds={x["kind"] for x in gaps["gaps"]}
    assert "unlinked-claim" in kinds
    assert "unresolved-question" in kinds

def test_synthesis_preserves_open_state():
    out=build_synthesis(SAMPLE)
    assert out["final_answer_generated"] is False
    assert out["truth_adjudicated"] is False
    assert out["workspace_persisted"] is False

def test_export_and_handoff_are_nonpersistent():
    exp=export_synthesis(SAMPLE)
    assert exp["automatic_import"] is False
    assert exp["content"]
    handoff=publishing_handoff_preview(SAMPLE)
    assert handoff["preview_only"] is True
    assert handoff["automatic_persistence"] is False
    assert handoff["signed_persistence_required"] is True
    assert handoff["preview_endpoint"]=="/api/library/v1/research-package-publishing/preview"
