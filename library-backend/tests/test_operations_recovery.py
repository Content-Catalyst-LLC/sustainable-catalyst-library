from datetime import datetime, timezone

from app.models import ExpectedRecordState, IntegrityAuditRequest, PruneRequest


def test_integrity_audit_models_accept_expected_wordpress_state():
    payload = IntegrityAuditRequest(
        source_key="wordpress-main",
        records=[
            ExpectedRecordState(
                record_id="wordpress:1:post:42",
                source_updated_at=datetime(2026, 9, 1, 23, 30, tzinfo=timezone.utc),
            )
        ],
    )
    assert payload.source_key == "wordpress-main"
    assert payload.records[0].record_id.endswith(":42")


def test_prune_request_deduplicates_exact_record_ids():
    payload = PruneRequest(
        source_key="wordpress-main",
        record_ids=["wordpress:1:post:1", "wordpress:1:post:1", "wordpress:1:post:2"],
    )
    assert payload.record_ids == ["wordpress:1:post:1", "wordpress:1:post:2"]


def test_integrity_classifier_finds_missing_stale_orphan_and_chunkless():
    from app.integrity import classify_integrity

    t1 = datetime(2026, 9, 1, 10, 0, tzinfo=timezone.utc)
    t2 = datetime(2026, 9, 1, 11, 0, tzinfo=timezone.utc)
    expected = {
        "wordpress:1:post:1": t1,
        "wordpress:1:post:2": t2,
        "wordpress:1:post:3": t1,
    }
    rows = [
        {"record_id": "wordpress:1:post:1", "source_updated_at": t1, "body_length": 100, "has_chunks": True},
        {"record_id": "wordpress:1:post:2", "source_updated_at": t1, "body_length": 100, "has_chunks": True},
        {"record_id": "wordpress:1:post:3", "source_updated_at": t1, "body_length": 100, "has_chunks": False},
        {"record_id": "wordpress:1:post:99", "source_updated_at": t1, "body_length": 100, "has_chunks": True},
    ]
    result = classify_integrity(expected, rows)
    assert result["missing"] == []
    assert result["stale"] == ["wordpress:1:post:2"]
    assert result["chunkless"] == ["wordpress:1:post:3"]
    assert result["orphaned"] == ["wordpress:1:post:99"]
    assert result["repair"] == ["wordpress:1:post:2", "wordpress:1:post:3"]


def test_integrity_classifier_identifies_missing_record():
    from app.integrity import classify_integrity

    expected = {"wordpress:1:post:5": datetime(2026, 9, 1, tzinfo=timezone.utc)}
    result = classify_integrity(expected, [])
    assert result["missing"] == ["wordpress:1:post:5"]
    assert result["repair"] == ["wordpress:1:post:5"]
