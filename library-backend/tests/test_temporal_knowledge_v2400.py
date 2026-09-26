from app.temporal_knowledge import (
    TEMPORAL_SCHEMA, SNAPSHOT_SCHEMA, CHANGESET_SCHEMA,
    build_temporal_knowledge_evolution, compare_snapshots, knowledge_snapshot, temporal_request,
)


def corpus():
    return [
        {"record_id":"a","title":"Study A","published_at":"2019-01-01","indexed_at":"2019-01-02","content_hash":"ha","metadata":{"temporal_events":[{"type":"correction","date":"2021-05-01","label":"Correction issued"}]}},
        {"record_id":"b","title":"Study B","published_at":"2020-03-01","indexed_at":"2020-03-02","content_hash":"hb","metadata":{"retracted_at":"2022-06-01"}},
        {"record_id":"c","title":"Study C","published_at":"2023-01-01","indexed_at":"2023-01-02","content_hash":"hc","metadata":{"dataset_updates":[{"date":"2024-02-01","label":"Dataset refresh"}]}},
    ]


def test_temporal_analysis_is_deterministic_and_event_grounded():
    a=build_temporal_knowledge_evolution(corpus())
    b=build_temporal_knowledge_evolution(corpus())
    assert a["schema"] == TEMPORAL_SCHEMA
    assert a["fingerprint_sha256"] == b["fingerprint_sha256"]
    assert a["metrics"]["record_count"] == 3
    assert any(x["event_type"]=="corrected" for x in a["events"])
    assert any(x["event_type"]=="retracted" for x in a["events"])
    assert any(x["event_type"]=="dataset-updated" for x in a["events"])


def test_historical_availability_does_not_project_later_events_backward():
    a=build_temporal_knowledge_evolution(corpus())
    s=knowledge_snapshot(a,"2020-12-31",lens="historical-availability")
    assert s["schema"] == SNAPSHOT_SCHEMA
    assert {x["record_id"] for x in s["records"]} == {"a","b"}
    assert {x["record_id"]:x["status_as_of"] for x in s["records"]} == {"a":"active","b":"active"}
    assert s["guardrails"]["historical_availability_uses_only_events_known_by_cutoff"] is True


def test_retrospective_lens_can_annotate_later_correction_and_retraction():
    a=build_temporal_knowledge_evolution(corpus())
    s=knowledge_snapshot(a,"2020-12-31",lens="retrospective-status")
    assert {x["record_id"]:x["status_as_of"] for x in s["records"]} == {"a":"corrected","b":"retracted"}
    assert s["guardrails"]["retrospective_status_may_surface_later_corrections"] is True


def test_snapshot_excludes_records_not_available_at_cutoff():
    a=build_temporal_knowledge_evolution(corpus())
    s=knowledge_snapshot(a,"2022-12-31")
    assert "c" not in {x["record_id"] for x in s["records"]}


def test_change_set_reports_additions_and_status_transitions_without_causality():
    a=build_temporal_knowledge_evolution(corpus())
    c=compare_snapshots(a,"2019-12-31","2023-12-31")
    assert c["schema"] == CHANGESET_SCHEMA
    assert c["metrics"]["added"] == 2
    assert c["metrics"]["status_changed"] == 1
    assert c["interpretation"]["change_is_causal_explanation"] is False
    assert c["interpretation"]["change_is_consensus_shift"] is False


def test_research_object_events_require_explicit_dates():
    a=build_temporal_knowledge_evolution(corpus(), nodes=[
        {"id":"claim:1","kind":"claim","label":"Claim one","accepted_at":"2022-01-01"},
        {"id":"claim:2","kind":"claim","label":"Claim two"},
    ])
    assert len(a["research_object_events"]) == 1
    assert a["research_object_events"][0]["object_id"] == "claim:1"


def test_temporal_request_supports_snapshot_and_change_set_together():
    out=temporal_request({"records":corpus(),"as_of":"2020-12-31","from_date":"2019-12-31","to_date":"2023-12-31"})
    assert out["snapshot"]["schema"] == SNAPSHOT_SCHEMA
    assert out["change_set"]["schema"] == CHANGESET_SCHEMA


def test_temporal_guardrails_are_explicit():
    out=build_temporal_knowledge_evolution(corpus())
    g=out["guardrails"]
    assert g["later_event_projected_backward_by_default"] is False
    assert g["absence_of_later_evidence_implies_earlier_consensus"] is False
    assert g["temporal_coincidence_implies_causality"] is False
    assert g["record_availability_equals_researcher_awareness"] is False
    assert g["platform_core_governance_changed"] is False
