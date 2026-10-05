from app.evidence_matrix_claim_support_workspace import (
    claim_evidence_matrix, contradiction_analysis, export_analysis, gap_analysis,
    guardrails, investigation_handoff_preview, provenance_coverage,
    source_dependencies, support_profiles, synthesis_handoff_preview,
)

SAMPLE={
 "title":"Enhanced weathering evidence matrix",
 "research_question":"What does the evidence support about durable net carbon removal?",
 "claims":[
   {"claim_id":"c1","text":"Enhanced weathering produces measurable carbon removal."},
   {"claim_id":"c2","text":"Net removal remains positive after lifecycle emissions."},
   {"claim_id":"c3","text":"Long-term ecosystem impacts are fully established."}],
 "evidence":[
   {"evidence_id":"e1","title":"Field trial A","kind":"publication","source_id":"study-a","independence_group":"trial-a","provenance_state":"complete","record_id":"record:a"},
   {"evidence_id":"e2","title":"Field trial B","kind":"publication","source_id":"study-b","independence_group":"trial-b","provenance_state":"complete","record_id":"record:b"},
   {"evidence_id":"e3","title":"Review using trial A","kind":"publication","source_id":"review-c","independence_group":"trial-a","provenance_state":"partial"}],
 "links":[
   {"claim_id":"c1","evidence_id":"e1","relationship":"supports","directness":"direct","rationale":"Direct field measurement."},
   {"claim_id":"c1","evidence_id":"e2","relationship":"contradicts","directness":"direct","rationale":"No measurable removal in a second setting."},
   {"claim_id":"c2","evidence_id":"e3","relationship":"qualifies","directness":"indirect","rationale":"Lifecycle assumptions vary."}]}

def test_guardrails():
    g=guardrails()
    assert g["workspace_is_new_truth_authority"] is False
    assert g["support_count_is_truth_probability"] is False
    assert g["independent_source_count_is_confidence_score"] is False
    assert g["automatic_source_independence_assumption"] is False

def test_matrix_preserves_relationships():
    m=claim_evidence_matrix(SAMPLE)
    c1=[x for x in m["rows"] if x["claim_id"]=="c1"][0]
    assert c1["relationship_counts"]["supports"]==1
    assert c1["relationship_counts"]["contradicts"]==1
    assert c1["independent_source_group_count"]==2
    assert c1["verdict"] is None

def test_profiles_are_descriptive():
    p=support_profiles(SAMPLE)
    c1=[x for x in p["profiles"] if x["claim_id"]=="c1"][0]
    assert c1["pattern"]=="mixed-support-and-contradiction"
    assert c1["truth_probability"] is None
    assert c1["confidence_score"] is None
    assert c1["verdict"] is None

def test_contradictions_not_resolved():
    c=contradiction_analysis(SAMPLE)
    assert c["count"]>=1
    assert c["automatic_resolution"] is False
    assert c["items"][0]["resolved"] is False

def test_provenance_and_dependencies_visible():
    p=provenance_coverage(SAMPLE)
    assert p["provenance_state_counts"]["complete"]==2
    d=source_dependencies(SAMPLE)
    trial_a=[x for x in d["rows"] if x["independence_group"]=="trial-a"][0]
    assert trial_a["potential_non_independence"] is True
    assert trial_a["independence_assumed"] is False

def test_gaps_include_unlinked_claim():
    g=gap_analysis(SAMPLE)
    assert any(x["kind"]=="claim-without-evidence" and x["claim_id"]=="c3" for x in g["gaps"])

def test_handoffs_preview_only():
    s=synthesis_handoff_preview(SAMPLE)
    assert s["preview_only"] is True and s["automatic_submission"] is False
    i=investigation_handoff_preview(SAMPLE)
    assert i["preview_only"] is True and i["automatic_task_execution"] is False

def test_export_nonpersistent():
    e=export_analysis(SAMPLE)
    assert e["automatic_import"] is False
    assert e["workspace_persisted"] is False
    assert e["content"]
