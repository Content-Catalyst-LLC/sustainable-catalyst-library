"""Read-only, interest-driven discovery candidates for public editorial Publications.

The catalog supplies candidates, not publishing authority. WordPress must
revalidate live editorial state, passwords, and canonical permalinks before
rendering any public HTML links. Nothing in this module records a visitor.
"""

from __future__ import annotations

from datetime import date, datetime
import re
from typing import Any
from urllib.parse import urlsplit

from .db import get_pool

CONTRACT = "sc-publications-discovery-candidates/0.2"
MAX_SCAN = 200
MAX_RESULTS = 24
MAX_INTERESTS = 5
ALLOWED_TYPES = frozenset({"post", "article", "publication"})
ALLOWED_HOSTS = frozenset({"sustainablecatalyst.com", "www.sustainablecatalyst.com"})
STOPWORDS = frozenset({"a", "an", "and", "in", "of", "on", "or", "the", "to", "with"})


def canonical_publication_url(value: Any) -> str | None:
    if not isinstance(value, str) or not value:
        return None
    try:
        parsed = urlsplit(value.strip())
        safe_host = parsed.hostname in ALLOWED_HOSTS and parsed.port is None
    except ValueError:
        return None
    if (
        parsed.scheme != "https"
        or not safe_host
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
    """Two-stage publish gate: Library status plus explicit editorial approval."""
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
        # A source-declared map identity, not a link. WordPress must resolve
        # its registry target to a current public canonical WP object.
        "article_map_key": str(metadata.get("publication_map_key") or "").strip()
            if re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", str(metadata.get("publication_map_key") or ""))
            else "",
        "topics": sorted(_terms(row.get("topics"))),
        "tags": sorted(_terms(row.get("tags"))),
        "published_at": _published_date(row.get("published_at")),
        "updated_at": _published_date(row.get("source_updated_at")),
        "kind": str(row["object_type"]),
    }


def _tokens(value: str) -> list[str]:
    """Deterministic lexical intent, not generated or inferred visitor profiling."""
    raw = re.findall(r"[a-z0-9]+", str(value or "").casefold()[:120])
    return [token for token in dict.fromkeys(raw) if token not in STOPWORDS][:8]


def _interest_terms(value: str) -> list[str]:
    """Comma-separated explicit interests from an opt-in interface, never tracked."""
    values = [part.strip().casefold()[:80] for part in str(value or "")[:400].split(",")]
    return list(dict.fromkeys(item for item in values if item))[:MAX_INTERESTS]


def _update_order(item: dict[str, Any]) -> int:
    return int("".join(c for c in item["updated_at"][:19] if c.isdigit()).ljust(14, "0")[:14])


def build_discovery(
    rows: list[dict[str, Any]],
    *,
    field: str = "",
    topic: str = "",
    query: str = "",
    interests: str = "",
    limit: int = 12,
) -> dict[str, Any]:
    """Rank by explicit interests, field, and search terms; then recent updates.

    This is an editorial candidate set, *not* a verified WordPress public
    directory. No user IDs, cookies, searches, or interests are persisted.
    """
    limit = max(1, min(int(limit), MAX_RESULTS))
    field = str(field or "").strip().casefold()
    topic = str(topic or "").strip().casefold()
    query_tokens = _tokens(query)
    chosen_interests = _interest_terms(interests)
    candidates: list[dict[str, Any]] = []
    seen: set[str] = set()

    for row in rows[:MAX_SCAN]:
        candidate = public_candidate(row)
        if candidate is None:
            continue
        if field and candidate["field"].casefold() != field:
            continue
        if candidate["url"] in seen:
            continue
        seen.add(candidate["url"])

        title = candidate["title"].casefold()
        field_text = candidate["field"].casefold()
        topics = set(candidate["topics"])
        tags = set(candidate["tags"])
        terms = topics | tags
        haystack = " ".join([title, field_text, *sorted(terms)])
        search_hits = sum(token in haystack for token in query_tokens)
        if query_tokens and search_hits == 0:
            continue
        topic_match = bool(topic and (topic in terms or topic in title))
        interest_hits = sum(
            item in terms or item in title or item == field_text for item in chosen_interests
        )
        score = (
            5 * interest_hits
            + 4 * search_hits
            + 3 * int(topic_match)
            + 2 * int(bool(query_tokens and all(token in title for token in query_tokens)))
        )
        reason = (
            "selected-interest" if interest_hits
            else "search-match" if search_hits
            else "related-topic" if topic_match
            else "recent-publication"
        )
        candidate["discovery_score"] = score
        candidate["discovery_reason"] = reason
        candidates.append(candidate)

    candidates.sort(
        key=lambda item: (
            -item["discovery_score"],
            -_update_order(item),
            item["title"].casefold(),
            item["url"],
        )
    )
    results = candidates[:limit]
    return {
        "schema": CONTRACT,
        "state": "candidates-only",
        "editorial_reverification_required": True,
        "auto_publish": False,
        "visitor_tracking": False,
        "seo_authority": "wordpress-rank-math",
        "field": field,
        "topic": topic,
        "query": " ".join(query_tokens),
        "interests": chosen_interests,
        "limit": limit,
        "count": len(results),
        "scan_ceiling": MAX_SCAN,
        "results": results,
    }


def discover_publications(
    *,
    field: str = "",
    topic: str = "",
    query: str = "",
    interests: str = "",
    limit: int = 12,
) -> dict[str, Any]:
    """One bounded SELECT from existing Library catalog; no schema writes."""
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
    return build_discovery(rows, field=field, topic=topic, query=query, interests=interests, limit=limit)
