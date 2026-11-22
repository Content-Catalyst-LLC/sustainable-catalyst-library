"""SC-PUB-SEO-03B2 source-ledger safety and replay acceptance."""
from __future__ import annotations

from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "library-backend"))

from app.publication_state_sync import (
    EVENT_SCHEMA, PublicationStateError, apply_publication_event,
    can_approve_indexed, event_digest, normalize_event,
)
from app.publication_discovery_service import _candidate_query


def event(action="approve", sequence=1, **updates):
    base = {
        "schema": EVENT_SCHEMA,
        "record_id": "wordpress:1:post:123",
        "sequence": sequence,
        "action": action,
        "canonical_url": "https://sustainablecatalyst.com/erw/",
        "fingerprint": "a" * 64,
        "field": "sustainable-systems",
        "map_key": "enhanced-rock-weathering",
    }
    if action == "withdraw":
        base.update(canonical_url="", field="", map_key="")
    base.update(updates)
    return base


def indexed(**updates):
    original = {
        "object_type": "post",
        "canonical_url": "https://sustainablecatalyst.com/erw/",
        "visibility": "public",
        "publication_status": "published",
        "metadata": {
            "publication_manifest_schema": "sc-publications-editorial-manifest/0.1",
            "publication_discovery_approved": True,
            "wordpress_public_verified": True,
            "wordpress_password_protected": False,
            "publication_fingerprint": "a" * 64,
            "publication_field": "sustainable-systems",
            "publication_map_key": "enhanced-rock-weathering",
        }
    }
    original.update(updates)
    return original


class FakeCursor:
    def __init__(self, ledger, current):
        self.ledger = ledger
        self.current = current
        self.result = None
        self.statements = []

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        return False

    def execute(self, sql, params):
        self.statements.append((sql, params))
        if "pg_advisory_xact_lock" in sql:
            self.result = None
        elif "SELECT sequence, event_digest, approved" in sql:
            self.result = self.ledger.copy() if self.ledger is not None else None
        elif "SELECT object_type, canonical_url, visibility, publication_status" in sql:
            self.result = self.current
        elif "INSERT INTO publication_discovery_states" in sql:
            self.ledger = {
                "sequence": params[1],
                "event_digest": params[2],
                "approved": params[3],
            }
            self.result = None
        else:
            raise AssertionError("Unexpected SQL: " + sql[:200])

    def fetchone(self):
        return self.result


class FakeConn:
    def __init__(self, cursor):
        self.cursor_obj = cursor
        self.committed = False

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        return False

    def cursor(self):
        return self.cursor_obj

    def commit(self):
        self.committed = True


class FakePool:
    def __init__(self, conn):
        self.conn = conn

    def connection(self):
        return self.conn


class StateLedgerTests(unittest.TestCase):
    def test_validate_event_rejects_unexpected_or_private_metadata(self):
        for mutation in [
            {"record_id": "research:1"},
            {"record_id": "wordpress:1:page:123"},
            {"sequence": 0},
            {"sequence": True},
            {"fingerprint": "not-a-hash"},
            {"canonical_url": "https://evil.test/erw/"},
            {"canonical_url": "https://sustainablecatalyst.com/erw/?preview=1"},
            {"extra": "private title"}
        ]:
            with self.subTest(mutation=mutation):
                with self.assertRaises(PublicationStateError):
                    normalize_event(event(**mutation))

    def test_withdrawal_is_metadata_free(self):
        valid = normalize_event(event(action="withdraw", sequence=9))
        self.assertEqual(valid["canonical_url"], "")
        self.assertEqual(valid["field"], "")
        self.assertEqual(valid["map_key"], "")
        with self.assertRaises(PublicationStateError):
            normalize_event(event(action="withdraw", field="sustainable-systems"))

    def test_index_must_match_exact_editorial_snapshot(self):
        self.assertTrue(can_approve_indexed(indexed(), normalize_event(event())))
        for wrong in [
            indexed(visibility="private"),
            indexed(publication_status="withdrawn"),
            indexed(object_type="page"),
            indexed(canonical_url="https://sustainablecatalyst.com/other/"),
            indexed(metadata={**indexed()["metadata"], "publication_fingerprint": "b" * 64}),
            indexed(metadata={**indexed()["metadata"], "wordpress_password_protected": True}),
        ]:
            with self.subTest(wrong=wrong):
                self.assertFalse(can_approve_indexed(wrong, normalize_event(event())))

    def test_higher_withdrawal_prevents_old_replay(self):
        cursor = FakeCursor(None, indexed())
        conn = FakeConn(cursor)
        with patch("app.publication_state_sync.get_pool", return_value=FakePool(conn)):
            accepted = apply_publication_event(event(sequence=1))
            self.assertTrue(accepted["approved"])
            self.assertTrue(conn.committed)
            withdrawn = apply_publication_event(event(action="withdraw", sequence=2))
            self.assertFalse(withdrawn["approved"])
            self.assertFalse(cursor.ledger["approved"])
            replay = apply_publication_event(event(sequence=1))
            self.assertEqual(replay["state"], "superseded")
            self.assertFalse(cursor.ledger["approved"])
            same = apply_publication_event(event(action="withdraw", sequence=2))
            self.assertEqual(same["state"], "replayed")
            self.assertFalse(cursor.ledger["approved"])

    def test_conflicting_same_sequence_is_denied(self):
        cursor = FakeCursor({
            "sequence": 5,
            "event_digest": event_digest(normalize_event(event(action="withdraw", sequence=5))),
            "approved": False,
        }, indexed())
        with patch("app.publication_state_sync.get_pool", return_value=FakePool(FakeConn(cursor))):
            with self.assertRaisesRegex(PublicationStateError, "conflicting-sequence"):
                apply_publication_event(event(sequence=5))

    def test_missing_index_denies_approval_but_allows_tombstone(self):
        cursor = FakeCursor(None, None)
        with patch("app.publication_state_sync.get_pool", return_value=FakePool(FakeConn(cursor))):
            with self.assertRaisesRegex(PublicationStateError, "index-not-current"):
                apply_publication_event(event(sequence=1))
            accepted = apply_publication_event(event(action="withdraw", sequence=2))
            self.assertEqual(accepted["state"], "accepted")
            self.assertFalse(accepted["approved"])

    def test_read_side_requires_unexpired_matching_signed_state(self):
        sql, values = _candidate_query(field="", terms=[], matched_only=False)
        self.assertIn("JOIN publication_discovery_states d", sql)
        self.assertIn("d.approved IS TRUE", sql)
        self.assertIn("d.expires_at > now()", sql)
        self.assertIn("d.fingerprint = r.metadata->>'publication_fingerprint'", sql)
        self.assertIn("d.canonical_url = r.canonical_url", sql)
        self.assertIn("d.map_key = r.metadata->>'publication_map_key'", sql)


if __name__ == "__main__":
    unittest.main()
