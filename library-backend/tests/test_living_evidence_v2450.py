from app.living_evidence import build_living_evidence, LIVING_EVIDENCE_SCHEMA, UPDATE_CANDIDATE_SCHEMA


def review(records, decisions, extractions=None, protocol_version="1"):
    return {
        "protocol": {
            "title": "Living carbon review",
            "research_question": "How is carbon pricing associated with industrial emissions?",
            "inclusion_criteria": ["Empirical"],
            "exclusion_criteria": ["Commentary"],
            "search_strategies": [{"source":"Library","query":"carbon pricing industrial emissions","executed_at":"2026-09-01"}],
            "protocol_version": protocol_version,
        },
        "records": records,
        "decisions": decisions,
        "extractions": extractions or [],
    }


def sample_payload():
    baseline = review(
        [
            {"record_id":"r1","title":"Study A","content_hash":"aaa","published_at":"2024-01-01","publication_status":"published"},
            {"record_id":"r2","title":"Study B","content_hash":"bbb","published_at":"2023-01-01","publication_status":"published"},
        ],
        [
            {"record_id":"r1","stage":"full-text","decision":"include","reviewer":"a"},
            {"record_id":"r2","stage":"title-abstract","decision":"exclude","reason":"Commentary","reviewer":"a"},
        ],
        [{"record_id":"r1","fields":{"effect":"-5%"},"extractor":"a"}],
    )
    current = review(
        [
            {"record_id":"r1","title":"Study A","content_hash":"aaa2","published_at":"2024-01-01","publication_status":"corrected"},
            {"record_id":"r2","title":"Study B","content_hash":"bbb","published_at":"2023-01-01","publication_status":"published"},
            {"record_id":"r3","title":"Study C","content_hash":"ccc","published_at":"2026-03-01","publication_status":"published"},
        ],
        [
            {"record_id":"r1","stage":"full-text","decision":"include","reviewer":"a"},
            {"record_id":"r2","stage":"title-abstract","decision":"exclude","reason":"Commentary","reviewer":"a"},
        ],
        [{"record_id":"r1","fields":{"effect":"-4%"},"extractor":"a"}],
    )
    return {
        "baseline_review": baseline,
        "current_review": current,
        "events": [{"record_id":"r1","event_type":"corrected","occurred_at":"2026-09-20","source":"publisher"}],
        "cadence_days": 30,
        "as_of": "2026-09-26",
    }


def test_living_evidence_is_deterministic_and_does_not_mutate_prior_review():
    a=build_living_evidence(sample_payload()); b=build_living_evidence(sample_payload())
    assert a["schema"] == LIVING_EVIDENCE_SCHEMA
    assert a["living_evidence_id"] == b["living_evidence_id"]
    kinds={c["kind"] for c in a["update_candidates"]}
    assert "new-record" in kinds
    assert "source-record-changed" in kinds
    assert "explicit-source-status-change" in kinds
    assert "extraction-changed" in kinds
    assert "explicit-change-event" in kinds
    assert a["guardrails"]["new_record_is_automatically_included"] is False
    assert a["guardrails"]["changed_record_invalidates_prior_review"] is False
    assert a["guardrails"]["extraction_changes_overwrite_prior_snapshot"] is False
    assert a["guardrails"]["human_review_required"] is True


def test_all_update_candidates_require_review_and_do_not_enter_default_evidence_paths():
    result=build_living_evidence(sample_payload())
    assert result["update_candidates"]
    assert all(c["schema"] == UPDATE_CANDIDATE_SCHEMA for c in result["update_candidates"])
    assert all(c["human_review_required"] is True for c in result["update_candidates"])
    assert all(c["automatic_state_change"] is False for c in result["update_candidates"])
    assert all(e["default_evidence_path"] is False for e in result["graph_overlay"]["edges"])
    assert all(e["truth_assertion"] is False for e in result["graph_overlay"]["edges"])


def test_decision_change_is_explicit_lineage_not_silent_rewrite():
    payload=sample_payload()
    payload["current_review"]["decisions"][1]={"record_id":"r2","stage":"full-text","decision":"include","reviewer":"b","reason":"Reassessed"}
    result=build_living_evidence(payload)
    hits=[c for c in result["update_candidates"] if c["kind"]=="screening-decision-changed" and c["record_id"]=="r2"]
    assert len(hits)==1
    assert result["guardrails"]["screening_decision_changes_are_silent"] is False
    assert result["review_change_set"]["added_included_record_ids"] == ["r2"]


def test_missing_current_record_is_not_called_withdrawn_or_false():
    payload=sample_payload()
    payload["current_review"]["records"]=[x for x in payload["current_review"]["records"] if x["record_id"]!="r2"]
    result=build_living_evidence(payload)
    hit=[c for c in result["update_candidates"] if c["kind"]=="record-not-present-in-current-snapshot" and c["record_id"]=="r2"]
    assert len(hit)==1
    assert "availability/version lineage" in hit[0]["suggested_review_action"]


def test_research_evolution_is_descriptive_chronology_only():
    result=build_living_evidence(sample_payload())
    evo=result["research_evolution"]
    years={x["year"] for x in evo["yearly_counts"]}
    assert {2023,2024,2026}.issubset(years)
    assert evo["explicit_event_type_counts"]["corrected"] == 1
    assert "does not infer causality" in evo["interpretation"]
    assert result["guardrails"]["chronology_implies_causality"] is False


def test_surveillance_is_plan_not_automatic_execution():
    result=build_living_evidence(sample_payload())
    surveillance=result["surveillance"]
    assert surveillance["cadence_days"] == 30
    assert surveillance["automatic_search_execution"] is False
    assert surveillance["automatic_screening"] is False
    assert surveillance["requires_scheduler_or_researcher_execution"] is True


def test_protocol_change_becomes_candidate_not_automatic_invalidation():
    payload=sample_payload(); payload["current_review"]["protocol"]["protocol_version"]="2"
    result=build_living_evidence(payload)
    assert result["review_change_set"]["protocol_changed"] is True
    assert any(c["kind"]=="review-protocol-changed" for c in result["update_candidates"])
    assert result["guardrails"]["changed_record_invalidates_prior_review"] is False


def test_requires_two_review_snapshots():
    try:
        build_living_evidence({"baseline_review": sample_payload()["baseline_review"]})
    except ValueError as exc:
        assert "baseline_review and current_review" in str(exc)
    else:
        raise AssertionError("expected ValueError")
