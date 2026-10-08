from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .independent_application_certification import readiness as independent_application_readiness
from .independent_library_product import readiness as independent_product_readiness
from .web_application import readiness as web_application_readiness
from .domain_authority import readiness as domain_authority_readiness
from .research_interface import readiness as research_interface_readiness
from .navigation_service import readiness as navigation_readiness
from .library7_production_consolidation_certification import readiness as predecessor_gate_readiness

LIBRARY_VERSION = "7.0.0"
BACKEND_VERSION = "4.0.0"
WEB_VERSION = "3.0.0"
SDK_VERSION = "2.0.0"
API_VERSION = "1.0"

CONTRACT = "sc-independent-knowledge-library-platform/1.0"
READINESS_CONTRACT = "sc-independent-knowledge-library-platform-readiness/1.0"
RELEASE_CONTRACT = "sc-independent-knowledge-library-platform-release/1.0"
CERTIFICATION_CONTRACT = "sc-independent-knowledge-library-platform-certification/1.0"
EXPORT_CONTRACT = "sc-independent-knowledge-library-platform-certification-export/1.0"

REQUIRED_RUNTIME_COMPONENTS = (
    "library-backend",
    "library-web",
    "postgresql",
    "redis",
    "ingestion",
    "worker-python",
    "worker-go",
    "worker-rust",
)

REQUIRED_SURFACES = (
    "api-v1",
    "runtime-authority",
    "independent-application",
    "independent-library-product",
    "independent-knowledge-library-platform",
    "research-interface",
    "navigation",
    "research-federation",
    "research-audit",
)

REQUIRED_PRODUCTION_ASSERTIONS = (
    "backend_health",
    "web_health",
    "public_origin_health",
    "same_origin_api_proxy",
    "rollback_backup_available",
    "database_migration_not_required",
    "predecessor_639_certified",
)

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _truth(value: Any) -> bool:
    if value is True:
        return True
    if isinstance(value, dict):
        return value.get("ready") is True or value.get("ok") is True or value.get("certified") is True
    return False

def guardrails() -> dict[str, Any]:
    return {
        "library_is_independent_primary_application": True,
        "api_v1_remains_stability_boundary": True,
        "api_v1_breaking_change": False,
        "python_backend_is_application_runtime_authority": True,
        "postgresql_is_structured_state_authority": True,
        "library_web_is_public_origin_authority": True,
        "wordpress_is_optional_adapter": True,
        "wordpress_required": False,
        "wordpress_authoritative": False,
        "wordpress_is_research_state_authority": False,
        "wordpress_is_identity_session_authority": False,
        "legacy_php_compatibility_may_remain": True,
        "legacy_php_compatibility_is_domain_authority": False,
        "automatic_database_migration": False,
        "destructive_database_migration": False,
        "automatic_external_fetch": False,
        "automatic_reexecution": False,
        "automatic_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "release_success_implies_research_truth": False,
        "runtime_success_implies_research_truth": False,
        "feature_line_status": "stabilized",
        "maintenance_line": "7.0.x",
    }

def contract() -> dict[str, Any]:
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "application_mode": "independent-primary",
        "maintenance_line": "7.0.x",
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "platform_id": "sustainable-catalyst-independent-knowledge-library",
        "platform_name": "Independent Knowledge Library Platform",
        "platform_contract_id": "independent-knowledge-library-platform:" + _fp(basis)[:32],
        "platform_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "generation": 7,
        "application_mode": "independent-primary",
        "state": "stable-platform-baseline",
        "authoritative_runtime": "python-backend",
        "structured_state_authority": "postgresql",
        "public_origin_authority": "library-web",
        "identity_session_authority": "library-service",
        "wordpress": {
            "role": "optional-adapter",
            "required": False,
            "authoritative": False,
            "legacy_compatibility_supported": True,
        },
        "compatibility": {
            "api_v1_preserved": True,
            "api_v1_breaking_change": False,
            "v6_research_surfaces_preserved": True,
            "v6_component_version_lineage_preserved": True,
            "destructive_data_migration_required": False,
        },
        "release_policy": {
            "feature_line_status": "stabilized",
            "maintenance_line": "7.0.x",
            "next_feature_release": None,
            "patches_for": ["bugs", "security", "deployment", "required-integration"],
        },
        "predecessor": {
            "release": "6.39.0",
            "gate": "/api/library/v1/library7-certification",
            "production_certification_required": True,
        },
        "guardrails": guardrails(),
    }

def release_manifest() -> dict[str, Any]:
    c = contract()
    basis = {
        "platform_id": c["platform_id"],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "maintenance_line": "7.0.x",
    }
    return {
        "schema": RELEASE_CONTRACT,
        "release_id": "independent-library-7:" + _fp(basis)[:32],
        "release_fingerprint_sha256": _fp(basis),
        **basis,
        "release_name": "Independent Knowledge Library Platform",
        "artifacts": [
            "library-backend",
            "library-web",
            "python-sdk",
            "javascript-typescript-client",
            "wordpress-optional-adapter",
        ],
        "runtime_components": list(REQUIRED_RUNTIME_COMPONENTS),
        "database_migration_required": False,
        "destructive_database_migration": False,
        "wordpress_required": False,
        "api_v1_breaking_change": False,
        "feature_line_status": "stabilized",
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    checks: dict[str, bool] = {}
    details: dict[str, Any] = {}
    probes = {
        "independent_application": independent_application_readiness(),
        "independent_product": independent_product_readiness(),
        "web_application": web_application_readiness(),
        "domain_authority": domain_authority_readiness(),
        "research_interface": research_interface_readiness(),
        "navigation": navigation_readiness(),
        "predecessor_gate": predecessor_gate_readiness(),
    }
    details.update(probes)

    checks["independent_application"] = (
        probes["independent_application"].get("state") == "ready"
        and probes["independent_application"].get("certified") is True
        and probes["independent_application"].get("library_version") == LIBRARY_VERSION
        and probes["independent_application"].get("backend_version") == BACKEND_VERSION
    )
    checks["independent_product"] = (
        probes["independent_product"].get("ready") is True
        and probes["independent_product"].get("library_version") == LIBRARY_VERSION
        and probes["independent_product"].get("backend_version") == BACKEND_VERSION
        and probes["independent_product"].get("web_version") == WEB_VERSION
        and probes["independent_product"].get("sdk_version") == SDK_VERSION
    )
    checks["web_application"] = (
        probes["web_application"].get("state") == "ready"
        and probes["web_application"].get("library_version") == LIBRARY_VERSION
        and probes["web_application"].get("backend_version") == BACKEND_VERSION
        and probes["web_application"].get("web_version") == WEB_VERSION
        and probes["web_application"].get("wordpress_required") is False
    )
    checks["domain_authority"] = (
        probes["domain_authority"].get("state") == "ready"
        and probes["domain_authority"].get("library_version") == LIBRARY_VERSION
        and probes["domain_authority"].get("backend_version") == BACKEND_VERSION
    )
    checks["research_interface"] = probes["research_interface"].get("state") == "ready"
    checks["navigation"] = probes["navigation"].get("state") == "ready"
    checks["predecessor_gate_available"] = (
        probes["predecessor_gate"].get("state") == "ready"
        and probes["predecessor_gate"].get("library_version") == "6.39.0"
    )

    errors = [name + "-not-ready" for name, ok in checks.items() if not ok]
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
        "checks": checks,
        "components": details,
        "production_certification_required": True,
        "production_certified": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "maintenance_line": "7.0.x",
        "guardrails": guardrails(),
    }

def default_evidence() -> dict[str, Any]:
    return {
        "generations": {
            "library": LIBRARY_VERSION,
            "backend": BACKEND_VERSION,
            "web": WEB_VERSION,
            "sdk": SDK_VERSION,
            "api": API_VERSION,
        },
        "runtime_components": {name: True for name in REQUIRED_RUNTIME_COMPONENTS},
        "surfaces": {name: True for name in REQUIRED_SURFACES},
        "production_assertions": {name: True for name in REQUIRED_PRODUCTION_ASSERTIONS},
        "authority": {
            "python_backend_authoritative": True,
            "postgresql_authoritative": True,
            "library_web_public_origin_authoritative": True,
            "wordpress_required": False,
            "wordpress_authoritative": False,
        },
        "guardrails": {
            "api_v1_breaking_change": False,
            "automatic_database_migration": False,
            "automatic_external_fetch": False,
            "automatic_reexecution": False,
            "automatic_persistence": False,
            "automatic_truth_promotion": False,
            "automatic_platform_core_promotion": False,
        },
        "metadata": {"evidence_kind": "synthetic-test-fixture"},
    }

def evaluate(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload = dict(payload or {})
    errors: list[str] = []
    checks: dict[str, Any] = {}

    expected = {
        "library": LIBRARY_VERSION,
        "backend": BACKEND_VERSION,
        "web": WEB_VERSION,
        "sdk": SDK_VERSION,
        "api": API_VERSION,
    }
    supplied_generations = payload.get("generations") if isinstance(payload.get("generations"), dict) else {}
    generation_checks: dict[str, bool] = {}
    for key, value in expected.items():
        ok = str(supplied_generations.get(key) or "") == value
        generation_checks[key] = ok
        if not ok:
            errors.append(f"generation-mismatch:{key}")
    checks["generations"] = generation_checks

    for family, required in (
        ("runtime_components", REQUIRED_RUNTIME_COMPONENTS),
        ("surfaces", REQUIRED_SURFACES),
        ("production_assertions", REQUIRED_PRODUCTION_ASSERTIONS),
    ):
        supplied = payload.get(family) if isinstance(payload.get(family), dict) else {}
        family_checks = {}
        for name in required:
            ok = _truth(supplied.get(name))
            family_checks[name] = ok
            if not ok:
                errors.append(f"{family}-not-ready:{name}")
        checks[family] = family_checks

    authority = payload.get("authority") if isinstance(payload.get("authority"), dict) else {}
    authority_expected = {
        "python_backend_authoritative": True,
        "postgresql_authoritative": True,
        "library_web_public_origin_authoritative": True,
        "wordpress_required": False,
        "wordpress_authoritative": False,
    }
    authority_checks = {}
    for key, expected_value in authority_expected.items():
        ok = authority.get(key) is expected_value
        authority_checks[key] = ok
        if not ok:
            errors.append(f"authority-assertion-failed:{key}")
    checks["authority"] = authority_checks

    supplied_guardrails = payload.get("guardrails") if isinstance(payload.get("guardrails"), dict) else {}
    forbidden_true = (
        "api_v1_breaking_change",
        "automatic_database_migration",
        "automatic_external_fetch",
        "automatic_reexecution",
        "automatic_persistence",
        "automatic_truth_promotion",
        "automatic_platform_core_promotion",
    )
    guardrail_checks = {}
    for key in forbidden_true:
        ok = supplied_guardrails.get(key) is False
        guardrail_checks[key] = ok
        if not ok:
            errors.append(f"guardrail-failed:{key}")
    checks["guardrails"] = guardrail_checks

    basis = {
        "generations": supplied_generations,
        "checks": checks,
        "errors": errors,
        "metadata": dict(payload.get("metadata") or {}),
    }
    fingerprint = _fp(basis)
    return {
        "schema": CERTIFICATION_CONTRACT,
        "certification_id": "independent-knowledge-library-platform-cert:" + fingerprint[:32],
        "certification_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": API_VERSION,
        "state": "certified" if not errors else "blocked",
        "certified": not errors,
        "errors": errors,
        "checks": checks,
        "production_evidence_required": True,
        "database_migration_required": False,
        "wordpress_required": False,
        "maintenance_line": "7.0.x",
        "guardrails": guardrails(),
    }

def export_certification(payload: dict[str, Any] | None) -> dict[str, Any]:
    result = evaluate(payload)
    content = _canon(result)
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-library-7.0.0-platform-certification.json",
        "media_type": "application/json",
        "sha256": sha256(content.encode("utf-8")).hexdigest(),
        "content": content,
        "certified": result["certified"],
        "guardrails": guardrails(),
    }
