from app.historical_archive_workspace import (
    BACKEND_VERSION,
    LIBRARY_VERSION,
    WEB_VERSION,
    bootstrap,
    compare_workspace,
    contract,
    handoff_workspace,
    packet_workspace,
    readiness,
    source_workspace,
    timeline_workspace,
)


def sample_source(title="Letter from A to B", year="circa 1912"):
    return {
        "title": title,
        "source_type": "personal-papers",
        "creators": [{"name": "A", "role": "author"}],
        "date": year,
        "repository": {"name": "Example Archive", "source_key": "example"},
        "archival_context": {
            "collection": "A Papers",
            "series": "Correspondence",
            "folder": "1912",
            "shelfmark": "MS 1/2/3",
        },
        "original_language": "en",
        "digital_surrogate": {"url": "https://example.test/item/1"},
        "derivations": [
            {"kind": "scan", "representation_id": "scan:1"},
            {"kind": "ocr", "representation_id": "ocr:1", "derived_from": "scan:1"},
        ],
    }


def test_release_identity():
    assert LIBRARY_VERSION == "6.13.0"
    assert BACKEND_VERSION == "3.13.0"
    assert WEB_VERSION == "2.13.0"


def test_contract_is_standalone_workspace():
    c = contract()
    assert c["route"] == "/research/archives"
    assert c["wordpress_required"] is False
    assert c["database_migration_required"] is False
    assert c["guardrails"]["automatic_library_import"] is False


def test_source_workspace_preserves_lineage_and_non_truth_boundary():
    result = source_workspace({"source": sample_source()})
    assert result["source"]["archival_context"]["shelfmark"] == "MS 1/2/3"
    assert result["source"]["derivations"][1]["kind"] == "ocr"
    assert result["source_criticism"]["truth_score"] is None
    assert result["authenticity_certified"] is False


def test_comparison_and_timeline_preserve_disagreement_and_uncertainty():
    result = compare_workspace({"sources": [sample_source(), sample_source("Second letter", "1913")]})
    assert result["comparison"]["disagreement_auto_resolved"] is False
    assert result["truth_determination"] is None
    assert result["timeline"]["uncertain_dates_preserved"] is True


def test_timeline_workspace_does_not_add_false_precision():
    result = timeline_workspace({"sources": [sample_source()]})
    assert result["false_precision_added"] is False
    assert result["timeline"]["events"][0]["qualifier"] == "circa"


def test_packet_and_handoff_are_previews_only():
    payload = {"project_id": "project:1", "research_question": "What changed?", "sources": [sample_source()]}
    packet = packet_workspace(payload)
    handoff = handoff_workspace(payload)
    assert packet["preview"] is True and packet["persisted"] is False
    assert handoff["preview"] is True and handoff["executed"] is False
    assert handoff["automatic_import"] is False


def test_bootstrap_and_readiness_shapes():
    b = bootstrap()
    r = readiness()
    assert b["route"] == "/research/archives"
    assert isinstance(b["source_types"], list)
    assert r["wordpress_required"] is False
