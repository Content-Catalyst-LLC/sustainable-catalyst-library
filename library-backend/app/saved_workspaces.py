from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .research_state import (
    readiness as research_state_readiness,
    owner_state,
    upsert_project,
    add_project_reference,
    create_saved_search,
    create_collection,
    add_collection_item,
)

LIBRARY_VERSION = "6.3.0"
BACKEND_VERSION = "3.3.0"
WEB_VERSION = "2.3.0"
SDK_VERSION = "1.3.0"

CONTRACT = "sc-library-research-projects-saved-workspaces/1.0"
READINESS_CONTRACT = "sc-library-research-projects-saved-workspaces-readiness/1.0"
WORKSPACE_CONTRACT = "sc-library-saved-workspace/1.0"
PROJECT_CONTEXT_CONTRACT = "sc-library-saved-workspace-project-context/1.0"
WORKING_SET_SAVE_CONTRACT = "sc-library-working-set-project-save/1.0"

MAX_WORKING_SET_SAVE = 100

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _clean(value: Any, limit: int) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]

def guardrails() -> dict[str, bool]:
    return {
        "saved_workspace_is_composition_layer": True,
        "saved_workspace_is_new_persistence_authority": False,
        "research_state_service_remains_authority": True,
        "postgresql_remains_saved_state_authority": True,
        "browser_working_set_remains_non_authoritative": True,
        "working_set_requires_explicit_save_to_persist": True,
        "workspace_mutations_require_library_session": True,
        "workspace_mutations_require_csrf": True,
        "workspace_owner_is_library_identity": True,
        "wordpress_required": False,
        "wordpress_is_saved_workspace_authority": False,
        "saving_record_implies_evidence_quality": False,
        "project_membership_implies_research_truth": False,
        "project_order_implies_research_importance": False,
        "automatic_publication": False,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str, Any]:
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "objects": ["projects", "project-references", "saved-searches", "collections"],
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "workspace_service_id": "saved-workspaces:" + _fp(basis)[:32],
        "workspace_service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-research-state-service",
        "persistence": "existing-postgresql-research-state",
        "database_migration_required": False,
        "web_routes": {
            "workspace": "/account?section=workspaces",
            "research": "/research",
        },
        "api": {
            "contract": "/api/library/v1/saved-workspaces",
            "readiness": "/api/library/v1/saved-workspaces/readiness",
            "owner_workspace": "/api/library/v1/workspaces",
            "project": "/api/library/v1/workspaces/projects/{project_id}",
            "project_write": "/api/library/v1/workspaces/projects",
            "working_set_save": "/api/library/v1/workspaces/projects/{project_id}/working-set",
            "saved_search_write": "/api/library/v1/workspaces/saved-searches",
            "collection_write": "/api/library/v1/workspaces/collections",
            "collection_item_write": "/api/library/v1/workspaces/collections/{collection_id}/items",
        },
        "session_model": {
            "authentication": "library-service-session-cookie",
            "owner_binding": "session.identity.identity_id",
            "mutation_csrf": "X-SC-CSRF-Token",
            "service_api_key_exposed_to_browser": False,
        },
        "working_set": {
            "browser_store": "localStorage",
            "authoritative": False,
            "persistent": False,
            "save_target": "research-project-references",
            "maximum_records_per_save": MAX_WORKING_SET_SAVE,
        },
        "next_release": "6.4.0",
        "next_release_name": "Project Detail, Source Bundles & Collections",
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    state = research_state_readiness()
    errors: list[str] = []
    if state.get("state") != "ready":
        errors.append("research-state-not-ready")
    if str(state.get("library_version") or "") != LIBRARY_VERSION:
        errors.append("research-state-library-version-mismatch")
    if str(state.get("backend_version") or "") != BACKEND_VERSION:
        errors.append("research-state-backend-version-mismatch")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready" if not errors else "blocked",
        "ready": not errors,
        "errors": errors,
        "authority": "python-research-state-service",
        "database_migration_required": False,
        "wordpress_required": False,
        "research_state": state,
        "guardrails": guardrails(),
    }

def workspace_for_owner(owner_identity_id: str) -> dict[str, Any]:
    snapshot = owner_state(owner_identity_id)
    projects = list(snapshot.get("projects") or [])
    project_references = list(snapshot.get("project_references") or [])
    source_bundles = list(snapshot.get("source_bundles") or [])
    saved_searches = list(snapshot.get("saved_searches") or [])
    collections = list(snapshot.get("collections") or [])
    queue = list(snapshot.get("research_queue") or [])
    watchlists = list(snapshot.get("watchlists") or [])
    basis = {
        "owner_identity_id": owner_identity_id,
        "project_ids": [x.get("project_id") for x in projects],
        "reference_ids": [x.get("reference_id") for x in project_references],
        "collection_ids": [x.get("collection_id") for x in collections],
        "saved_search_ids": [x.get("saved_search_id") for x in saved_searches],
    }
    return {
        "schema": WORKSPACE_CONTRACT,
        "workspace_id": "saved-workspace:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "owner_identity_id": owner_identity_id,
        "authority": "python-research-state-service",
        "summary": {
            "project_count": len(projects),
            "reference_count": len(project_references),
            "source_bundle_count": len(source_bundles),
            "saved_search_count": len(saved_searches),
            "collection_count": len(collections),
            "queue_count": len(queue),
            "watchlist_count": len(watchlists),
        },
        "projects": projects,
        "project_references": project_references,
        "source_bundles": source_bundles,
        "saved_searches": saved_searches,
        "collections": collections,
        "research_queue": queue,
        "watchlists": watchlists,
        "guardrails": guardrails(),
    }

def project_context(owner_identity_id: str, project_id: str) -> dict[str, Any]:
    workspace = workspace_for_owner(owner_identity_id)
    project = next((x for x in workspace["projects"] if str(x.get("project_id")) == str(project_id)), None)
    if project is None:
        raise KeyError("project-not-found")
    refs = [x for x in workspace["project_references"] if str(x.get("project_id")) == str(project_id)]
    bundles = [x for x in workspace["source_bundles"] if str(x.get("project_id")) == str(project_id)]
    queue = [x for x in workspace["research_queue"] if str(x.get("project_id") or "") == str(project_id)]
    basis = {
        "project_id": project_id,
        "updated_at": project.get("updated_at"),
        "references": [x.get("reference_id") for x in refs],
        "bundles": [x.get("bundle_id") for x in bundles],
    }
    return {
        "schema": PROJECT_CONTEXT_CONTRACT,
        "context_id": "saved-workspace-project:" + _fp(basis)[:32],
        "project": project,
        "references": refs,
        "source_bundles": bundles,
        "research_queue": queue,
        "authority": "python-research-state-service",
        "guardrails": guardrails(),
    }

def save_project(owner_identity_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    body["owner_identity_id"] = owner_identity_id
    body.setdefault("visibility", "private")
    body.setdefault("status", "active")
    return upsert_project(body)

def save_working_set(owner_identity_id: str, project_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    raw_records = list((payload or {}).get("records") or [])
    records: list[dict[str, str]] = []
    seen: set[str] = set()
    for item in raw_records[:MAX_WORKING_SET_SAVE]:
        if not isinstance(item, dict):
            continue
        record_id = _clean(item.get("id") or item.get("record_id") or item.get("ref_id"), 360)
        if not record_id or record_id in seen:
            continue
        seen.add(record_id)
        records.append({
            "id": record_id,
            "title": _clean(item.get("title") or item.get("label") or record_id, 220),
            "type": _clean(item.get("type") or item.get("object_type") or "library-record", 80),
        })
    if not records:
        raise ValueError("working-set-records-required")

    # Ownership is enforced by the underlying Research State service.
    project_context(owner_identity_id, project_id)

    saved = []
    for item in records:
        saved.append(add_project_reference(project_id, {
            "owner_identity_id": owner_identity_id,
            "family": "library_record",
            "ref_id": item["id"],
            "label": item["title"],
            "role": "reference",
            "metadata": {
                "saved_from": "library-web-working-set",
                "object_type": item["type"],
                "workspace_contract": CONTRACT,
            },
        }))

    context = project_context(owner_identity_id, project_id)
    basis = {
        "project_id": project_id,
        "saved_reference_ids": [x.get("reference_id") for x in saved],
    }
    return {
        "schema": WORKING_SET_SAVE_CONTRACT,
        "save_id": "working-set-save:" + _fp(basis)[:32],
        "project_id": project_id,
        "saved_count": len(saved),
        "saved_references": saved,
        "project_context": context,
        "browser_working_set_cleared_automatically": False,
        "authority": "python-research-state-service",
        "guardrails": guardrails(),
    }

def save_search(owner_identity_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    body["owner_identity_id"] = owner_identity_id
    return create_saved_search(body)

def save_collection(owner_identity_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    body["owner_identity_id"] = owner_identity_id
    body.setdefault("visibility", "private")
    return create_collection(body)

def save_collection_item(owner_identity_id: str, collection_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    body = dict(payload or {})
    body["owner_identity_id"] = owner_identity_id
    return add_collection_item(collection_id, body)
