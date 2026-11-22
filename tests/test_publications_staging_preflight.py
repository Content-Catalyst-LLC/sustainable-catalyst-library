"""Read-only, offline acceptance for SC-PUB-SEO-03B2 staging preflight."""
from __future__ import annotations

import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "tools/publications-03b2-staging-preflight.py"
spec = importlib.util.spec_from_file_location("publications03b2preflight", SCRIPT)
preflight = importlib.util.module_from_spec(spec)
spec.loader.exec_module(preflight)


class StagePreflightTests(unittest.TestCase):
    def test_production_and_unsafe_hosts_are_rejected(self):
        for url in (
            "https://sustainablecatalyst.com",
            "https://library-api.sustainablecatalyst.com",
            "https://research-api.sustainablecatalyst.com",
            "https://example.net",
            "https://staging.example.net/path",
            "https://staging.example.net?auth=secret",
            "http://staging.example.net",
            "javascript:alert(1)",
        ):
            with self.subTest(url=url):
                with self.assertRaises(ValueError):
                    preflight.staging_base(url)

    def test_explicit_staging_and_local_origins_are_allowed(self):
        self.assertEqual(
            preflight.staging_base("https://staging.example.test/"),
            "https://staging.example.test/",
        )
        self.assertEqual(
            preflight.staging_base("http://localhost:8800"),
            "http://localhost:8800/",
        )

    def test_plain_html_and_unauthenticated_api_are_inspected(self):
        page = {
            "status": 200,
            "truncated": False,
            "bytes": 400,
            "robots_header": "",
            "content": '<link rel="canonical" href="https://sustainablecatalyst.com/publications/">'
                       '<a href="/category/sustainable-systems/">Browse fields</a>',
        }
        api = {"status": 401, "content": "", "bytes": 0, "robots_header": ""}
        with patch.object(preflight, "fetch", side_effect=[page, api]):
            result = preflight.preflight(
                "https://staging.example.test/",
                "https://api-staging.example.test/",
            )
        self.assertTrue(result["passed"])
        self.assertEqual(result["wordpress"]["anchor_links"], 1)
        self.assertEqual(result["wordpress"]["canonical_links"][0],
                         "https://sustainablecatalyst.com/publications/")

    def test_public_api_or_missing_field_navigation_fails(self):
        page = {
            "status": 200,
            "truncated": False,
            "bytes": 200,
            "robots_header": "",
            "content": '<a href="/about/">Other</a>',
        }
        api = {"status": 200, "content": '{"results":[{"title":"Private"}]}',
               "bytes": 30, "robots_header": ""}
        with patch.object(preflight, "fetch", side_effect=[page, api]):
            result = preflight.preflight(
                "https://staging.example.test/",
                "https://api-staging.example.test/",
            )
        self.assertFalse(result["passed"])
        self.assertFalse(result["checks"]["discovery_api_rejects_anonymous_requests"])
        self.assertFalse(result["checks"]["wordpress_anonymous_html_and_field_navigation"])


if __name__ == "__main__":
    unittest.main()
