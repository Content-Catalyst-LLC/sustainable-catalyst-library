from __future__ import annotations

from typing import Any
from urllib.parse import urlsplit
from uuid import uuid4

from psycopg.types.json import Jsonb

from .db import get_pool

LIBRARY_VERSION = "5.71.0"
BACKEND_VERSION = "2.82.0"
CONTRACT = "sc-library-python-research-state-service/1.0"
READINESS_CONTRACT = "sc-library-python-research-state-readiness/1.0"
PROJECT_CONTRACT = "sc-library-research-project/2.0"
REFERENCE_CONTRACT = "sc-library-project-reference/2.0"
BUNDLE_CONTRACT = "sc-library-source-bundle/2.0"
SAVED_SEARCH_CONTRACT = "sc-library-saved-search/2.0"
WATCHLIST_CONTRACT = "sc-library-watchlist/2.0"
QUEUE_CONTRACT = "sc-library-research-queue-item/2.0"
COLLECTION_CONTRACT = "sc-library-research-collection/2.0"
COLLECTION_ITEM_CONTRACT = "sc-library-research-collection-item/2.0"

MAX_PROJECTS_PER_OWNER = 50
MAX_REFERENCES_PER_PROJECT = 300
MAX_BUNDLES_PER_PROJECT = 60
MAX_REFERENCES_PER_BUNDLE = 120
MAX_SAVED_SEARCHES_PER_OWNER = 100
MAX_WATCHLISTS_PER_OWNER = 100
MAX_QUEUE_ITEMS_PER_OWNER = 250
MAX_COLLECTIONS_PER_OWNER = 100
MAX_COLLECTION_ITEMS = 500

PROJECT_STATUSES = {"active", "on_hold", "complete", "archived"}
VISIBILITIES = {"private", "shared", "public"}
REFERENCE_FAMILIES = {
    "source", "personal_library", "saved_search", "watchlist", "research_queue",
    "source_collection", "research_document", "course", "pathway", "external", "library_record",
}
REFERENCE_ROLES = {"reference", "background", "evidence", "method", "dataset", "context", "learning", "follow_up"}
BUNDLE_PURPOSES = {"working_set", "evidence", "review", "learning", "briefing", "handoff"}
SEARCH_SCOPES = {"all", "sustainable-catalyst", "libraries", "scholarly", "courses", "external"}
WATCH_KINDS = {"topic", "query", "provider", "source", "author", "institution", "collection", "course", "other"}
QUEUE_KINDS = {"question", "source", "search", "task", "course", "dataset", "document", "other"}
QUEUE_STATUSES = {"queued", "in_progress", "done", "dismissed"}
COLLECTION_KINDS = {"research", "reading", "evidence", "source", "dataset", "mixed"}


def guardrails() -> dict[str, bool]:
    return {
        "python_is_research_state_authority": True,
        "wordpress_php_is_research_state_authority": False,
        "postgresql_is_research_state_authority": True,
        "library_identity_owns_private_state": True,
        "wordpress_user_id_is_canonical_owner": False,
        "source_bundles_are_references_only": True,
        "source_bundle_binary_copying": False,
        "watchlists_are_passive_without_background_monitoring": True,
        "private_by_default": True,
        "saved_state_implies_evidence_quality": False,
        "queue_priority_implies_research_importance": False,
        "automatic_publication": False,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "persistence": "postgresql",
        "owner_identity": "library-identity",
        "wordpress": {"role": "presentation-and-api-client", "required": False, "authoritative": False},
        "objects": {
            "projects": PROJECT_CONTRACT,
            "project_references": REFERENCE_CONTRACT,
            "source_bundles": BUNDLE_CONTRACT,
            "saved_searches": SAVED_SEARCH_CONTRACT,
            "watchlists": WATCHLIST_CONTRACT,
            "research_queue": QUEUE_CONTRACT,
            "collections": COLLECTION_CONTRACT,
            "collection_items": COLLECTION_ITEM_CONTRACT,
        },
        "limits": {
            "projects_per_owner": MAX_PROJECTS_PER_OWNER,
            "references_per_project": MAX_REFERENCES_PER_PROJECT,
            "bundles_per_project": MAX_BUNDLES_PER_PROJECT,
            "references_per_bundle": MAX_REFERENCES_PER_BUNDLE,
            "saved_searches_per_owner": MAX_SAVED_SEARCHES_PER_OWNER,
            "watchlists_per_owner": MAX_WATCHLISTS_PER_OWNER,
            "queue_items_per_owner": MAX_QUEUE_ITEMS_PER_OWNER,
            "collections_per_owner": MAX_COLLECTIONS_PER_OWNER,
            "collection_items": MAX_COLLECTION_ITEMS,
        },
        "guardrails": guardrails(),
    }


def _clean(value: Any, limit: int) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _text(value: Any, limit: int) -> str:
    return str(value or "").strip()[:limit]


def _enum(value: Any, allowed: set[str], fallback: str) -> str:
    candidate = _clean(value, 80).lower().replace("-", "_")
    return candidate if candidate in allowed else fallback


def _id(prefix: str) -> str:
    return f"{prefix}:{uuid4()}"


def _valid_url(value: Any) -> str:
    text = _text(value, 2000)
    if not text:
        return ""
    try:
        parsed = urlsplit(text)
    except Exception:
        return ""
    return text if parsed.scheme in {"http", "https"} and parsed.netloc else ""


def _ensure_owner(cur, owner_identity_id: str) -> None:
    owner_identity_id = _clean(owner_identity_id, 120)
    cur.execute("SELECT identity_id FROM library_identities WHERE identity_id=%s AND status='active'", (owner_identity_id,))
    if cur.fetchone() is None:
        raise KeyError("owner-identity-not-found")


def _project_for_owner(cur, project_id: str, owner_identity_id: str) -> dict[str, Any]:
    cur.execute(
        "SELECT * FROM library_research_projects WHERE project_id=%s AND owner_identity_id=%s",
        (_clean(project_id, 120), _clean(owner_identity_id, 120)),
    )
    row = cur.fetchone()
    if row is None:
        raise KeyError("project-not-found")
    return dict(row)


def _count(cur, table: str, owner_identity_id: str) -> int:
    cur.execute(f"SELECT count(*) AS n FROM {table} WHERE owner_identity_id=%s", (_clean(owner_identity_id, 120),))
    return int(cur.fetchone()["n"])


def upsert_project(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    owner = _clean(payload.get("owner_identity_id"), 120)
    title = _clean(payload.get("title"), 180)
    if not owner or not title:
        raise ValueError("owner_identity_id-and-title-required")
    project_id = _clean(payload.get("project_id"), 120) or _id("project")
    status = _enum(payload.get("status"), PROJECT_STATUSES, "active")
    visibility = _enum(payload.get("visibility"), VISIBILITIES, "private")
    question = _text(payload.get("research_question"), 4000)
    description = _text(payload.get("description"), 8000)
    metadata = dict(payload.get("metadata") or {})
    metadata.update({"authority": "python-backend", "contract": PROJECT_CONTRACT})
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        cur.execute("SELECT owner_identity_id FROM library_research_projects WHERE project_id=%s", (project_id,))
        existing = cur.fetchone()
        if existing and existing["owner_identity_id"] != owner:
            raise PermissionError("project-owner-mismatch")
        if not existing and _count(cur, "library_research_projects", owner) >= MAX_PROJECTS_PER_OWNER:
            raise ValueError("project-limit-reached")
        urn = f"urn:sc:research-project:{project_id.split(':',1)[-1]}"
        cur.execute(
            """
            INSERT INTO library_research_projects(project_id,owner_identity_id,urn,title,research_question,description,status,visibility,metadata,updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,now())
            ON CONFLICT (project_id) DO UPDATE SET
              title=EXCLUDED.title,research_question=EXCLUDED.research_question,description=EXCLUDED.description,
              status=EXCLUDED.status,visibility=EXCLUDED.visibility,metadata=EXCLUDED.metadata,updated_at=now()
            RETURNING *
            """,
            (project_id, owner, urn, title, question, description, status, visibility, Jsonb(metadata)),
        )
        row = dict(cur.fetchone())
        conn.commit()
    return {"schema": PROJECT_CONTRACT, **row, "authority": "python-backend"}


def add_project_reference(project_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    family = _enum(payload.get("family"), REFERENCE_FAMILIES, "external")
    ref_id = _clean(payload.get("ref_id"), 360)
    if not owner or not ref_id:
        raise ValueError("owner_identity_id-and-ref_id-required")
    role = _enum(payload.get("role"), REFERENCE_ROLES, "reference")
    reference_id = _clean(payload.get("reference_id"), 120) or _id("project-ref")
    with get_pool().connection() as conn, conn.cursor() as cur:
        _project_for_owner(cur, project_id, owner)
        cur.execute("SELECT count(*) AS n FROM library_project_references WHERE project_id=%s", (project_id,))
        if int(cur.fetchone()["n"]) >= MAX_REFERENCES_PER_PROJECT:
            raise ValueError("project-reference-limit-reached")
        cur.execute(
            """
            INSERT INTO library_project_references(reference_id,project_id,family,ref_id,label,role,url,note,metadata)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT (project_id,family,ref_id) DO UPDATE SET
              label=EXCLUDED.label,role=EXCLUDED.role,url=EXCLUDED.url,note=EXCLUDED.note,metadata=EXCLUDED.metadata
            RETURNING *
            """,
            (reference_id, project_id, family, ref_id, _clean(payload.get("label"),220), role,
             _valid_url(payload.get("url")), _text(payload.get("note"),2000), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone())
        conn.commit()
    return {"schema": REFERENCE_CONTRACT, **row, "references_only": True, "authority": "python-backend"}


def create_source_bundle(project_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    title = _clean(payload.get("title"), 180)
    if not owner or not title:
        raise ValueError("owner_identity_id-and-title-required")
    link_ids = []
    for value in list(payload.get("reference_ids") or []):
        item = _clean(value, 120)
        if item and item not in link_ids:
            link_ids.append(item)
    link_ids = link_ids[:MAX_REFERENCES_PER_BUNDLE]
    bundle_id = _clean(payload.get("bundle_id"), 120) or _id("source-bundle")
    purpose = _enum(payload.get("purpose"), BUNDLE_PURPOSES, "working_set")
    with get_pool().connection() as conn, conn.cursor() as cur:
        _project_for_owner(cur, project_id, owner)
        cur.execute("SELECT count(*) AS n FROM library_source_bundles WHERE project_id=%s", (project_id,))
        if int(cur.fetchone()["n"]) >= MAX_BUNDLES_PER_PROJECT:
            raise ValueError("source-bundle-limit-reached")
        if link_ids:
            cur.execute("SELECT reference_id FROM library_project_references WHERE project_id=%s AND reference_id=ANY(%s)", (project_id, link_ids))
            valid = {r["reference_id"] for r in cur.fetchall()}
            link_ids = [x for x in link_ids if x in valid]
        urn = f"urn:sc:source-bundle:{bundle_id.split(':',1)[-1]}"
        cur.execute(
            """
            INSERT INTO library_source_bundles(bundle_id,project_id,urn,title,purpose,description,metadata,updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s,now())
            RETURNING *
            """,
            (bundle_id, project_id, urn, title, purpose, _text(payload.get("description"),2400), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone())
        for position, reference_id in enumerate(link_ids):
            cur.execute(
                "INSERT INTO library_source_bundle_members(bundle_id,reference_id,position) VALUES (%s,%s,%s) ON CONFLICT DO NOTHING",
                (bundle_id, reference_id, position),
            )
        conn.commit()
    return {"schema": BUNDLE_CONTRACT, **row, "reference_ids": link_ids, "references_only": True, "authority": "python-backend"}


def create_saved_search(payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    query = _clean(payload.get("query"), 1000)
    if not owner or not query:
        raise ValueError("owner_identity_id-and-query-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        if _count(cur, "library_saved_searches", owner) >= MAX_SAVED_SEARCHES_PER_OWNER:
            raise ValueError("saved-search-limit-reached")
        saved_search_id = _id("saved-search")
        cur.execute(
            """INSERT INTO library_saved_searches(saved_search_id,owner_identity_id,label,query,scope,filters,metadata)
               VALUES (%s,%s,%s,%s,%s,%s,%s) RETURNING *""",
            (saved_search_id, owner, _clean(payload.get("label"),180), query,
             _enum(payload.get("scope"), SEARCH_SCOPES, "all"), Jsonb(dict(payload.get("filters") or {})), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone()); conn.commit()
    return {"schema": SAVED_SEARCH_CONTRACT, **row, "authority": "python-backend"}


def create_watchlist(payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    label = _clean(payload.get("label"), 220)
    target = _clean(payload.get("target"), 1000)
    if not owner or not (label or target):
        raise ValueError("owner_identity_id-and-watch-target-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        if _count(cur, "library_watchlists", owner) >= MAX_WATCHLISTS_PER_OWNER:
            raise ValueError("watchlist-limit-reached")
        watchlist_id = _id("watchlist")
        cur.execute(
            """INSERT INTO library_watchlists(watchlist_id,owner_identity_id,kind,label,target,url,metadata)
               VALUES (%s,%s,%s,%s,%s,%s,%s) RETURNING *""",
            (watchlist_id, owner, _enum(payload.get("kind"), WATCH_KINDS, "other"), label, target,
             _valid_url(payload.get("url")), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone()); conn.commit()
    return {"schema": WATCHLIST_CONTRACT, **row, "passive": True, "background_monitoring": False, "authority": "python-backend"}


def enqueue_research_item(payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    label = _clean(payload.get("label"), 260)
    if not owner or not label:
        raise ValueError("owner_identity_id-and-label-required")
    project_id = _clean(payload.get("project_id"), 120)
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        if project_id:
            _project_for_owner(cur, project_id, owner)
        if _count(cur, "library_research_queue_items", owner) >= MAX_QUEUE_ITEMS_PER_OWNER:
            raise ValueError("research-queue-limit-reached")
        queue_item_id = _id("queue-item")
        cur.execute(
            """INSERT INTO library_research_queue_items(queue_item_id,owner_identity_id,project_id,kind,label,ref_id,url,note,status,priority,metadata)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) RETURNING *""",
            (queue_item_id, owner, project_id or None, _enum(payload.get("kind"), QUEUE_KINDS, "other"), label,
             _clean(payload.get("ref_id"),360), _valid_url(payload.get("url")), _text(payload.get("note"),3000),
             _enum(payload.get("status"), QUEUE_STATUSES, "queued"), max(0,min(100,int(payload.get("priority") or 0))),
             Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone()); conn.commit()
    return {"schema": QUEUE_CONTRACT, **row, "authority": "python-backend"}


def create_collection(payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    title = _clean(payload.get("title"), 180)
    if not owner or not title:
        raise ValueError("owner_identity_id-and-title-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        if _count(cur, "library_research_collections", owner) >= MAX_COLLECTIONS_PER_OWNER:
            raise ValueError("collection-limit-reached")
        collection_id = _id("collection")
        cur.execute(
            """INSERT INTO library_research_collections(collection_id,owner_identity_id,title,description,kind,visibility,metadata,updated_at)
               VALUES (%s,%s,%s,%s,%s,%s,%s,now()) RETURNING *""",
            (collection_id, owner, title, _text(payload.get("description"),4000), _enum(payload.get("kind"), COLLECTION_KINDS, "research"),
             _enum(payload.get("visibility"), VISIBILITIES, "private"), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone()); conn.commit()
    return {"schema": COLLECTION_CONTRACT, **row, "authority": "python-backend"}


def add_collection_item(collection_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    owner = _clean(payload.get("owner_identity_id"), 120)
    ref_id = _clean(payload.get("ref_id"), 360)
    if not owner or not ref_id:
        raise ValueError("owner_identity_id-and-ref_id-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT collection_id FROM library_research_collections WHERE collection_id=%s AND owner_identity_id=%s", (collection_id, owner))
        if cur.fetchone() is None:
            raise KeyError("collection-not-found")
        cur.execute("SELECT count(*) AS n FROM library_research_collection_items WHERE collection_id=%s", (collection_id,))
        if int(cur.fetchone()["n"]) >= MAX_COLLECTION_ITEMS:
            raise ValueError("collection-item-limit-reached")
        item_id = _id("collection-item")
        cur.execute(
            """INSERT INTO library_research_collection_items(item_id,collection_id,ref_type,ref_id,label,url,note,metadata)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
               ON CONFLICT (collection_id,ref_type,ref_id) DO UPDATE SET label=EXCLUDED.label,url=EXCLUDED.url,note=EXCLUDED.note,metadata=EXCLUDED.metadata
               RETURNING *""",
            (item_id, collection_id, _clean(payload.get("ref_type"),80) or "library-record", ref_id, _clean(payload.get("label"),220),
             _valid_url(payload.get("url")), _text(payload.get("note"),2000), Jsonb(dict(payload.get("metadata") or {}))),
        )
        row = dict(cur.fetchone()); conn.commit()
    return {"schema": COLLECTION_ITEM_CONTRACT, **row, "references_only": True, "authority": "python-backend"}


def owner_state(owner_identity_id: str) -> dict[str, Any]:
    owner = _clean(owner_identity_id, 120)
    with get_pool().connection() as conn, conn.cursor() as cur:
        _ensure_owner(cur, owner)
        result: dict[str, Any] = {}
        queries = {
            "projects": "SELECT * FROM library_research_projects WHERE owner_identity_id=%s ORDER BY updated_at DESC LIMIT 50",
            "saved_searches": "SELECT * FROM library_saved_searches WHERE owner_identity_id=%s ORDER BY updated_at DESC LIMIT 100",
            "watchlists": "SELECT * FROM library_watchlists WHERE owner_identity_id=%s ORDER BY updated_at DESC LIMIT 100",
            "research_queue": "SELECT * FROM library_research_queue_items WHERE owner_identity_id=%s ORDER BY created_at DESC LIMIT 250",
            "collections": "SELECT * FROM library_research_collections WHERE owner_identity_id=%s ORDER BY updated_at DESC LIMIT 100",
        }
        for key, sql in queries.items():
            cur.execute(sql, (owner,)); result[key] = [dict(r) for r in cur.fetchall()]
        project_ids = [p["project_id"] for p in result["projects"]]
        if project_ids:
            cur.execute("SELECT * FROM library_project_references WHERE project_id=ANY(%s) ORDER BY created_at DESC", (project_ids,))
            result["project_references"] = [dict(r) for r in cur.fetchall()]
            cur.execute("SELECT * FROM library_source_bundles WHERE project_id=ANY(%s) ORDER BY updated_at DESC", (project_ids,))
            result["source_bundles"] = [dict(r) for r in cur.fetchall()]
        else:
            result["project_references"] = []; result["source_bundles"] = []
    return {"schema": "sc-library-research-state-owner-snapshot/1.0", "owner_identity_id": owner, **result, "guardrails": guardrails()}


def readiness() -> dict[str, Any]:
    tables = {
        "projects": "library_research_projects",
        "project_references": "library_project_references",
        "source_bundles": "library_source_bundles",
        "saved_searches": "library_saved_searches",
        "watchlists": "library_watchlists",
        "research_queue": "library_research_queue_items",
        "collections": "library_research_collections",
        "collection_items": "library_research_collection_items",
    }
    counts = {key: 0 for key in tables}
    database = "unavailable"
    try:
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for key, table in tables.items():
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            database = "ready"
    except Exception:
        pass
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if database == "ready" else "degraded",
        "authority": "python-backend",
        "database": database,
        "owner_identity": "library-identity",
        "wordpress_required": False,
        "counts": counts,
        "guardrails": guardrails(),
    }
