#!/usr/bin/env python3
"""SC-PUB-SEO-03B2 read-only, anonymous staging smoke test.

The test intentionally has NO authentication, cookies, database access,
remote writes or production fallback. It cannot certify the signed event
lifecycle; that must be verified separately in a controlled staging clone.

Usage:
  python3 tools/publications-03b2-staging-preflight.py \
    --wp-base https://STAGING-WP-HOST \
    --api-base https://STAGING-API-HOST \
    --report /tmp/publications-03b2-smoke.json
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
from html.parser import HTMLParser
import json
from pathlib import Path
import sys
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlsplit
from urllib.request import (
    HTTPRedirectHandler,
    OpenerDirector,
    Request,
    build_opener,
)

TIMEOUT = 8
MAX_BYTES = 2_800_000


def staging_base(value: str) -> str:
    parsed = urlsplit(value.strip())
    host = (parsed.hostname or "").lower()
    # Reject documentation placeholders before any network request. These
    # often include the word "staging" and otherwise pass the host heuristic.
    placeholder_tokens = ("your-", "replace-", "placeholder", "staging-host", "stage-host")
    if any(token in host for token in placeholder_tokens) or any(ch in value for ch in "<>{}"):
        raise ValueError("placeholder host: provide an actual isolated staging address")
    if parsed.scheme not in {"https", "http"} or not host:
        raise ValueError("expected absolute HTTP(S) staging URL")
    if parsed.username or parsed.password or parsed.query or parsed.fragment:
        raise ValueError("staging URL must not contain credentials/query/fragment")
    if parsed.path not in {"", "/"}:
        raise ValueError("staging base URL must have no path")
    # Explicit, conservative acceptance. Never substitute a live hostname.
    safe = (
        host == "localhost"
        or host in {"127.0.0.1", "::1"}
        or host.endswith(".test")
        or host.endswith(".localhost")
        or "staging" in host
        or host.startswith("stage.")
        or host.startswith("stg.")
    )
    if not safe:
        raise ValueError("host does not look like an isolated staging origin")
    if parsed.scheme != "https" and host not in {"localhost", "127.0.0.1", "::1"}:
        raise ValueError("non-local staging requires HTTPS")
    return parsed.geturl().rstrip("/") + "/"


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(opener: OpenerDirector, url: str) -> dict:
    req = Request(
        url,
        headers={
            "User-Agent": "SC-PUB-SEO-03B2-staging-preflight/1.0",
            "Accept": "text/html, application/json",
        },
        method="GET",
    )
    try:
        with opener.open(req, timeout=TIMEOUT) as response:
            body = response.read(MAX_BYTES + 1)
            status = response.status
            headers = response.headers
    except HTTPError as exc:
        # Preserve status and inspect bounded error payload without ever
        # publishing its contents to the JSON evidence report.
        error_body = exc.read(4096)
        return {
            "url": url,
            "status": exc.code,
            "location": exc.headers.get("Location", ""),
            "bytes": len(error_body),
            "truncated": False,
            "content": error_body.decode("utf-8", errors="replace"),
            "robots_header": exc.headers.get("X-Robots-Tag", ""),
        }
    except (URLError, OSError) as exc:
        return {
            "url": url,
            "status": "connection-failed",
            "error": exc.__class__.__name__,
            "bytes": 0,
            "truncated": False,
            "content": "",
            "robots_header": "",
        }
    return {
        "url": url,
        "status": status,
        "location": headers.get("Location", ""),
        "robots_header": headers.get("X-Robots-Tag", ""),
        "bytes": min(len(body), MAX_BYTES),
        "truncated": len(body) > MAX_BYTES,
        "content": body[:MAX_BYTES].decode("utf-8", errors="replace"),
    }


class LinkReader(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links: list[str] = []
        self.canonicals: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]):
        attributes = dict(attrs)
        if tag == "a" and attributes.get("href"):
            self.links.append(attributes["href"] or "")
        if tag == "link" and "canonical" in (attributes.get("rel") or "").lower().split():
            self.canonicals.append(attributes.get("href") or "")


def preflight(wp_base: str, api_base: str) -> dict:
    opener = build_opener(NoRedirect())
    wp_url = urljoin(wp_base, "publications/")
    api_url = urljoin(api_base, "api/library/v1/publications/discovery")
    page = fetch(opener, wp_url)
    api = fetch(opener, api_url)
    reader = LinkReader()
    if page["status"] == 200:
        reader.feed(page["content"])
    hrefs = reader.links
    has_field_navigation = any(
        href.startswith("/category/")
        or "/category/" in href
        or "#sc-field-" in href
        for href in hrefs
    )
    page_pass = page["status"] == 200 and not page["truncated"] and bool(hrefs) and has_field_navigation
    # Without a signed server request, a staging endpoint may intentionally be
    # disabled (404) or enabled but reject anonymous access (401/403).
    api_text = api.get("content", "")
    api_pass = (api["status"] in {401, 403, 404}
        and '"results"' not in api_text and '"record_id"' not in api_text
        and '"canonical_url"' not in api_text)
    checks = {
        "wordpress_anonymous_html_and_field_navigation": page_pass,
        "discovery_api_rejects_anonymous_requests": api_pass,
        "no_page_redirect_or_fetch_failure": page["status"] == 200,
    }
    return {
        "schema": "sc-publications-staging-anonymous-preflight/1.0",
        "observed_utc": datetime.now(timezone.utc).isoformat(),
        "scope": "anonymous-read-only-no-production",
        "wordpress": {
            "url": wp_url,
            "status": page["status"],
            "location": page.get("location", ""),
            "bytes": page["bytes"],
            "truncated": page["truncated"],
            "anchor_links": len(hrefs),
            "category_or_field_navigation_found": has_field_navigation,
            "canonical_links": reader.canonicals[:3],
            "x_robots_tag": page["robots_header"],
        },
        "api": {
            "url": api_url,
            "status": api["status"],
            "location": api.get("location", ""),
        },
        "checks": checks,
        "passed": all(checks.values()),
        "not_covered": [
            "signed WordPress-to-Python manifest delivery",
            "publication password/status transition and tombstone replay",
            "PostgreSQL migrations and serial transaction behavior",
            "Article Map route and link integrity",
            "full-corpus semantic relevance and query performance",
            "staging cron cadence, backlog and lease throughput",
            "mobile accessibility, Core Web Vitals and SEO schema",
        ],
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--wp-base", required=True, help="isolated WordPress staging origin")
    parser.add_argument("--api-base", required=True, help="isolated Python API staging origin")
    parser.add_argument("--report", help="optional JSON output file")
    args = parser.parse_args(argv)
    try:
        wp = staging_base(args.wp_base)
        api = staging_base(args.api_base)
    except ValueError as exc:
        parser.error(str(exc))
    result = preflight(wp, api)
    serial = json.dumps(result, indent=2)
    print(serial)
    if args.report:
        Path(args.report).write_text(serial + "\n", encoding="utf-8")
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    sys.exit(main())
