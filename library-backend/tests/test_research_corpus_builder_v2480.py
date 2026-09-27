from __future__ import annotations

import csv
import io
import json

import pytest

from app.research_corpus_builder import (
    CORPUS_SCHEMA,
    DATASET_SCHEMA,
    EXPORT_PACKAGE_SCHEMA,
    build_research_corpus,
    export_research_corpus,
)


def records():
    return [
        {
            "record_id": "r2",
            "title": "Second study",
            "doi": "10.1000/second",
            "published_at": "2025-04-02",
            "source_type": "journal-article",
            "topics": ["carbon", "cities"],
            "authors": ["B. Author"],
            "source_key": "pubmed",
            "source_hash": "hash-r2",
        },
        {
            "record_id": "r1",
            "title": "First study",
            "doi": "10.1000/first",
            "published_at": "2024-01-15",
            "source_type": "journal-article",
            "topics": ["carbon"],
            "authors": ["A. Author"],
            "source_key": "library",
            "source_hash": "hash-r1",
        },
    ]


def test_deterministic_corpus_and_row_provenance():
    payload = {"title": "Carbon corpus", "records": records(), "fields": ["record_id", "title", "doi"]}
    a = build_research_corpus(payload)
    b = build_research_corpus(payload)
    assert a["schema"] == CORPUS_SCHEMA
    assert a["corpus_id"] == b["corpus_id"]
    assert a["manifest"]["corpus_fingerprint_sha256"] == b["manifest"]["corpus_fingerprint_sha256"]
    assert [row["record_id"] for row in a["rows"]] == ["r1", "r2"]
    assert [p["source_record_id"] for p in a["row_provenance"]] == ["r1", "r2"]
    assert all(p["row_fingerprint_sha256"] for p in a["row_provenance"])


def test_explicit_selection_and_filters_are_descriptive_not_truth_judgments():
    out = build_research_corpus({
        "records": records(),
        "selection": {
            "include_record_ids": ["r1", "r2"],
            "exclude_record_ids": ["r2"],
            "criteria": {"topics": ["carbon"]},
            "human_reviewed": True,
        },
    })
    assert [r["record_id"] for r in out["records"]] == ["r1"]
    assert out["guardrails"]["corpus_membership_implies_truth"] is False
    assert out["guardrails"]["corpus_membership_implies_evidence_quality"] is False
    assert out["guardrails"]["corpus_exclusion_implies_falsehood"] is False
    assert out["guardrails"]["automatic_platform_core_promotion"] is False


def test_duplicate_record_ids_are_not_silently_duplicated():
    dup = records() + [{"record_id": "r1", "title": "duplicate copy"}]
    out = build_research_corpus({"records": dup})
    assert out["metrics"]["provided_record_count"] == 3
    assert out["metrics"]["unique_record_count"] == 2
    assert out["metrics"]["duplicate_record_id_count"] == 1
    assert out["manifest"]["duplicate_record_ids_ignored"] == ["r1"]


def test_json_jsonl_csv_exports_share_rows_and_provenance():
    corpus = build_research_corpus({"title": "Export corpus", "records": records(), "fields": ["record_id", "title", "authors"]})
    json_out = export_research_corpus({"corpus": corpus, "export": {"format": "json"}})
    jsonl_out = export_research_corpus({"corpus": corpus, "export": {"format": "jsonl"}})
    csv_out = export_research_corpus({"corpus": corpus, "export": {"format": "csv"}})
    assert json_out["schema"] == DATASET_SCHEMA
    assert len(json.loads(json_out["content"])) == 2
    assert len([x for x in jsonl_out["content"].splitlines() if x]) == 2
    parsed_csv = list(csv.DictReader(io.StringIO(csv_out["content"])))
    assert [r["record_id"] for r in parsed_csv] == ["r1", "r2"]
    assert len(csv_out["row_provenance"]) == 2


def test_bundle_contains_all_three_formats_with_hashes():
    out = export_research_corpus({"title": "Bundle", "records": records(), "export": {"format": "bundle"}})
    assert out["schema"] == EXPORT_PACKAGE_SCHEMA
    assert set(out["exports"]) == {"json", "jsonl", "csv"}
    assert all(v["sha256"] for v in out["exports"].values())



def test_duplicate_resolution_is_input_order_independent():
    a = {"record_id": "r1", "title": "Variant A"}
    b = {"record_id": "r1", "title": "Variant B"}
    x = build_research_corpus({"records": [a, b]})
    y = build_research_corpus({"records": [b, a]})
    assert x["corpus_id"] == y["corpus_id"]
    assert x["records"] == y["records"]


def test_missing_date_does_not_satisfy_explicit_date_window():
    out = build_research_corpus({
        "records": [{"record_id": "dated", "published_at": "2025-01-01"}, {"record_id": "undated"}],
        "selection": {"criteria": {"date_from": "2024-01-01", "date_to": "2026-12-31"}},
    })
    assert [r["record_id"] for r in out["records"]] == ["dated"]

def test_unknown_fields_and_formats_are_rejected():
    with pytest.raises(ValueError):
        build_research_corpus({"records": records(), "fields": ["record_id", "truth_score"]})
    corpus = build_research_corpus({"records": records()})
    with pytest.raises(ValueError):
        export_research_corpus({"corpus": corpus, "export": {"format": "xlsx"}})


def test_core_handoff_is_explicit_candidate_only():
    out = build_research_corpus({"records": records(), "core_handoff_requested": True})
    assert out["core_handoff"]["requested"] is True
    assert out["core_handoff"]["automatic"] is False
    assert out["core_handoff"]["durable_authority"] == "platform-core"
