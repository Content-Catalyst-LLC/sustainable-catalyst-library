"""Behavioral acceptance for the gated Publications discovery candidates."""

import sys
from pathlib import Path
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "library-backend"))

from app.publication_discovery_service import (
    CONTRACT,
    MAX_RESULTS,
    build_discovery,
    canonical_publication_url,
    public_candidate,
    _candidate_query,
    discover_publications,
)


def record(**updates):
    row = {
        "record_id": "wordpress:1:post:123",
        "object_type": "post",
        "title": "Enhanced Rock Weathering",
        "canonical_url": "https://sustainablecatalyst.com/enhanced-rock-weathering/",
        "visibility": "public",
        "publication_status": "published",
        "published_at": "2026-11-15T10:00:00",
        "source_updated_at": "2026-11-21T14:00:00",
        "topics": ["Carbon Removal", "Agriculture"],
        "tags": ["Wexford"],
        "metadata": {
            "publication_discovery_approved": True,
            "wordpress_public_verified": True,
            "wordpress_password_protected": False,
            "publication_field": "sustainable-systems",
        },
    }
    row.update(updates)
    return row


class PublicationsDiscoveryTests(unittest.TestCase):
    def test_sql_filters_corpus_by_interest_before_limit(self):
        sql, params = _candidate_query(
            field="sustainable-systems",
            terms=["carbon removal", "rock weathering"],
            matched_only=True,
        )
        self.assertIn("FROM library_records r WHERE", sql)
        self.assertIn("lower(r.topics::text) LIKE %s", sql)
        self.assertIn("publication_manifest_schema", sql)
        self.assertIn("publication_discovery_approved", sql)
        self.assertIn("wordpress_password_protected", sql)
        self.assertIn("ORDER BY r.source_updated_at", sql)
        self.assertTrue(sql.rstrip().endswith("LIMIT %s"))
        self.assertIn("%carbon removal%", params)
        self.assertIn("%rock weathering%", params)
        self.assertEqual(params[0], "sustainable-systems")
        self.assertNotIn("carbon removal", sql)
        self.assertNotIn("rock weathering", sql)

    def test_sql_query_does_not_interpolate_search_attacks(self):
        sql, params = _candidate_query(
            field="", terms=["%'; DROP TABLE library_records; --"], matched_only=True,
        )
        self.assertNotIn("DROP TABLE", sql)
        self.assertNotIn("DROP TABLE", " ".join(str(p) for p in params))
        self.assertNotIn("ILIKE '%", sql)

    def test_discovery_queries_filtered_corpus_not_latest_only(self):
        class FakeCursor:
            def __init__(self):
                self.calls = []
            def execute(self, sql, params):
                self.calls.append((sql, params))
            def fetchall(self):
                return [record(metadata={
                    **record()["metadata"],
                    "publication_manifest_schema": "sc-publications-editorial-manifest/0.1",
                })]
            def __enter__(self):
                return self
            def __exit__(self, *args):
                return False
        class FakeConnection:
            def __init__(self, cursor):
                self._cursor = cursor
            def cursor(self):
                return self._cursor
            def __enter__(self):
                return self
            def __exit__(self, *args):
                return False
        class FakePool:
            def __init__(self, cursor):
                self._connection = FakeConnection(cursor)
            def connection(self):
                return self._connection
        cursor = FakeCursor()
        with patch("app.publication_discovery_service.get_pool", return_value=FakePool(cursor)):
            result = discover_publications(query="carbon removal", limit=8)
        self.assertEqual(result["results"][0]["title"], "Enhanced Rock Weathering")
        self.assertEqual(result["retrieval"]["scope"], "full-eligible-catalog-before-limit")
        self.assertEqual(len(cursor.calls), 1)
        self.assertIn("lower(r.title) LIKE %s", cursor.calls[0][0])

    def test_approved_public_record_is_candidate(self):
        result = public_candidate(record())
        self.assertEqual(result["url"], "https://sustainablecatalyst.com/enhanced-rock-weathering/")
        self.assertEqual(result["field"], "sustainable-systems")

    def test_fails_closed_on_missing_or_negative_editorial_verification(self):
        for key, value in [
            ("publication_discovery_approved", None),
            ("publication_discovery_approved", False),
            ("wordpress_public_verified", None),
            ("wordpress_public_verified", False),
            ("wordpress_password_protected", True),
            ("wordpress_password_protected", None),
        ]:
            metadata = dict(record()["metadata"])
            metadata.pop(key, None) if value is None else metadata.update({key: value})
            with self.subTest(key=key, value=value):
                self.assertIsNone(public_candidate(record(metadata=metadata)))

    def test_rejects_nonpublic_or_nonwordpress_or_unsupported_object(self):
        cases = [
            {"visibility": "private"},
            {"publication_status": "draft"},
            {"publication_status": "pending"},
            {"object_type": "dataset"},
            {"record_id": "research:1"},
            {"title": "   "},
        ]
        for fields in cases:
            with self.subTest(fields=fields):
                self.assertIsNone(public_candidate(record(**fields)))

    def test_rejects_noncanonical_or_nonwebsite_urls(self):
        bad = [
            "http://sustainablecatalyst.com/post/",
            "https://evil.test/post/",
            "https://sustainablecatalyst.com.evil.test/post/",
            "https://sustainablecatalyst.com/post/?preview=1",
            "https://sustainablecatalyst.com/post/#more",
            "https://sustainablecatalyst.com/wp-admin/",
            "javascript:alert(1)",
            "https://user:pass@sustainablecatalyst.com/post/",
            "https://sustainablecatalyst.com:443/post/",
        ]
        for url in bad:
            with self.subTest(url=url):
                self.assertIsNone(canonical_publication_url(url))

    def test_rank_dedupe_and_field_are_deterministic(self):
        base = record()
        duplicate = record(record_id="wordpress:1:post:555")
        other = record(
            record_id="wordpress:1:post:456",
            title="Solar Energy Basics",
            canonical_url="https://sustainablecatalyst.com/solar-energy-basics/",
            topics=["Energy"],
            source_updated_at="2026-11-22T01:00:00",
        )
        out = build_discovery([other, base, duplicate], field="sustainable-systems", topic="carbon removal")
        self.assertEqual(out["schema"], CONTRACT)
        self.assertEqual(out["count"], 2)
        self.assertEqual([r["title"] for r in out["results"]], [
            "Enhanced Rock Weathering", "Solar Energy Basics"
        ])
        self.assertEqual(out["results"][0]["discovery_reason"], "related-topic")
        self.assertTrue(out["editorial_reverification_required"])
        self.assertFalse(out["auto_publish"])


    def test_editor_assigned_map_key_is_identity_not_link(self):
        metadata = dict(record()["metadata"])
        metadata["publication_map_key"] = "international-law"
        candidate = public_candidate(record(metadata=metadata))
        self.assertEqual(candidate["article_map_key"], "international-law")
        self.assertNotIn("article_map_url", candidate)

        metadata["publication_map_key"] = "https://evil.test/redirect"
        untrusted = public_candidate(record(metadata=metadata))
        self.assertEqual(untrusted["article_map_key"], "")

    def test_opt_in_interests_prioritize_relevance(self):
        carbon = record()
        other = record(
            record_id="wordpress:1:post:789",
            title="A Brief History of Philosophy",
            canonical_url="https://sustainablecatalyst.com/history-of-philosophy/",
            topics=["Philosophy"],
            tags=[],
            source_updated_at="2026-11-22T18:00:00",
        )
        result = build_discovery([other, carbon], interests="carbon removal")
        self.assertEqual(result["results"][0]["title"], "Enhanced Rock Weathering")
        self.assertEqual(result["results"][0]["discovery_reason"], "selected-interest")
        self.assertFalse(result["visitor_tracking"])

    def test_search_query_filters_unrelated_records(self):
        carbon = record()
        philosophy = record(
            record_id="wordpress:1:post:789",
            title="A Brief History of Philosophy",
            canonical_url="https://sustainablecatalyst.com/history-of-philosophy/",
            topics=["Philosophy"],
            tags=[],
        )
        result = build_discovery([carbon, philosophy], query="carbon removal")
        self.assertEqual(result["count"], 1)
        self.assertEqual(result["results"][0]["title"], "Enhanced Rock Weathering")
        self.assertEqual(result["results"][0]["discovery_reason"], "search-match")

    def test_missing_editorial_metadata_never_included_from_interest(self):
        result = build_discovery([
            record(metadata={"publication_field": "sustainable-systems"}),
        ], query="rock weathering", interests="carbon removal")
        self.assertEqual(result["results"], [])

    def test_limit_is_bounded_and_no_metadata_leak(self):
        out = build_discovery([record()], limit=1000)
        self.assertEqual(out["limit"], MAX_RESULTS)
        self.assertNotIn("metadata", out["results"][0])
        self.assertNotIn("body_text", out["results"][0])

    def test_no_approval_yields_empty_collection(self):
        out = build_discovery([record(metadata={"publication_field": "sustainable-systems"})])
        self.assertEqual(out["count"], 0)
        self.assertEqual(out["results"], [])


if __name__ == "__main__":
    unittest.main()
