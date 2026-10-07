from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.39.0"
BACKEND_VERSION = "3.39.0"
WEB_VERSION = "2.39.0"
SDK_VERSION = "1.39.0"
NEXT_RELEASE = "7.0.0"
NEXT_RELEASE_NAME = "Independent Knowledge Library Platform"

CONTRACT = "sc-library7-production-consolidation-certification/1.0"
READINESS_CONTRACT = "sc-library7-production-consolidation-certification-readiness/1.0"
INVENTORY_CONTRACT = "sc-library7-production-consolidation-inventory/1.0"
CERTIFICATION_CONTRACT = "sc-library7-production-certification-result/1.0"
EXPORT_CONTRACT = "sc-library7-production-certification-export/1.0"

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

REQUIRED_SURFACE_PROBES = (
    "api-v1",
    "runtime-authority",
    "web-application",
    "independent-application",
    "independent-library-product",
    "research-interface",
    "navigation",
    "workspace-integration",
    "cross-product-certification",
    "research-project-workspace",
    "research-lineage-graph",
    "research-review-versioning",
    "research-package-readiness",
    "research-object-exchange",
    "research-rooms",
    "research-intelligence",
    "research-federation",
    "research-audit",
)

REQUIRED_AUTHORITY_ASSERTIONS = (
    "api_v1_authoritative",
    "python_backend_authoritative",
    "postgresql_structured_state_authoritative",
    "wordpress_required_false",
    "wordpress_authoritative_false",
    "library_web_public_origin_authoritative",
)

REQUIRED_DEPLOYMENT_ASSERTIONS = (
    "backend_health",
    "web_health",
    "public_origin_health",
    "same_origin_api_proxy",
    "rollback_backup_available",
    "database_migration_not_required",
)

REQUIRED_PRESERVED_GUARDRAILS = (
    "automatic_external_fetch_false",
    "automatic_reexecution_false",
    "automatic_persistence_false",
    "automatic_reproducibility_certification_false",
    "automatic_truth_promotion_false",
    "automatic_platform_core_promotion_false",
)


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _truth(value: Any) -> bool:
    if value is True:
        return True
    if isinstance(value, dict):
        return value.get("ready") is True or value.get("ok") is True or value.get("certified") is True
    return False


def guardrails() -> dict[str, Any]:
    return {
        "certification_is_release_readiness_only": True,
        "certification_is_new_domain_authority": False,
        "certification_is_new_research_state_authority": False,
        "certification_implies_research_truth": False,
        "certification_implies_source_validity": False,
        "certification_implies_evidence_strength": False,
        "certification_implies_scientific_validity": False,
        "certification_implies_security_perfection": False,
        "certification_implies_operational_perfection": False,
        "api_v1_remains_stability_boundary": True,
        "python_backend_remains_application_runtime_authority": True,
        "postgresql_remains_structured_state_authority": True,
        "library_web_remains_public_origin_authority": True,
        "wordpress_required": False,
        "wordpress_authoritative": False,
        "wordpress_role": "optional-thin-adapter",
        "automatic_database_migration": False,
        "automatic_runtime_mutation": False,
        "automatic_external_fetch": False,
        "automatic_reexecution": False,
        "automatic_persistence": False,
        "automatic_reproducibility_certification": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "production_probe_evidence_required": True,
        "rollback_backup_required": True,
    }


def inventory() -> dict[str, Any]:
    basis = {
        "generations": {
            "library": LIBRARY_VERSION,
            "backend": BACKEND_VERSION,
            "web": WEB_VERSION,
            "sdk": SDK_VERSION,
            "api": "1.0",
        },
        "runtime_components": list(REQUIRED_RUNTIME_COMPONENTS),
        "surface_probes": list(REQUIRED_SURFACE_PROBES),
        "authority_assertions": list(REQUIRED_AUTHORITY_ASSERTIONS),
        "deployment_assertions": list(REQUIRED_DEPLOYMENT_ASSERTIONS),
        "preserved_guardrails": list(REQUIRED_PRESERVED_GUARDRAILS),
        "bind_contract": {"backend": "127.0.0.1:8087", "web": "127.0.0.1:8095"},
    }
    return {
        "schema": INVENTORY_CONTRACT,
        "inventory_id": "library7-production-inventory:" + _fp(basis)[:32],
        "inventory_fingerprint_sha256": _fp(basis),
        **basis,
    }


def contract() -> dict[str, Any]:
    inv = inventory()
    basis = {
        "inventory_fingerprint_sha256": inv["inventory_fingerprint_sha256"],
        "next_release": NEXT_RELEASE,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "gate_id": "library7-production-certification-gate:" + _fp(basis)[:32],
        "gate_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": "1.0",
        "state": "certification-gate",
        "authority": "python-backend-composition",
        "purpose": "consolidate-and-certify-library-6x-production-runtime-before-library-7",
        "inventory": inv,
        "next_release": NEXT_RELEASE,
        "next_release_name": NEXT_RELEASE_NAME,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "ready": True,
        "certification_service_ready": True,
        "library7_certified": False,
        "production_certification_required": True,
        "database_migration_required": False,
        "wordpress_required": False,
        "next_release": NEXT_RELEASE,
        "next_release_name": NEXT_RELEASE_NAME,
        "guardrails": guardrails(),
    }


def default_evidence() -> dict[str, Any]:
    return {
        "generations": {
            "library": LIBRARY_VERSION,
            "backend": BACKEND_VERSION,
            "web": WEB_VERSION,
            "sdk": SDK_VERSION,
            "api": "1.0",
        },
        "runtime_components": {name: True for name in REQUIRED_RUNTIME_COMPONENTS},
        "surface_probes": {name: True for name in REQUIRED_SURFACE_PROBES},
        "authority_assertions": {name: True for name in REQUIRED_AUTHORITY_ASSERTIONS},
        "deployment_assertions": {
            **{name: True for name in REQUIRED_DEPLOYMENT_ASSERTIONS},
            "backend_bind": "127.0.0.1:8087",
            "web_bind": "127.0.0.1:8095",
        },
        "preserved_guardrails": {name: True for name in REQUIRED_PRESERVED_GUARDRAILS},
        "metadata": {"evidence_kind": "synthetic-test-fixture"},
    }


def evaluate(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    errors: list[str] = []
    checks: dict[str, Any] = {}

    generations = _dict(payload.get("generations"))
    expected_generations = {
        "library": LIBRARY_VERSION,
        "backend": BACKEND_VERSION,
        "web": WEB_VERSION,
        "sdk": SDK_VERSION,
        "api": "1.0",
    }
    generation_checks = {}
    for name, expected in expected_generations.items():
        actual = str(generations.get(name) or "")
        ok = actual == expected
        generation_checks[name] = {"expected": expected, "actual": actual or None, "ready": ok}
        if not ok:
            errors.append(f"generation-mismatch:{name}:{actual or 'missing'}:{expected}")
    checks["generations"] = generation_checks

    runtime = _dict(payload.get("runtime_components"))
    runtime_checks = {}
    for name in REQUIRED_RUNTIME_COMPONENTS:
        ok = _truth(runtime.get(name))
        runtime_checks[name] = {"ready": ok}
        if not ok:
            errors.append(f"runtime-component-not-ready:{name}")
    checks["runtime_components"] = runtime_checks

    surfaces = _dict(payload.get("surface_probes"))
    surface_checks = {}
    for name in REQUIRED_SURFACE_PROBES:
        ok = _truth(surfaces.get(name))
        surface_checks[name] = {"ready": ok}
        if not ok:
            errors.append(f"critical-surface-not-ready:{name}")
    checks["surface_probes"] = surface_checks

    authority = _dict(payload.get("authority_assertions"))
    authority_checks = {}
    for name in REQUIRED_AUTHORITY_ASSERTIONS:
        ok = authority.get(name) is True
        authority_checks[name] = {"ready": ok}
        if not ok:
            errors.append(f"authority-boundary-failed:{name}")
    checks["authority_assertions"] = authority_checks

    deployment = _dict(payload.get("deployment_assertions"))
    deployment_checks = {}
    for name in REQUIRED_DEPLOYMENT_ASSERTIONS:
        ok = deployment.get(name) is True
        deployment_checks[name] = {"ready": ok}
        if not ok:
            errors.append(f"deployment-assertion-failed:{name}")
    for name, expected in (("backend_bind", "127.0.0.1:8087"), ("web_bind", "127.0.0.1:8095")):
        actual = str(deployment.get(name) or "")
        ok = actual == expected
        deployment_checks[name] = {"expected": expected, "actual": actual or None, "ready": ok}
        if not ok:
            errors.append(f"deployment-bind-mismatch:{name}:{actual or 'missing'}:{expected}")
    checks["deployment_assertions"] = deployment_checks

    preserved = _dict(payload.get("preserved_guardrails"))
    guardrail_checks = {}
    for name in REQUIRED_PRESERVED_GUARDRAILS:
        ok = preserved.get(name) is True
        guardrail_checks[name] = {"ready": ok}
        if not ok:
            errors.append(f"preserved-guardrail-failed:{name}")
    checks["preserved_guardrails"] = guardrail_checks

    evidence_basis = {
        "generations": generations,
        "runtime_components": runtime,
        "surface_probes": surfaces,
        "authority_assertions": authority,
        "deployment_assertions": deployment,
        "preserved_guardrails": preserved,
        "metadata": _dict(payload.get("metadata")),
    }
    fingerprint = _fp(evidence_basis)
    certified = not errors
    return {
        "schema": CERTIFICATION_CONTRACT,
        "certification_id": "library7-production-certification:" + fingerprint[:32],
        "certification_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": "1.0",
        "state": "certified" if certified else "blocked",
        "certified": certified,
        "library7_ready": certified,
        "errors": errors,
        "error_count": len(errors),
        "checks": checks,
        "next_release": NEXT_RELEASE,
        "next_release_name": NEXT_RELEASE_NAME,
        "metadata": _dict(payload.get("metadata")),
        "guardrails": guardrails(),
    }


def export_certification(payload: dict[str, Any]) -> dict[str, Any]:
    result = evaluate(payload)
    body = {
        "schema": "sc-library7-production-certification-package/1.0",
        "certification": result,
        "inventory": inventory(),
        "contract": contract(),
    }
    content = json.dumps(body, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-library-v6.39.0-library7-production-certification.json",
        "media_type": "application/json",
        "content": content,
        "content_sha256": sha256(content.encode("utf-8")).hexdigest(),
        "certified": result["certified"],
        "next_release": NEXT_RELEASE,
        "guardrails": guardrails(),
    }
