from app.historical_event_timeline import (
    build_timeline,
    compare_chronologies,
    normalize_event,
    readiness,
    source_coverage,
)


def sample_source(title, year):
    return {
        "title": title,
        "source_type": "personal-papers",
        "creators": ["Researcher"],
        "date": str(year),
        "repository": {"name": "Archive"},
        "archival_context": {"collection": "Papers", "shelfmark": title},
        "original_language": "en",
    }


def test_readiness_and_event_uncertainty():
    r = readiness()
    assert r["library_version"] == "6.15.0"
    assert r["backend_version"] == "3.15.0"
    assert r["wordpress_required"] is False
    e = normalize_event({"title": "Approximate event", "date": "circa 1972", "event_key": "event-a"})
    assert e["event_key"] == "event-a"
    assert e["event_key_explicit"] is True
    assert e["truth_status"] is None
    assert e["causal_status"] is None


def test_timeline_preserves_unknowns_and_no_causation():
    timeline = build_timeline({
        "events": [
            {"title": "Known", "date": "1971", "event_key": "known"},
            {"title": "Unknown", "date": "", "event_key": "unknown"},
        ],
        "relationships": [],
    })
    assert timeline["event_count"] == 2
    assert timeline["display_order_is_asserted_chronology"] is False
    assert timeline["truth_determination"] is None
    assert len(timeline["unknown_date_event_ids"]) == 1


def test_source_coverage_is_descriptive():
    source = sample_source("Letter", 1971)
    from app.historical_archive_primary_source import normalize_primary_source
    normalized = normalize_primary_source(source)
    coverage = source_coverage({
        "sources": [source],
        "events": [{
            "title": "Event",
            "date": "1971",
            "source_assertions": [{"primary_source_id": normalized["primary_source_id"], "relation": "attests"}],
        }],
    })
    assert coverage["source_count"] == 1
    assert coverage["coverage_is_confidence_score"] is False
    assert coverage["truth_determination"] is None


def test_competing_chronologies_require_explicit_key_and_no_winner():
    comparison = compare_chronologies({
        "chronologies": [
            {"chronology_id": "a", "title": "A", "events": [{"title": "Event", "event_key": "shared", "date": "1971"}]},
            {"chronology_id": "b", "title": "B", "events": [{"title": "Event", "event_key": "shared", "date": "circa 1972"}]},
        ]
    })
    assert comparison["chronology_count"] == 2
    assert comparison["winner"] is None
    assert comparison["automatic_reconciliation"] is False
    assert comparison["aligned_event_rows"][0]["date_disagreement"] is True
