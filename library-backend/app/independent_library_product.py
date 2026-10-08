from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .independent_application_certification import build_certification
from .independent_api import readiness as api_readiness
from .runtime_authority import readiness as runtime_authority_readiness
from .web_application import readiness as web_application_readiness
from .identity_access import readiness as identity_access_readiness
from .client_framework import readiness as client_framework_readiness
from .release_engineering import readiness as release_engineering_readiness
from .wordpress_thin_adapter import readiness as wordpress_adapter_readiness

LIBRARY_VERSION = "7.0.0"
BACKEND_VERSION = "4.0.0"
WEB_VERSION = "3.0.0"
SDK_VERSION = "2.0.0"
API_VERSION = "1.0"

CONTRACT = "sc-independent-sustainable-catalyst-knowledge-library/1.0"
READINESS_CONTRACT = "sc-independent-sustainable-catalyst-knowledge-library-readiness/1.0"
RELEASE_CONTRACT = "sc-independent-sustainable-catalyst-knowledge-library-release/1.0"

# These are certified minimum versions of long-lived component contracts, not
# a requirement that every component adopt the Library release number.
PRODUCT_COMPONENT_VERSION_FLOORS_V700: dict[str, dict[str, str]] = {
    "api-v1": {"library_version": "6.12.0", "backend_version": "3.12.0"},
    "runtime-authority": {"library_version": "6.3.0", "backend_version": "3.3.0"},
    "client-framework": {
        "library_version": "6.4.0",
        "backend_version": "3.4.0",
        "sdk_version": "1.4.0",
    },
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _version_tuple(value: Any) -> tuple[int, ...] | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    parts = raw.split(".")
    try:
        return tuple(int(part) for part in parts)
    except ValueError:
        return None


def _version_at_least(actual: Any, minimum: str) -> bool:
    a = _version_tuple(actual)
    b = _version_tuple(minimum)
    if a is None or b is None:
        return False
    width = max(len(a), len(b))
    return a + (0,) * (width - len(a)) >= b + (0,) * (width - len(b))


def guardrails() -> dict[str, bool]:
    return {
        "library_is_independent_primary_application": True,
        "wordpress_is_optional_adapter": True,
        "wordpress_is_application_runtime": False,
        "wordpress_is_research_state_authority": False,
        "wordpress_is_identity_session_authority": False,
        "wordpress_required_for_api_v1": False,
        "wordpress_required_for_library_web": False,
        "wordpress_required_for_research_execution": False,
        "wordpress_required_for_cross_product_clients": False,
        "api_v1_remains_stable_in_6_0": True,
        "api_v1_remains_stable_in_7_0": True,
        "postgresql_is_structured_state_authority": True,
        "python_backend_is_application_runtime": True,
        "library_web_is_first_party_client": True,
        "legacy_php_compatibility_may_remain": True,
        "legacy_php_compatibility_is_domain_authority": False,
        "automatic_platform_core_promotion": False,
        "runtime_success_implies_research_truth": False,
        "release_success_implies_research_truth": False,
    }


def contract() -> dict[str, Any]:
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "application_mode": "independent-primary",
        "wordpress_role": "optional-adapter",
        "component_version_policy": "current-product-plus-explicit-certified-component-floors-v700",
        "component_version_floors": PRODUCT_COMPONENT_VERSION_FLOORS_V700,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "product_id": "sustainable-catalyst-knowledge-library",
        "product_name": "Independent Sustainable Catalyst Knowledge Library",
        "product_contract_id": "independent-library-product:" + _fp(basis)[:32],
        "product_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "api_base_path": "/api/library/v1",
        "generation": 7,
        "application_mode": "independent-primary",
        "state": "independent-primary-application",
        "authoritative_runtime": "python-backend",
        "structured_state_authority": "postgresql",
        "identity_session_authority": "library-service",
        "first_party_web": {
            "application_id": "sustainable-catalyst-library-web",
            "version": WEB_VERSION,
            "direct_api_v1": True,
            "wordpress_required": False,
        },
        "client_framework": {
            "sdk_version": SDK_VERSION,
            "python": True,
            "javascript": True,
            "typescript": True,
            "direct_api_v1": True,
        },
        "wordpress": {
            "role": "optional-adapter",
            "authoritative": False,
            "required": False,
            "research_runtime": False,
            "request_path_dependency": False,
            "legacy_compatibility_supported": True,
        },
        "compatibility": {
            "api_v1_preserved": True,
            "v5_client_contracts_remain_additively_compatible": True,
            "wordpress_adapter_remains_supported": True,
            "destructive_data_migration_required": False,
            "component_version_policy": "current-product-plus-explicit-certified-component-floors-v700",
            "component_version_floors": PRODUCT_COMPONENT_VERSION_FLOORS_V700,
        },
        "certification_basis": "v7.0-independent-application-certification",
        "maintenance_line": "7.0.x",
        "feature_line_status": "stabilized",
        "next_feature_release": None,
        "next_release": None,
        "next_release_name": None,
        "guardrails": guardrails(),
    }


def release_manifest() -> dict[str, Any]:
    c = contract()
    basis = {
        "product_id": c["product_id"],
        "library_version": c["library_version"],
        "backend_version": c["backend_version"],
        "web_version": c["web_version"],
        "sdk_version": c["sdk_version"],
        "api_version": c["api_version"],
        "application_mode": c["application_mode"],
    }
    return {
        "schema": RELEASE_CONTRACT,
        "release_id": "independent-library-7:" + _fp(basis)[:32],
        "release_fingerprint_sha256": _fp(basis),
        **basis,
        "artifacts": [
            "library-backend",
            "library-web",
            "python-sdk",
            "javascript-typescript-client",
            "wordpress-optional-adapter",
        ],
        "wordpress_required": False,
        "destructive_database_migration": False,
        "api_v1_breaking_change": False,
        "component_version_policy": "current-product-plus-explicit-certified-component-floors-v700",
        "component_version_floors": PRODUCT_COMPONENT_VERSION_FLOORS_V700,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    certification = build_certification()
    api = api_readiness()
    runtime = runtime_authority_readiness()
    web = web_application_readiness()
    identity = identity_access_readiness()
    clients = client_framework_readiness()
    release = release_engineering_readiness()
    adapter = wordpress_adapter_readiness()

    errors: list[str] = []

    if certification.get("certified") is not True:
        errors.append("independent-application-certification-not-ready")

    api_floor = PRODUCT_COMPONENT_VERSION_FLOORS_V700["api-v1"]
    if api.get("state") != "ready":
        errors.append("api-v1-not-ready")
    if not _version_at_least(api.get("library_version"), api_floor["library_version"]):
        errors.append("api-library-version-below-certified-baseline")
    if not _version_at_least(api.get("backend_version"), api_floor["backend_version"]):
        errors.append("api-backend-version-below-certified-baseline")
    if str(api.get("api_version") or "") != API_VERSION:
        errors.append("api-version-mismatch")

    runtime_floor = PRODUCT_COMPONENT_VERSION_FLOORS_V700["runtime-authority"]
    if runtime.get("state") != "ready":
        errors.append("runtime-authority-not-ready")
    if not _version_at_least(runtime.get("version"), runtime_floor["library_version"]):
        errors.append("runtime-authority-library-version-below-certified-baseline")
    if not _version_at_least(runtime.get("backend_version"), runtime_floor["backend_version"]):
        errors.append("runtime-authority-backend-version-below-certified-baseline")
    if int(runtime.get("wordpress_dependency_count") or 0) != 0:
        errors.append("runtime-authority-wordpress-dependency")

    # The first-party Web is a release-generation artifact, so it must match
    # the current Library 7.0 production generation exactly.
    if web.get("state") != "ready":
        errors.append("library-web-not-ready")
    if str(web.get("library_version") or "") != LIBRARY_VERSION:
        errors.append("library-web-library-version-mismatch")
    if str(web.get("backend_version") or "") != BACKEND_VERSION:
        errors.append("library-web-backend-version-mismatch")
    if str(web.get("web_version") or "") != WEB_VERSION:
        errors.append("library-web-version-mismatch")
    if web.get("wordpress_required") is True:
        errors.append("library-web-wordpress-dependency")

    if identity.get("state") != "ready":
        errors.append("identity-session-not-ready")
    if identity.get("wordpress_required") is True:
        errors.append("identity-wordpress-dependency")

    client_floor = PRODUCT_COMPONENT_VERSION_FLOORS_V700["client-framework"]
    if clients.get("state") != "ready":
        errors.append("client-framework-not-ready")
    if not _version_at_least(clients.get("library_version"), client_floor["library_version"]):
        errors.append("client-framework-library-version-below-certified-baseline")
    if not _version_at_least(clients.get("backend_version"), client_floor["backend_version"]):
        errors.append("client-framework-backend-version-below-certified-baseline")
    if not _version_at_least(clients.get("sdk_version"), client_floor["sdk_version"]):
        errors.append("sdk-version-below-certified-baseline")
    if clients.get("wordpress_required") is True:
        errors.append("sdk-wordpress-dependency")

    if release.get("state") != "ready":
        errors.append("release-engineering-not-ready")

    if adapter.get("state") not in {"ready", "blocked"}:
        errors.append("wordpress-adapter-contract-unavailable")
    if int(adapter.get("wordpress_dependency_count") or 0) != 0:
        errors.append("wordpress-adapter-dependency-count-nonzero")

    c = contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "state": "ready" if not errors else "blocked",
        "ready": not errors,
        "errors": errors,
        "application_mode": "independent-primary",
        "wordpress_required": False,
        "wordpress_dependency_count": 0,
        "certification_basis_ready": certification.get("certified") is True,
        "component_version_policy": "current-product-plus-explicit-certified-component-floors-v700",
        "component_version_floors": PRODUCT_COMPONENT_VERSION_FLOORS_V700,
        "components": {
            "api_v1": {
                "state": api.get("state"),
                "version": api.get("api_version"),
                "library_version": api.get("library_version"),
                "backend_version": api.get("backend_version"),
            },
            "runtime_authority": {
                "state": runtime.get("state"),
                "library_version": runtime.get("version"),
                "backend_version": runtime.get("backend_version"),
            },
            "library_web": {
                "state": web.get("state"),
                "version": web.get("web_version"),
                "library_version": web.get("library_version"),
                "backend_version": web.get("backend_version"),
            },
            "identity_access": {"state": identity.get("state")},
            "client_framework": {
                "state": clients.get("state"),
                "version": clients.get("sdk_version"),
                "library_version": clients.get("library_version"),
                "backend_version": clients.get("backend_version"),
            },
            "release_engineering": {"state": release.get("state")},
            "wordpress_adapter": {"state": adapter.get("state"), "required": False},
        },
        "next_release": c["next_release"],
        "next_release_name": c["next_release_name"],
        "guardrails": guardrails(),
    }
