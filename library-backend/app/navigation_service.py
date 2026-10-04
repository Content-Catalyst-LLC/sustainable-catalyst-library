from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .research_interface import (
    contract as research_interface_contract,
    readiness as research_interface_readiness,
    bootstrap as research_interface_bootstrap,
)
from .independent_api import capability_catalog
from .web_application import application_contract as web_application_contract

LIBRARY_VERSION = "6.13.0"
BACKEND_VERSION = "3.13.0"
WEB_VERSION = "2.13.0"
SDK_VERSION = "1.13.0"

CONTRACT = "sc-library-unified-discovery-research-navigation/1.0"
READINESS_CONTRACT = "sc-library-unified-discovery-research-navigation-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-unified-discovery-research-navigation-bootstrap/1.0"
RESOLUTION_CONTRACT = "sc-library-unified-discovery-research-navigation-resolution/1.0"

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def guardrails() -> dict[str, bool]:
    return {
        "navigation_is_domain_authority": False,
        "navigation_creates_second_search_engine": False,
        "navigation_creates_second_research_state": False,
        "research_interface_remains_composition_layer": True,
        "retrieval_orchestration_remains_search_authority": True,
        "research_state_service_remains_saved_state_authority": True,
        "legacy_search_route_remains_supported": True,
        "legacy_discover_route_remains_supported": True,
        "legacy_routes_are_primary_navigation": False,
        "browser_working_set_is_authoritative": False,
        "capability_registry_is_research_quality_ranking": False,
        "navigation_order_is_research_quality_ranking": False,
        "wordpress_required": False,
        "wordpress_is_navigation_authority": False,
        "api_v1_remains_stable": True,
        "automatic_platform_core_promotion": False,
    }

def navigation_model() -> dict[str, Any]:
    primary = [
        {"id": "research", "label": "Research", "route": "/research", "surface": "research", "primary": True},
        {"id": "system", "label": "System", "route": "/system", "surface": "system", "primary": True},
        {"id": "account", "label": "Account", "route": "/account", "surface": "account", "primary": True},
    ]
    research_modes = [
        {"id": "overview", "label": "Overview", "route": "/research", "mode": "overview"},
        {"id": "discover", "label": "Discover", "route": "/discover", "mode": "discover", "compatibility_route": True},
        {"id": "search", "label": "Search", "route": "/search", "mode": "search", "compatibility_route": True},
        {"id": "working-set", "label": "Working Set", "route": "/research?mode=working-set", "mode": "working-set"},
        {"id": "archives", "label": "Archives", "route": "/research/archives", "mode": "archives"},
        {"id": "projects", "label": "Projects", "route": "/account?section=workspaces", "mode": "projects"},
    ]
    aliases = {
        "/search": {"canonical_route": "/research", "surface": "research", "mode": "search"},
        "/discover": {"canonical_route": "/research", "surface": "research", "mode": "discover"},
    }
    return {
        "primary": primary,
        "research_modes": research_modes,
        "aliases": aliases,
        "record_context": {"route_template": "/record/{record_id}", "parent_surface": "research"},
        "saved_research": {"route": "/account?section=workspaces", "parent_surface": "account", "authority": "python-research-state-service", "session_api": "/api/library/v1/workspaces"},
    }

def contract() -> dict[str, Any]:
    nav = navigation_model()
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "navigation": nav,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "navigation_id": "unified-library-navigation:" + _fp(basis)[:32],
        "navigation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "unified",
        "authority": "python-backend-composition",
        "canonical_research_route": "/research",
        "navigation": nav,
        "wordpress": {"role": "optional-adapter", "required": False, "authoritative": False},
        "next_release": "6.14.0",
        "next_release_name": "Primary-Source Comparison & Source Criticism Workspace",
        "guardrails": guardrails(),
    }

def _pathways(capabilities: dict[str, Any]) -> list[dict[str, Any]]:
    families = capabilities.get("families") if isinstance(capabilities.get("families"), dict) else {}
    groups = [
        ("discover", "Discover", ["discovery", "federation", "language"]),
        ("understand", "Understand", ["provenance-graph", "transparency", "cross-civilizational"]),
        ("organize", "Organize", ["research-state", "research-interface"]),
        ("reproduce", "Reproduce", ["research-package-reproducibility", "background-job-workflows", "artifacts"]),
    ]
    out: list[dict[str, Any]] = []
    for group_id, label, keys in groups:
        items = []
        for key in keys:
            if key not in families:
                continue
            family = families[key]
            items.append({
                "family": key,
                "authority": family.get("authority"),
                "resources": list(family.get("resources") or []),
                "direct_api": bool(family.get("direct_api")),
            })
        out.append({"id": group_id, "label": label, "items": items})
    return out

def bootstrap() -> dict[str, Any]:
    research = research_interface_bootstrap()
    capabilities = capability_catalog()
    nav = navigation_model()
    basis = {
        "navigation": nav,
        "research_search": research.get("search"),
        "research_facets": research.get("facets"),
        "pathways": _pathways(capabilities),
    }
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "bootstrap_id": "unified-library-navigation-bootstrap:" + _fp(basis)[:32],
        "bootstrap_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "navigation": nav,
        "research": {
            "search": research.get("search", {}),
            "facets": research.get("facets", {}),
            "working_set": research.get("working_set", {}),
            "handoffs": research.get("handoffs", {}),
        },
        "pathways": _pathways(capabilities),
        "guardrails": guardrails(),
    }

def resolve_route(path: str) -> dict[str, Any]:
    raw = "/" + str(path or "").strip().split("?", 1)[0].strip("/")
    if raw == "//":
        raw = "/"
    nav = navigation_model()
    alias = nav["aliases"].get(raw)
    if alias:
        resolved = {"requested_route": raw, **alias, "compatibility_alias": True}
    elif raw == "/research/archives":
        resolved = {"requested_route": raw, "canonical_route": raw, "surface": "research", "mode": "archives", "compatibility_alias": False}
    elif raw in {"/research", "/system", "/account"}:
        resolved = {"requested_route": raw, "canonical_route": raw, "surface": raw.lstrip("/"), "mode": None, "compatibility_alias": False}
    elif raw.startswith("/record/"):
        resolved = {"requested_route": raw, "canonical_route": raw, "surface": "record", "mode": "record-context", "compatibility_alias": False}
    else:
        resolved = {"requested_route": raw, "canonical_route": "/research", "surface": "research", "mode": "overview", "compatibility_alias": False, "fallback": True}
    return {
        "schema": RESOLUTION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        **resolved,
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    research = research_interface_readiness()
    web = web_application_contract()
    errors: list[str] = []
    if research.get("state") != "ready":
        errors.append("research-interface-not-ready")
    if str(research.get("library_version") or "") != LIBRARY_VERSION:
        errors.append("research-interface-library-version-mismatch")
    if str(research.get("backend_version") or "") != BACKEND_VERSION:
        errors.append("research-interface-backend-version-mismatch")
    if str(research.get("web_version") or "") != WEB_VERSION:
        errors.append("research-interface-web-version-mismatch")
    if str(research.get("sdk_version") or "") != SDK_VERSION:
        errors.append("research-interface-sdk-version-mismatch")
    if str(web.get("web_version") or "") != WEB_VERSION:
        errors.append("web-version-mismatch")
    if web.get("wordpress", {}).get("required") is True:
        errors.append("wordpress-required")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready" if not errors else "blocked",
        "ready": not errors,
        "errors": errors,
        "canonical_research_route": "/research",
        "compatibility_routes": ["/search", "/discover"],
        "wordpress_required": False,
        "guardrails": guardrails(),
    }
