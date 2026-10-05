from app.research_question_investigation_workspace import (
    build_investigation, coverage_analysis, execution_plan, guardrails,
    handoff_preview, investigation_matrix, risk_register, export_investigation,
)

SAMPLE = {
    "title": "Carbon-removal investigation",
    "research_question": "Under what conditions does enhanced weathering deliver durable net carbon removal?",
    "scope": "Peer-reviewed and primary-source evidence; global, with explicit attention to geography and time.",
    "subquestions": [
        {"subquestion_id":"sq1","text":"What removal is directly measured?","priority":"critical"},
        {"subquestion_id":"sq2","text":"What lifecycle emissions offset removal?","priority":"high"},
    ],
    "hypotheses": [
        {"hypothesis_id":"h1","text":"Net removal remains positive after lifecycle emissions.","subquestion_ids":["sq2"]},
    ],
    "evidence_needs": [
        {"evidence_need_id":"e1","description":"Direct field measurement of weathering-related carbon removal.","kind":"publication","subquestion_ids":["sq1"],"priority":"critical"},
        {"evidence_need_id":"e2","description":"Lifecycle inventory for mining, grinding, transport, and spreading.","kind":"dataset","subquestion_ids":["sq2"],"hypothesis_ids":["h1"],"priority":"high"},
    ],
    "tasks": [
        {"task_id":"t1","description":"Search for field trials.","task_type":"search","subquestion_ids":["sq1"],"evidence_need_ids":["e1"],"search_query":"enhanced weathering field trial carbon removal"},
        {"task_id":"t2","description":"Retrieve lifecycle assessments.","task_type":"retrieve","subquestion_ids":["sq2"],"evidence_need_ids":["e2"],"search_query":"enhanced weathering lifecycle assessment"},
        {"task_id":"t3","description":"Compare direct removal with lifecycle burdens.","task_type":"compare","subquestion_ids":["sq1","sq2"],"evidence_need_ids":["e1","e2"],"depends_on":["t1","t2"]},
    ],
    "decision_points": [
        {"decision_point_id":"d1","question":"Is evidence coverage sufficient for synthesis?","depends_on_task_ids":["t3"],"criteria":["Both direct-removal and lifecycle evidence are reviewed."]},
    ],
    "stop_conditions": [
        {"stop_condition_id":"stop1","description":"Pause when source coverage is sufficient for manual synthesis review."},
    ],
    "risks": [
        {"risk_id":"r1","risk_type":"geographic","description":"Evidence may overrepresent temperate agricultural settings.","mitigation":"Require geographic diversity."},
    ],
    "source_strategy": {"languages":["en"],"include":["peer-reviewed","primary-source"],"exclude":["uncited marketing claims"]},
}

def test_guardrails():
    g=guardrails()
    assert g["workspace_is_autonomous_research_agent"] is False
    assert g["task_completion_is_evidence"] is False
    assert g["hypothesis_is_truth"] is False
    assert g["automatic_source_trust"] is False

def test_matrix_links_question_to_evidence_and_tasks():
    m=investigation_matrix(SAMPLE)
    sq1=[x for x in m["rows"] if x["subquestion_id"]=="sq1"][0]
    assert "e1" in sq1["evidence_need_ids"]
    assert "t1" in sq1["task_ids"]
    assert sq1["answered"] is False

def test_coverage_and_execution():
    coverage=coverage_analysis(SAMPLE)
    assert coverage["complete"] is True
    plan=execution_plan(SAMPLE)
    assert plan["waves"][0]["task_ids"]==["t1","t2"]
    assert plan["waves"][1]["task_ids"]==["t3"]
    assert plan["automatically_executed"] is False

def test_risk_register_preserves_manual_controls():
    r=risk_register(SAMPLE)
    assert r["risk_count"]==1
    assert r["automatic_source_trust"] is False

def test_build_does_not_conclude_or_execute():
    out=build_investigation(SAMPLE)
    assert out["conclusion_generated"] is False
    assert out["hypotheses_adjudicated"] is False
    assert out["tasks_executed"] is False
    assert out["workspace_persisted"] is False

def test_handoffs_are_preview_only():
    for kind in ["research-project","saved-search","research-queue","retrieval-plan"]:
        h=handoff_preview({**SAMPLE,"handoff_type":kind})
        assert h["preview_only"] is True
        assert h["automatic_submission"] is False
        assert h["automatic_persistence"] is False
        assert h["signed_request_required"] is True

def test_export():
    e=export_investigation(SAMPLE)
    assert e["automatic_import"] is False
    assert e["workspace_persisted"] is False
    assert e["content"]
