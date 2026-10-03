from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
from typing import Any

from .advanced_discovery import (
    execute as execute_advanced_discovery,
    execute_project as execute_project_advanced_discovery,
    readiness as advanced_discovery_readiness,
)
from .db import get_pool
from .research_graph_navigation import readiness as research_graph_readiness, summary as research_graph_summary
from .research_state import readiness as research_state_readiness
from .saved_workspaces import project_context, save_collection, save_collection_item, workspace_for_owner

LIBRARY_VERSION = "6.7.0"
BACKEND_VERSION = "3.7.0"
CONTRACT = "sc-library-living-collections-research-projects/1.0"
READINESS_CONTRACT = "sc-library-living-collections-research-projects-readiness/1.0"
COLLECTION_CONTRACT = "sc-library-living-collection/1.0"
REFRESH_CONTRACT = "sc-library-living-collection-refresh/1.0"
APPLY_CONTRACT = "sc-library-living-collection-apply/1.0"
PROJECT_BRIEF_CONTRACT = "sc-library-living-research-project-brief/1.0"
LIVING_METADATA_KEY = "living_collection"
MAX_REFRESH_RESULTS = 100
MAX_APPLY = 100
MAX_GRAPH_REFERENCES = 8


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any, limit: int = 500) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _bounded_int(value: Any, default: int, minimum: int, maximum: int) -> int:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        parsed = default
    return max(minimum, min(maximum, parsed))


def _record_id(item: dict[str, Any]) -> str:
    return _clean(item.get("record_id") or item.get("id") or item.get("research_object_id"), 512)


def _record_title(item: dict[str, Any], record_id: str) -> str:
    for key in ("title", "name", "label", "display_title"):
        value = _clean(item.get(key), 220)
        if value:
            return value
    return record_id


def _record_type(item: dict[str, Any]) -> str:
    return _clean(item.get("object_type") or item.get("type") or item.get("record_type") or "library-record", 80)


def guardrails() -> dict[str, bool]:
    return {
        "living_collection_is_composition_layer": True,
        "research_state_service_remains_persistence_authority": True,
        "postgresql_remains_collection_membership_authority": True,
        "advanced_discovery_remains_retrieval_authority": True,
        "research_graph_remains_graph_authority": True,
        "refresh_is_read_only_preview": True,
        "refresh_automatically_mutates_membership": False,
        "apply_requires_explicit_selected_record_ids": True,
        "apply_requires_authenticated_library_session": True,
        "apply_requires_csrf": True,
        "collection_membership_implies_evidence_quality": False,
        "collection_membership_implies_truth": False,
        "project_membership_implies_research_truth": False,
        "project_reference_count_implies_importance": False,
        "graph_connectivity_implies_evidence_strength": False,
        "automatic_publication": False,
        "automatic_platform_core_promotion": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "living-collection-definition",
        "living-collection-refresh-preview",
        "explicit-refresh-apply",
        "project-research-brief",
        "project-collection-linkage",
        "graph-aware-project-context",
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "service_id": "living-research:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "persistence_authority": "python-research-state-service/postgresql",
        "retrieval_authority": "advanced-discovery",
        "graph_authority": "research-graph-navigation",
        "database_migration_required": False,
        "wordpress": {"role": "optional-adapter", "required": False, "authoritative": False},
        "resources": resources,
        "api": {
            "contract": "/api/library/v1/living-research",
            "readiness": "/api/library/v1/living-research/readiness",
            "project_brief": "/api/library/v1/living-research/projects/{project_id}/brief",
            "collection": "/api/library/v1/living-research/collections/{collection_id}",
            "create_collection": "/api/library/v1/living-research/collections",
            "refresh_collection": "/api/library/v1/living-research/collections/{collection_id}/refresh",
            "apply_collection_refresh": "/api/library/v1/living-research/collections/{collection_id}/apply",
        },
        "guardrails": guardrails(),
    }


def _collection_row(owner_identity_id: str, collection_id: str) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    owner = _clean(owner_identity_id, 120)
    collection_id = _clean(collection_id, 120)
    if not owner or not collection_id:
        raise ValueError("owner_identity_id-and-collection_id-required")
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            "SELECT * FROM library_research_collections WHERE collection_id=%s AND owner_identity_id=%s",
            (collection_id, owner),
        )
        row = cur.fetchone()
        if row is None:
            raise KeyError("collection-not-found")
        cur.execute(
            "SELECT * FROM library_research_collection_items WHERE collection_id=%s ORDER BY created_at ASC",
            (collection_id,),
        )
        items = [dict(x) for x in cur.fetchall()]
    return dict(row), items


def _living_config(collection: dict[str, Any]) -> dict[str, Any]:
    metadata = collection.get("metadata") if isinstance(collection.get("metadata"), dict) else {}
    cfg = metadata.get(LIVING_METADATA_KEY) if isinstance(metadata.get(LIVING_METADATA_KEY), dict) else {}
    if not cfg or not bool(cfg.get("enabled")):
        raise ValueError("collection-is-not-living")
    query = _clean(cfg.get("query"), 1000)
    if not query:
        raise ValueError("living-collection-query-missing")
    return {
        "enabled": True,
        "query": query,
        "mode": _clean(cfg.get("mode") or "hybrid", 20).lower(),
        "rerank": _clean(cfg.get("rerank") or "neural", 20).lower(),
        "cross_language": bool(cfg.get("cross_language", True)),
        "limit": _bounded_int(cfg.get("limit"), 50, 1, MAX_REFRESH_RESULTS),
        "filters": dict(cfg.get("filters") or {}),
        "project_id": _clean(cfg.get("project_id"), 120),
        "automatic_apply": False,
    }


def collection_state(owner_identity_id: str, collection_id: str) -> dict[str, Any]:
    collection, items = _collection_row(owner_identity_id, collection_id)
    cfg = _living_config(collection)
    basis = {
        "collection_id": collection_id,
        "updated_at": collection.get("updated_at"),
        "item_ids": [x.get("item_id") for x in items],
        "config": cfg,
    }
    return {
        "schema": COLLECTION_CONTRACT,
        "collection_state_id": "living-collection-state:" + _fp(basis)[:32],
        "collection_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "collection": collection,
        "living": cfg,
        "items": items,
        "item_count": len(items),
        "authority": "python-research-state-service",
        "database_migration_required": False,
        "guardrails": guardrails(),
    }


def create_living_collection(owner_identity_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    title = _clean(body.get("title"), 180)
    query = _clean(body.get("query"), 1000)
    if not title or not query:
        raise ValueError("title-and-query-required")
    project_id = _clean(body.get("project_id"), 120)
    if project_id:
        project_context(owner_identity_id, project_id)
    filters = dict(body.get("filters") or {})
    living = {
        "enabled": True,
        "schema": "sc-library-living-collection-definition/1.0",
        "query": query,
        "mode": _clean(body.get("mode") or "hybrid", 20).lower(),
        "rerank": _clean(body.get("rerank") or "neural", 20).lower(),
        "cross_language": bool(body.get("cross_language", True)),
        "limit": _bounded_int(body.get("limit"), 50, 1, MAX_REFRESH_RESULTS),
        "filters": filters,
        "project_id": project_id,
        "automatic_apply": False,
        "created_by_release": LIBRARY_VERSION,
    }
    metadata = dict(body.get("metadata") or {})
    metadata[LIVING_METADATA_KEY] = living
    created = save_collection(owner_identity_id, {
        "title": title,
        "description": str(body.get("description") or "")[:4000],
        "kind": body.get("kind") or "research",
        "visibility": body.get("visibility") or "private",
        "metadata": metadata,
    })
    return collection_state(owner_identity_id, str(created.get("collection_id") or ""))


def _discovery_payload(cfg: dict[str, Any], override: dict[str, Any] | None = None) -> dict[str, Any]:
    override = dict(override or {})
    payload: dict[str, Any] = {
        "q": cfg["query"],
        "mode": cfg["mode"],
        "rerank": cfg["rerank"],
        "cross_language": cfg["cross_language"],
        "limit": _bounded_int(override.get("limit"), cfg["limit"], 1, MAX_REFRESH_RESULTS),
        "offset": 0,
    }
    for key, value in cfg.get("filters", {}).items():
        if key in {"object_type", "source_key", "topic", "year_from", "year_to", "include_core"} and value not in (None, ""):
            payload[key] = value
    return payload


def refresh_collection(owner_identity_id: str, collection_id: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
    state = collection_state(owner_identity_id, collection_id)
    cfg = state["living"]
    discovery_payload = _discovery_payload(cfg, payload)
    project_id = cfg.get("project_id") or ""
    if project_id:
        project = project_context(owner_identity_id, project_id)
        result = execute_project_advanced_discovery(discovery_payload, project)
    else:
        result = execute_advanced_discovery(discovery_payload)

    existing_ids = {
        _clean(item.get("ref_id"), 512)
        for item in state["items"]
        if _clean(item.get("ref_type") or "library-record", 80) in {"library-record", "library_record"}
    }
    candidates: list[dict[str, Any]] = []
    existing_matches: list[dict[str, Any]] = []
    result_ids: set[str] = set()
    for item in result.get("results") or []:
        if not isinstance(item, dict):
            continue
        rid = _record_id(item)
        if not rid:
            continue
        result_ids.add(rid)
        source_obj = item.get("source") if isinstance(item.get("source"), dict) else {}
        normalized = {
            "record_id": rid,
            "title": _record_title(item, rid),
            "object_type": _record_type(item),
            "source_key": _clean(item.get("source_key") or source_obj.get("source_key"), 191),
            "discovery_signals": dict(item.get("discovery_signals") or {}),
        }
        if rid in existing_ids:
            existing_matches.append(normalized)
        else:
            candidates.append(normalized)
    not_in_refresh = sorted(x for x in existing_ids if x and x not in result_ids)
    basis = {
        "collection_id": collection_id,
        "collection_fingerprint": state["collection_fingerprint_sha256"],
        "discovery_plan": result.get("plan_id") or result.get("plan_fingerprint_sha256"),
        "result_ids": sorted(result_ids),
        "candidate_ids": [x["record_id"] for x in candidates],
    }
    fingerprint = _fp(basis)
    return {
        "schema": REFRESH_CONTRACT,
        "refresh_id": "living-collection-refresh:" + fingerprint[:32],
        "refresh_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "collection_id": collection_id,
        "project_id": project_id or None,
        "query": cfg["query"],
        "candidate_additions": candidates,
        "candidate_addition_count": len(candidates),
        "existing_matches": existing_matches,
        "existing_match_count": len(existing_matches),
        "not_in_current_refresh": not_in_refresh,
        "not_in_current_refresh_is_removal_instruction": False,
        "membership_mutated": False,
        "explicit_apply_required": True,
        "discovery": {
            "total": result.get("total"),
            "cross_language_expansion_state": result.get("cross_language_expansion_state"),
            "query_representations": result.get("query_representations") or [],
        },
        "guardrails": guardrails(),
    }


def apply_refresh(owner_identity_id: str, collection_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    selected = []
    seen: set[str] = set()
    for value in list((payload or {}).get("selected_record_ids") or [])[:MAX_APPLY]:
        rid = _clean(value, 512)
        if rid and rid not in seen:
            seen.add(rid)
            selected.append(rid)
    if not selected:
        raise ValueError("selected_record_ids-required")

    refresh = refresh_collection(owner_identity_id, collection_id, payload)
    by_id = {x["record_id"]: x for x in refresh["candidate_additions"]}
    invalid = [rid for rid in selected if rid not in by_id]
    if invalid:
        raise ValueError("selected-record-not-in-current-refresh:" + ",".join(invalid[:10]))

    saved = []
    for rid in selected:
        row = by_id[rid]
        saved.append(save_collection_item(owner_identity_id, collection_id, {
            "ref_type": "library-record",
            "ref_id": rid,
            "label": row.get("title") or rid,
            "metadata": {
                "saved_from": "living-collection-refresh",
                "refresh_fingerprint_sha256": refresh["refresh_fingerprint_sha256"],
                "object_type": row.get("object_type") or "library-record",
                "source_key": row.get("source_key") or "",
            },
        }))
    state = collection_state(owner_identity_id, collection_id)
    basis = {
        "collection_id": collection_id,
        "refresh": refresh["refresh_fingerprint_sha256"],
        "selected": selected,
        "saved_item_ids": [x.get("item_id") for x in saved],
    }
    return {
        "schema": APPLY_CONTRACT,
        "apply_id": "living-collection-apply:" + _fp(basis)[:32],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "collection_id": collection_id,
        "refresh_fingerprint_sha256": refresh["refresh_fingerprint_sha256"],
        "selected_record_ids": selected,
        "saved_count": len(saved),
        "saved_items": saved,
        "collection_state": state,
        "explicit_user_selection": True,
        "guardrails": guardrails(),
    }


def _owner_living_collections(owner_identity_id: str, *, project_id: str = "") -> list[dict[str, Any]]:
    workspace = workspace_for_owner(owner_identity_id)
    out = []
    for collection in workspace.get("collections") or []:
        metadata = collection.get("metadata") if isinstance(collection.get("metadata"), dict) else {}
        cfg = metadata.get(LIVING_METADATA_KEY) if isinstance(metadata.get(LIVING_METADATA_KEY), dict) else {}
        if not cfg or not bool(cfg.get("enabled")):
            continue
        if project_id and _clean(cfg.get("project_id"), 120) != project_id:
            continue
        out.append({
            "collection_id": collection.get("collection_id"),
            "title": collection.get("title"),
            "updated_at": collection.get("updated_at"),
            "query": cfg.get("query"),
            "project_id": cfg.get("project_id") or None,
            "automatic_apply": False,
        })
    return out


def project_brief(owner_identity_id: str, project_id: str, *, include_graph: bool = False) -> dict[str, Any]:
    context = project_context(owner_identity_id, project_id)
    project = dict(context.get("project") or {})
    refs = list(context.get("references") or [])
    bundles = list(context.get("source_bundles") or [])
    queue = list(context.get("research_queue") or [])
    living = _owner_living_collections(owner_identity_id, project_id=project_id)
    role_counts = Counter(_clean(x.get("role") or "reference", 80) for x in refs)
    family_counts = Counter(_clean(x.get("family") or "external", 80) for x in refs)
    queue_counts = Counter(_clean(x.get("status") or "queued", 80) for x in queue)

    graph_rows = []
    if include_graph:
        record_ids = []
        for ref in refs:
            if _clean(ref.get("family"), 80) != "library_record":
                continue
            rid = _clean(ref.get("ref_id"), 512)
            if rid and rid not in record_ids:
                record_ids.append(rid)
            if len(record_ids) >= MAX_GRAPH_REFERENCES:
                break
        for rid in record_ids:
            try:
                graph_rows.append(research_graph_summary(rid, depth=1, limit=120, include_core=True))
            except Exception as exc:
                graph_rows.append({"record_id": rid, "state": "unavailable", "error": str(exc)})

    basis = {
        "project_id": project_id,
        "updated_at": project.get("updated_at"),
        "reference_ids": [x.get("reference_id") for x in refs],
        "bundle_ids": [x.get("bundle_id") for x in bundles],
        "living_collection_ids": [x.get("collection_id") for x in living],
        "queue_ids": [x.get("queue_item_id") for x in queue],
    }
    return {
        "schema": PROJECT_BRIEF_CONTRACT,
        "brief_id": "living-project-brief:" + _fp(basis)[:32],
        "brief_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "project": project,
        "summary": {
            "reference_count": len(refs),
            "source_bundle_count": len(bundles),
            "research_queue_count": len(queue),
            "living_collection_count": len(living),
            "reference_roles": dict(sorted(role_counts.items())),
            "reference_families": dict(sorted(family_counts.items())),
            "queue_statuses": dict(sorted(queue_counts.items())),
        },
        "living_collections": living,
        "source_bundles": bundles,
        "research_queue": queue,
        "graph_context": graph_rows,
        "graph_context_requested": bool(include_graph),
        "authority": "python-research-state-service",
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    research = research_state_readiness()
    discovery = advanced_discovery_readiness()
    graph = research_graph_readiness()
    errors: list[str] = []
    if research.get("state") != "ready":
        errors.append("research-state-not-ready")
    discovery_ready = bool(discovery.get("ready", discovery.get("state") in {"ready", "degraded"}))
    if not discovery_ready:
        errors.append("advanced-discovery-not-ready")
    if graph.get("state") not in {"ready", "degraded"}:
        errors.append("research-graph-not-ready")
    degraded = discovery.get("state") == "degraded" or graph.get("state") == "degraded"
    readiness_state = "blocked" if errors else ("degraded" if degraded else "ready")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": readiness_state,
        "ready": not errors,
        "errors": errors,
        "database_migration_required": False,
        "wordpress_required": False,
        "research_state": research,
        "advanced_discovery": discovery,
        "research_graph": graph,
        "guardrails": guardrails(),
    }
