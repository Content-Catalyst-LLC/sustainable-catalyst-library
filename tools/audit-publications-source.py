#!/usr/bin/env python3
"""Audit anonymous HTML and sitemap captures without bootstrapping WordPress."""

import argparse
from collections import Counter
from html.parser import HTMLParser
import json
from pathlib import Path
import subprocess
from urllib.parse import urljoin, urlsplit
from xml.etree import ElementTree


class Page(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.links = []
        self.meta = []
        self.canonical = []
        self.data = []
        self.scripts = []
        self.images = []
        self.ids = Counter()
        self.directory = False
        self.invalid_json_scripts = 0
        self.capture = None
        self.feed(html)
        self.close()

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        classes = (attrs.get("class") or "").split()
        self.directory |= "data-sc-publications-directory" in attrs
        if attrs.get("id"):
            self.ids[attrs["id"]] += 1
        if tag == "a" and attrs.get("href"):
            self.links.append(attrs["href"])
        if tag == "meta" and attrs.get("content") is not None:
            self.meta.append((attrs.get("name") or attrs.get("property"), attrs["content"]))
        if tag == "link" and "canonical" in (attrs.get("rel") or "").lower().split():
            self.canonical.append(attrs.get("href"))
        if tag == "img":
            self.images.append(attrs)
        if tag == "script":
            self.scripts.append(attrs)
            if "sc-field-spotlight__data" in classes or attrs.get("type") == "application/ld+json":
                self.capture = (attrs, [])

    def handle_data(self, data):
        if self.capture:
            self.capture[1].append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            attrs, chunks = self.capture
            raw = "".join(chunks)
            try:
                self.data.append((attrs, len(raw.encode()), json.loads(raw)))
            except json.JSONDecodeError:
                self.invalid_json_scripts += 1
            self.capture = None


def inventory(root):
    path = root / "sustainable-catalyst-library/includes/data"
    code = '''define("ABSPATH", __DIR__);
    echo json_encode([require $argv[1], require $argv[2]], JSON_THROW_ON_ERROR);'''
    return json.loads(subprocess.check_output([
        "php", "-r", code, "--", str(path / "field-spotlight-fields-v434.php"),
        str(path / "publications-article-map-registry-v431.php"),
    ]))


def locations(path):
    return {node.text for node in ElementTree.parse(path).getroot().findall("{*}url/{*}loc")
            if node.text}


def summarize(path, base):
    raw = path.read_bytes()
    page = Page(raw.decode("utf-8", errors="replace"))
    structured = [payload for attrs, _, payload in page.data
                  if attrs.get("type") == "application/ld+json"]
    schema = [node.get("@type") for graph in structured if isinstance(graph, dict)
              for node in graph.get("@graph", [graph]) if isinstance(node, dict)]
    links = {urljoin(base, link) for link in page.links}
    header_file = path.with_suffix(".headers")
    headers = header_file.read_text(errors="replace").splitlines() if header_file.is_file() else []
    result = {
        "http_chain": [line for line in headers if line.lower().startswith(("http/", "location:", "x-robots-tag:"))],
        "invalid_json_scripts": page.invalid_json_scripts,
        "html_bytes": len(raw),
        "canonical": page.canonical,
        "meta": [(key, value) for key, value in page.meta if key in {
            "robots", "description", "og:title", "og:description", "og:type", "og:url",
        }],
        "schema_types": schema,
        "script_tags": len(page.scripts),
        "external_script_tags": sum(bool(attrs.get("src")) for attrs in page.scripts),
        "image_tags": len(page.images),
        "eager_image_tags": sum(attrs.get("loading") == "eager" for attrs in page.images),
        "duplicate_ids": sorted(key for key, count in page.ids.items() if count > 1),
        "links_to_publications": urljoin(base, "/publications/") in links,
    }
    return page, links, result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("capture", type=Path)
    parser.add_argument("--origin", required=True)
    args = parser.parse_args()
    base = urljoin(args.origin, "/publications/")
    fields, registry = inventory(Path(__file__).resolve().parents[1])
    hub, direct, stats = summarize(args.capture / "publications.body", base)
    sitemap_files = sorted(args.capture.glob("*sitemap*.xml.body"))
    sitemap_urls = set().union(*(locations(path) for path in sitemap_files)) if sitemap_files else set()
    live_fields = {
        payload["field"]["key"]: payload["field"] for attrs, _, payload in hub.data
        if "sc-field-spotlight__data" in (attrs.get("class") or "").split()
    }
    live_panels = {panel["key"]: panel for field in live_fields.values() for panel in field["panels"]}
    candidates = []
    for key, record in registry.items():
        url = urljoin(base, record["url"])
        panel = live_panels.get(key)
        curated = []
        for article in (panel or {}).get("articles", []):
            if article.get("url"):
                href = urljoin(base, article["url"])
                curated.append({"title": article.get("title"), "url": href,
                                "anchor_on_hub": href in direct, "in_captured_sitemaps": href in sitemap_urls})
        candidates.append({"key": key, **record, "url": url, "in_hub_model": panel is not None,
                           "anchor_on_hub": url in direct, "in_captured_sitemaps": url in sitemap_urls,
                           "curated": curated})
    articles = {item["url"] for row in candidates for item in row["curated"]}
    stats.update({
        "directory_markup": hub.directory,
        "in_captured_sitemaps": base in sitemap_urls,
        "sitemap_files": [path.name for path in sitemap_files],
        "registry_fields": len(fields),
        "registry_maps": len(candidates),
        "hub_model_fields": len(live_fields),
        "hub_model_panels": len(live_panels),
        "hub_model_panel_keys_outside_registry": sorted(live_panels.keys() - registry.keys()),
        "field_model_json_bytes": sum(size for attrs, size, _ in hub.data
                                      if "sc-field-spotlight__data" in (attrs.get("class") or "").split()),
        "map_anchors": sum(row["anchor_on_hub"] for row in candidates),
        "map_sitemap_urls": sum(row["in_captured_sitemaps"] for row in candidates),
        "panel_selection_urls": sum("sc_publication_panel=" in urlsplit(url).query for url in direct),
        "curated_links_total": sum(len(row["curated"]) for row in candidates),
        "unique_curated_urls": len(articles),
        "curated_anchors": len(articles & direct),
        "curated_sitemap_urls": len(articles & sitemap_urls),
    })
    sample_paths = {"panel": "", "panel-invalid": "", "hub-page2": "/publications/page/2/",
                    "field": "/category/global-governance/", "field-page2": "/category/global-governance/page/2/",
                    "map": "/international-law/", "article": "/jus-cogens-erga-omnes-and-peremptory-norms-in-international-law/",
                    "research": "/research-frameworks-and-analytical-models/", "library": "/knowledge-libraries/"}
    samples = {name: summarize(args.capture / f"{name}.body", urljoin(base, path))[2]
               for name, path in sample_paths.items() if (args.capture / f"{name}.body").is_file()}
    robots_file = args.capture / "robots.txt.body"
    robots = robots_file.read_text() if robots_file.is_file() else None
    print(json.dumps({"hub": stats, "fields": fields, "maps": candidates, "samples": samples, "robots": robots},
                     indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
