from __future__ import annotations

from typing import Any

from .runtime_authority import dependency_graph, guardrails as runtime_guardrails
from .identity_access import boundary_contract as identity_boundary_contract

LIBRARY_VERSION = "5.66.0"
BACKEND_VERSION = "2.77.0"
CONTRACT = "sc-library-wordpress-thin-adapter/1.0"
READINESS_CONTRACT = "sc-library-wordpress-thin-adapter-readiness/1.0"

ALLOWED_RESPONSIBILITIES: tuple[str, ...] = (
    "public-routing",
    "seo-and-public-metadata",
    "launch-and-embed-surfaces",
    "health-and-status-display",
    "optional-identity-handoff",
    "legacy-presentation-compatibility",
)

PROHIBITED_AUTHORITIES: tuple[str, ...] = (
    "research-object-authority",
    "research-execution-authority",
    "research-job-authority",
    "artifact-authority",
    "pipeline-authority",
    "compute-authority",
    "identity-authority",
    "session-authority",
    "credential-authority",
    "federation-authority",
    "trust-policy-authority",
    "platform-core-promotion-authority",
)


def guardrails() -> dict[str, bool]:
    return {
        "wordpress_is_thin_adapter": True,
        "wordpress_is_research_runtime": False,
        "wordpress_owns_research_state": False,
        "wordpress_owns_jobs": False,
        "wordpress_owns_artifacts": False,
        "wordpress_owns_pipelines": False,
        "wordpress_owns_compute": False,
        "wordpress_owns_identity": False,
        "wordpress_owns_sessions": False,
        "wordpress_cookie_is_library_session": False,
        "wordpress_identity_handoff_is_optional": True,
        "wordpress_health_proxy_is_authoritative_api": False,
        "legacy_wordpress_routes_are_api_v1_contract": False,
        "wordpress_can_promote_to_platform_core": False,
        "new_research_capability_may_require_wordpress": False,
    }


def contract() -> dict[str, Any]:
    graph = dependency_graph()
    identity = identity_boundary_contract()
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "adapter": {
            "id": "wordpress",
            "role": "thin-adapter",
            "authoritative": False,
            "required_for_research_execution": False,
            "required_for_library_web": False,
        },
        "allowed_responsibilities": list(ALLOWED_RESPONSIBILITIES),
        "prohibited_authorities": list(PROHIBITED_AUTHORITIES),
        "authoritative_services": [
            "library-api",
            "python-backend",
            "postgresql",
            "research-workers",
            "artifact-storage",
            "pipeline-engine",
            "compute-broker",
            "library-identity-session-access",
            "global-knowledge-federation",
            "platform-core-contracts",
        ],
        "identity_handoff": {
            "supported_as_adapter_role": True,
            "authoritative": False,
            "wordpress_cookie_is_library_session": False,
            "library_session_authority": identity.get("session_authority", "library-service"),
        },
        "dependency_graph": graph,
        "runtime_guardrails": runtime_guardrails(),
        "guardrails": guardrails(),
    }


def validate_adapter_claim(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload = dict(payload or {})
    errors: list[str] = []
    if payload.get("wordpress_authoritative") is True:
        errors.append("wordpress-authority-prohibited")
    if payload.get("wordpress_required_for_research_execution") is True:
        errors.append("wordpress-research-runtime-dependency-prohibited")
    if payload.get("wordpress_owns_sessions") is True:
        errors.append("wordpress-session-authority-prohibited")
    if payload.get("wordpress_cookie_is_library_session") is True:
        errors.append("wordpress-cookie-session-equivalence-prohibited")
    if payload.get("automatic_platform_core_promotion") is True:
        errors.append("automatic-platform-core-promotion-prohibited")
    return {
        "schema": "sc-library-wordpress-thin-adapter-validation/1.0",
        "valid": not errors,
        "errors": errors,
        "normalized": contract(),
    }


def readiness() -> dict[str, Any]:
    c = contract()
    graph = c["dependency_graph"]
    wordpress_edges = int(graph.get("wordpress_dependency_count", 0) or 0)
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if wordpress_edges == 0 else "blocked",
        "wordpress_dependency_count": wordpress_edges,
        "adapter": c["adapter"],
        "allowed_responsibilities": c["allowed_responsibilities"],
        "prohibited_authorities": c["prohibited_authorities"],
        "identity_handoff": c["identity_handoff"],
        "guardrails": c["guardrails"],
    }
