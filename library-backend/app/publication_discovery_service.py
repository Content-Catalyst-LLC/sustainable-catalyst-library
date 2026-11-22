"""Read-only, opt-in public editorial discovery candidates for Publications.

This module does not publish, index, verify WordPress routes, or replace the
WordPress editorial state. It provides deterministic candidates from the
existing Library catalog, subject to explicit WordPress visibility evidence.
"""

from __future__ import annotations

from datetime import date, datetime
from typing import Any
from urllib.parse import urlsplit

from .db import get_pool

CONTRACT = "sc-publications-discovery-candidates/0.1"
MAX_SCAN = 200
MAX_RESULTS = 24
ALLOWED_TYPES = frozenset({"post", "article", "publication"})
ALLOWED_HOSTS = frozenset({"sustainablecatalyst.com", "www.sustainablecatalyst.com"})


def canonical_publication_url(value: Any) -> str | None:
    if not isinstance(value, str) or not value:
        return None
    parsed = urlsplit(value.strip())
    if (
        parsed.scheme != "https"
        or parsed.hostname not in ALLOWED_HOSTS
        or parsed.port is not None
        or parsed.username is not None
        or parsed.password is not None
        or parsed.query
        or parsed.fragment
        or not parsed.path.startswith("/")
        or parsed.path.startswith(("/wp-admin", "/wp-login", "/preview"))
        or any(character.isspace() for character in parsed.path)
    ):
        return None
    return parsed.geturl()


def _terms(values: Any) -> set[str]:
    return {
        str(item).strip().casefold()
        for item in values
        if isinstance(item, str) and item.strip()
    } if isinstance(values, (list, tuple)) else set()


def _published_date(value: Any) -> str:
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    return str(value or "")[:32]


def public_candidate(row: dict[str, Any]) -> dict[str, Any] | None:
    """Apply both Library and editorial-public gates; fail closed on absent metadata."""
    metadata = row.get("metadata")
    if not isinstance(metadata, dict):
        return None
    if (
        row.get("visibility") != "public"
        or row.get("publication_status") != "published"
        or row.get("object_type") not in ALLOWED_TYPES
        or not str(row.get("record_id") or "").startswith("wordpress:")
        or metadata.get("publication_discovery_approved") is not True
        or metadata.get("wordpress_public_verified") is not True
        or metadata.get("wordpress_password_protected") is not False
    ):
        return None
    url = canonical_publication_url(row.get("canonical_url"))
    title = str(row.get("title") or "").strip()
    if not url or not title:
        return None
    return {
        "record_id": str(row["record_id"]),
        "title": title,
        "url": url,
        "field": str(metadata.get("publication_field") or "").strip(),
        "topics": sorted(_terms(row.get("topics"))),
        "tags": sorted(_terms(row.get("tags"))),
        "published_at": _published_date(row.get("published_at")),
        "updated_at": _published_date(row.get("source_updated_at")),
        "kind": str(row["object_type"]),
    }


def build_discovery(
    rows: list[dict[str, Any]],
    *,
    field: str = "",
    topic: str = "",
    limit: int = 12,
) -> dict[str, Any]:
    """Rank verified candidates by topic affinity, then stable publication identity.

    Suggestions do not change WordPress editorial choices. The WordPress
    renderer must recheck current post visibility and canonical permalink.
    """
    limit = max(1, min(int(limit), MAX_RESULTS))
    field = str(field or "").strip().casefold()
    topic = str(topic or "").strip().casefold()
    candidates: list[dict[str, Any]] = []
    seen: set[str] = set()
    for row in rows[:MAX_SCAN]:
        candidate = public_candidate(row)
        if not candidate:
            continue
        if field and candidate["field"].casefold() != field:
            continue
        if candidate["url"] in seen:
            continue
        seen.add(candidate["url"])
        terms = set(candidate["topics"]) | set(candidate["tags"])
        candidate["topic_match"] = bool(topic and topic in terms)
        candidate["discovery_reason"] = "topic-overlap" if candidate["topic_match"] else "recent-publication"
        candidates.append(candidate)
    candidates.sort(
        key=lambda item: (
            not item["topic_match"],
            -(int(item["updated_at"][:4]) if item["updated_at"][:4].isdigit() else 0),
            item["title"].casefold(),
            item["url"],
        )
    )
    results = []
    for item in candidates[:limit]:
        # Never expose raw catalog metadata, visibility predicates, or source bodies.
        results.append(item)
    return {
        "schema": CONTRACT,
        "state": "candidates-only",
        "editorial_reverification_required": True,
        "auto_publish": False,
        "seo_authority": "wordpress-rank-math",
        "field": field,
        "topic": topic,
        "limit": limit,
        "count": len(results),
        "results": results,
    }


def discover_publications(*, field: str = "", topic: str = "", limit: int = 12) -> dict[str, Any]:
    """Single bounded SELECT from existing PostgreSQL catalog; no schema writes."""
    limit = max(1, min(int(limit), MAX_RESULTS))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.record_id, r.object_type, r.title, r.canonical_url,
                   r.visibility, r.publication_status, r.published_at,
                   r.source_updated_at, r.topics, r.tags, r.metadata
            FROM library_records r
            WHERE r.visibility = 'public'
              AND r.publication_status = 'published'
              AND r.object_type IN ('post', 'article', 'publication')
              AND r.record_id LIKE 'wordpress:%'
              AND r.metadata->>'publication_discovery_approved' = 'true'
              AND r.metadata->>'wordpress_public_verified' = 'true'
              AND r.metadata->>'wordpress_password_protected' = 'false'
            ORDER BY r.source_updated_at DESC NULLS LAST, r.record_id
            LIMIT %s
            """,
            (MAX_SCAN,),
        )
        rows = list(cur.fetchall())
    return build_discovery(rows, field=field, topic=topic, limit=limit)
