from app.historical_archive_primary_source import (
    BACKEND_VERSION,
    LIBRARY_VERSION,
    analyze_primary_source,
    build_primary_source_packet,
    build_timeline,
    compare_primary_sources,
    normalize_date_assertion,
    normalize_primary_source,
    provenance_chain,
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
    assert LIBRARY_VERSION == "6.12.0"
    assert BACKEND_VERSION == "3.12.0"


def test_date_uncertainty_is_preserved():
    d = normalize_date_assertion("circa 1912")
    assert d["qualifier"] == "circa"
    assert d["start_year"] == 1912
    assert d["normalized_without_false_precision"] is True


def test_primary_source_distinguishes_derived_text():
    source = normalize_primary_source(sample_source())
    assert source["candidate_primary_source"] is True
    assert source["archival_context"]["shelfmark"] == "MS 1/2/3"
    assert source["derivations"][1]["kind"] == "ocr"
    assert source["derivations"][1]["is_original_text"] is False
    assert source["guardrails"]["primary_source_label_implies_truth"] is False


def test_provenance_chain_preserves_mediation():
    chain = provenance_chain(sample_source())
    assert chain["derived_representation_count"] == 2
    assert chain["original_and_derived_are_distinct"] is True


def test_source_criticism_is_not_truth_scoring():
    result = analyze_primary_source(sample_source())
    assert "machine-derived-text-present" in result["flags"]
    assert result["authenticity_certified"] is False
    assert result["truth_score"] is None


def test_comparison_does_not_resolve_disagreement():
    result = compare_primary_sources({"sources": [sample_source(year="1912"), sample_source("Second letter", "1913")]})
    assert "creation_date" in result["disagreement_dimensions"]
    assert result["disagreement_auto_resolved"] is False
    assert result["truth_determination"] is None


def test_timeline_retains_uncertain_dates():
    result = build_timeline({"sources": [sample_source(year="circa 1912"), sample_source("Later", "1914")]})
    assert result["event_count"] == 2
    assert result["uncertain_dates_preserved"] is True
    assert result["events"][0]["qualifier"] == "circa"


def test_packet_is_reproducible_and_non_promoting():
    packet = build_primary_source_packet({"project_id": "project:1", "sources": [sample_source()]})
    assert packet["source_count"] == 1
    assert packet["persisted"] is False
    assert packet["evidence_promoted"] is False
    assert len(packet["packet_fingerprint_sha256"]) == 64
