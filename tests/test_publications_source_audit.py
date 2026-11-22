"""Regression tests for the PUB-00 capture analysis."""

import importlib.util
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("publications_audit", ROOT / "tools/audit-publications-source.py")
AUDIT = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(AUDIT)


class SourceAuditTest(unittest.TestCase):
    def test_source_links_are_distinct_from_payload_destinations(self):
        page = AUDIT.Page("""
            <a href="?sc_publication_panel=common-law-precedent">Common law</a>
            <nav data-sc-publications-directory><a href="/international-law/">International law</a></nav>
            <script class="sc-field-spotlight__data" type="application/json">
              {"field": {"key": "global-governance", "panels": [{"url": "/common-law-precedent/"}]}}
            </script>
            <script type="application/ld+json">{invalid}</script>
            <link rel="canonical" href="https://example.org/publications/">
        """)
        self.assertEqual(page.links, ["?sc_publication_panel=common-law-precedent", "/international-law/"])
        self.assertEqual(len(page.data), 1)
        self.assertEqual(page.invalid_json_scripts, 1)
        self.assertTrue(page.directory)
        self.assertEqual(page.canonical, ["https://example.org/publications/"])

    def test_registry_matches_advertised_hierarchy(self):
        fields, maps = AUDIT.inventory(ROOT)
        self.assertEqual((len(fields), len(maps)), (14, 170))
        self.assertEqual({field["title"] for field in fields.values()}, {row["field"] for row in maps.values()})
        self.assertEqual(len({row["url"] for row in maps.values()}), 170)


if __name__ == "__main__":
    unittest.main()
