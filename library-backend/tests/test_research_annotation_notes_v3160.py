from app.research_annotation_notes import (
    bootstrap,
    build_notebook,
    export_notes,
    normalize_annotation,
    normalize_relation,
    readiness,
)


def sample_note(kind="question"):
    return {
        "note_type": kind,
        "body": "What does the source actually establish?",
        "target": {"kind": "library-record", "id": "record:1", "title": "Test record", "content_sha256": "a" * 64},
        "anchor": {"page": "12", "selected_quote": "Example passage"},
        "tags": ["method", "review"],
    }


def test_contract_and_readiness():
    b = bootstrap()
    assert b["route"] == "/research/notes"
    assert "question" in b["note_types"]
    r = readiness()
    assert r["ready"] is True
    assert r["database_migration_required"] is False
    assert r["server_side_note_persistence"] is False


def test_annotation_preserves_non_authority():
    note = normalize_annotation(sample_note())
    assert note["target"]["id"] == "record:1"
    assert note["anchor"]["selected_quote"] == "Example passage"
    assert note["anchor"]["quote_verified"] is False
    assert note["truth_status"] is None
    assert note["evidence_status"] is None
    assert note["citation_status"] is None
    assert note["persisted"] is False


def test_relation_is_explicit_not_inferred():
    rel = normalize_relation({"source_annotation_id": "a", "target_annotation_id": "b", "relation": "contextualizes"})
    assert rel["human_asserted"] is True
    assert rel["inferred"] is False


def test_notebook_keeps_unresolved_questions():
    nb = build_notebook({"annotations": [sample_note("question"), {**sample_note("summary"), "body": "A descriptive summary."}]})
    assert nb["annotation_count"] == 2
    assert len(nb["unresolved_questions"]) == 1
    assert nb["automatic_synthesis"] is False
    assert nb["truth_determination"] is None


def test_export_is_preview_not_import():
    out = export_notes({"annotations": [sample_note()]})
    assert out["format"] == "application/json"
    assert out["persisted"] is False
    assert out["automatic_import"] is False
    assert out["automatic_evidence_promotion"] is False
