from app.literature_review import build_literature_review, compare_literature_reviews, REVIEW_SCHEMA, CHANGESET_SCHEMA


def sample_payload():
    return {
        "protocol": {
            "title": "Carbon pricing review",
            "research_question": "What is the relationship between carbon pricing and industrial emissions?",
            "inclusion_criteria": ["Empirical study", "Reports industrial emissions"],
            "exclusion_criteria": ["Commentary only"],
            "search_strategies": [{"source":"Library hybrid search","query":"carbon pricing industrial emissions","executed_at":"2026-09-26","result_count":3}],
            "extraction_fields": ["population","design","effect estimate"],
        },
        "records": [
            {"record_id":"r1","title":"Study A"},
            {"record_id":"r2","title":"Study B"},
            {"record_id":"r3","title":"Study C"},
        ],
        "decisions": [
            {"record_id":"r1","stage":"title-abstract","decision":"include","reviewer":"reviewer-1"},
            {"record_id":"r1","stage":"full-text","decision":"include","reviewer":"reviewer-1"},
            {"record_id":"r2","stage":"title-abstract","decision":"exclude","reason":"Commentary only","reviewer":"reviewer-1"},
            {"record_id":"r3","stage":"title-abstract","decision":"uncertain","reviewer":"reviewer-2"},
        ],
        "extractions": [{"record_id":"r1","fields":{"design":"panel study"},"extractor":"reviewer-1","source_spans":[{"page":4,"label":"Methods"}]}],
    }


def test_build_review_is_deterministic_and_human_governed():
    a=build_literature_review(sample_payload()); b=build_literature_review(sample_payload())
    assert a["schema"] == REVIEW_SCHEMA
    assert a["review_id"] == b["review_id"]
    assert a["sets"]["included_record_ids"] == ["r1"]
    assert a["sets"]["excluded_record_ids"] == ["r2"]
    assert a["sets"]["pending_record_ids"] == ["r3"]
    assert a["metrics"]["included_extraction_coverage"] == 1.0
    assert a["guardrails"]["automatic_screening_decisions"] is False
    assert a["guardrails"]["automatic_inclusion_exclusion"] is False
    assert a["guardrails"]["automatic_meta_analysis"] is False
    assert a["guardrails"]["human_review_required"] is True
    assert all(e["default_evidence_path"] is False for e in a["graph_overlay"]["edges"])


def test_protocol_and_review_have_stable_fingerprints():
    review=build_literature_review(sample_payload())
    assert len(review["protocol"]["protocol_fingerprint_sha256"]) == 64
    assert len(review["snapshot"]["review_fingerprint_sha256"]) == 64
    assert review["search_lineage"]["sources"] == ["Library hybrid search"]


def test_missing_protocol_metadata_is_warning_not_inference():
    review=build_literature_review({"protocol":{"title":"Sparse"},"records":[],"decisions":[]})
    assert len(review["warnings"]) >= 3
    assert review["guardrails"]["review_is_global_literature_completeness_claim"] is False


def test_compare_review_states_is_descriptive():
    left=sample_payload(); right=sample_payload()
    right["decisions"][-1] = {"record_id":"r3","stage":"full-text","decision":"include","reviewer":"reviewer-2"}
    delta=compare_literature_reviews(left,right)
    assert delta["schema"] == CHANGESET_SCHEMA
    assert delta["added_included_record_ids"] == ["r3"]
    assert delta["guardrails"]["added_record_implies_support"] is False


def test_duplicate_is_only_excluded_when_explicit_decision_exists():
    p=sample_payload(); p["decisions"]=[]
    review=build_literature_review(p)
    assert review["flow"]["duplicate_decisions"] == 0
    p["decisions"]=[{"record_id":"r2","stage":"title-abstract","decision":"duplicate","reviewer":"reviewer-1"}]
    review=build_literature_review(p)
    assert review["flow"]["duplicate_decisions"] == 1
