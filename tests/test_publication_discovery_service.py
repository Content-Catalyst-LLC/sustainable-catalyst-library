"""Behavioral acceptance for the gated Publications discovery candidates."""

import sys
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "library-backend"))

from app.publication_discovery_service import (
    CONTRACT,
    MAX_RESULTS,
    build_discovery,
    canonical_publication_url,
    public_candidate,
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
        self.assertEqual(out["results"][0]["discovery_reason"], "topic-overlap")
        self.assertTrue(out["editorial_reverification_required"])
        self.assertFalse(out["auto_publish"])

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
