from __future__ import annotations

from hashlib import sha256
import json
from typing import Any, Callable

from .independent_api import readiness as api_readiness
from .runtime_authority import readiness as runtime_authority_readiness
from .runtime_independence import readiness as wordpress_failure_readiness
from .wordpress_thin_adapter import readiness as wordpress_adapter_readiness
from .web_application import readiness as web_application_readiness
from .identity_access import readiness as identity_access_readiness
from .release_engineering import readiness as release_engineering_readiness
from .state_migration import readiness as state_migration_readiness
from .client_framework import readiness as client_framework_readiness
from .domain_authority import readiness as domain_authority_readiness
from .catalog_service import readiness as catalog_readiness
from .research_state import readiness as research_state_readiness
from .source_ingestion import readiness as ingestion_readiness
from .retrieval_orchestration import readiness as retrieval_readiness
from .provenance_graph_service import readiness as provenance_readiness
from .language_document_service import readiness as language_readiness
from .research_package_service import readiness as reproducibility_readiness
from .connector_federation_service import readiness as federation_readiness
from .background_workflow_service import readiness as workflow_readiness
from .artifact_storage import artifact_storage_readiness
from .checkpointed_pipeline import pipeline_readiness
from .distributed_compute_broker import broker_readiness as compute_broker_readiness
from .cross_product_integration import readiness as cross_product_readiness
from .public_routing import readiness as public_routing_readiness

LIBRARY_VERSION = "6.2.0"
BACKEND_VERSION = "3.2.0"
CONTRACT = "sc-library-independent-application-certification-service/1.0"
READINESS_CONTRACT = "sc-library-independent-application-certification-readiness/1.0"
CERTIFICATION_CONTRACT = "sc-library-independent-application-certification/1.0"

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def guardrails() -> dict[str, bool]:
    return {
        "wordpress_required_for_library_runtime": False,
        "wordpress_required_for_api_v1": False,
        "wordpress_required_for_library_web": False,
        "wordpress_required_for_identity_sessions": False,
        "wordpress_required_for_research_execution": False,
        "wordpress_required_for_cross_product_clients": False,
        "legacy_php_compatibility_may_remain": True,
        "legacy_php_compatibility_is_domain_authority": False,
        "php_domain_authority_files": False,
        "postgresql_is_structured_state_authority": True,
        "library_api_v1_is_application_boundary": True,
        "python_backend_is_application_runtime": True,
        "optional_compute_degradation_is_advisory": True,
        "certification_implies_research_truth": False,
        "certification_implies_source_validity": False,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str, Any]:
    critical = [
        "api-v1",
        "runtime-authority",
        "wordpress-failure-independence",
        "wordpress-thin-adapter",
        "web-application",
        "identity-access",
        "release-engineering",
        "state-migration",
        "client-framework",
        "domain-authority",
        "catalog-service",
        "research-state",
        "source-ingestion",
        "retrieval-orchestration",
        "provenance-graph",
        "language-document",
        "reproducibility-service",
        "connector-federation",
        "workflow-service",
        "artifact-storage",
        "pipeline-engine",
        "cross-product-integrations",
        "public-routing",
    ]
    advisory = ["distributed-compute-broker"]
    basis = {
        "critical_probes": critical,
        "advisory_probes": advisory,
        "wordpress_dependency_count_required": 0,
        "certified_release": "6.0.0",
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "service_id": "independent-library-application-certification:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "state": "certification-gate",
        "authority": "python-backend",
        "critical_probes": critical,
        "advisory_probes": advisory,
        "wordpress": {
            "required": False,
            "authoritative": False,
            "role": "optional-thin-adapter",
            "dependency_count_required": 0,
        },
        "legacy_php": {
            "domain_authority_files": 0,
            "compatibility_surface_may_remain": True,
            "mass_deletion_required_for_certification": False,
        },
        "certified_release": "6.0.0",
        "certified_release_name": "Independent Sustainable Catalyst Knowledge Library",
        "guardrails": guardrails(),
    }

def _version(result: dict[str, Any]) -> str | None:
    value = result.get("library_version")
    if value is None:
        value = result.get("version")
    return str(value) if value is not None else None

def _probe(
    name: str,
    fn: Callable[[], dict[str, Any]],
    *,
    critical: bool,
    release_identity: bool = True,
    require_database: bool = False,
    allowed_states: tuple[str, ...] = ("ready",),
    extra: Callable[[dict[str, Any]], list[str]] | None = None,
) -> dict[str, Any]:
    errors: list[str] = []
    try:
        result = fn()
    except Exception as exc:
        return {
            "name": name,
            "critical": critical,
            "ready": False,
            "state": "error",
            "errors": [f"probe-exception:{type(exc).__name__}:{exc}"],
        }

    if not isinstance(result, dict):
        return {
            "name": name,
            "critical": critical,
            "ready": False,
            "state": "invalid",
            "errors": ["probe-result-must-be-object"],
        }

    state = str(result.get("state") or "unknown")
    if state not in allowed_states:
        errors.append(f"state-not-ready:{state}")

    if release_identity:
        lv = _version(result)
        bv = result.get("backend_version")
        if lv is not None and lv != LIBRARY_VERSION:
            errors.append(f"library-version-mismatch:{lv}")
        if bv is not None and str(bv) != BACKEND_VERSION:
            errors.append(f"backend-version-mismatch:{bv}")

    if result.get("wordpress_required") is True:
        errors.append("wordpress-required")
    if int(result.get("wordpress_dependency_count") or 0) != 0:
        errors.append("wordpress-dependency-count-nonzero")

    if require_database and str(result.get("database") or "") != "ready":
        errors.append("database-not-ready")

    if extra is not None:
        errors.extend(extra(result))

    summary: dict[str, Any] = {
        "schema": result.get("schema"),
        "state": state,
    }
    for key in (
        "library_version",
        "version",
        "backend_version",
        "database",
        "wordpress_required",
        "wordpress_dependency_count",
        "authority",
        "sdk_version",
        "web_version",
        "public_origin",
        "product_count",
    ):
        if key in result:
            summary[key] = result.get(key)

    return {
        "name": name,
        "critical": critical,
        "ready": not errors,
        "state": state,
        "errors": errors,
        "summary": summary,
    }

def _workflow_extra(result: dict[str, Any]) -> list[str]:
    states = result.get("component_states") if isinstance(result.get("component_states"), dict) else {}
    errors = []
    for key in ("execution_postgresql", "worker_runtime", "pipeline_engine"):
        if str(states.get(key) or "") != "ready":
            errors.append(f"workflow-component-not-ready:{key}:{states.get(key)}")
    return errors

def _cross_product_extra(result: dict[str, Any]) -> list[str]:
    errors = []
    if str(result.get("database") or "") != "ready":
        errors.append("cross-product-database-not-ready")
    if int(result.get("product_count") or 0) != 6:
        errors.append("cross-product-registry-count-must-be-6")
    if result.get("direct_library_api") is not True:
        errors.append("cross-product-direct-library-api-required")
    return errors

def live_probe_snapshot() -> dict[str, Any]:
    critical_specs = [
        ("api-v1", api_readiness, True, True, ("ready",), None),
        ("runtime-authority", runtime_authority_readiness, True, True, ("ready",), None),
        ("wordpress-failure-independence", wordpress_failure_readiness, True, False, ("ready",), None),
        ("wordpress-thin-adapter", wordpress_adapter_readiness, True, False, ("ready",), None),
        ("web-application", web_application_readiness, True, False, ("ready",), None),
        ("identity-access", identity_access_readiness, True, True, ("ready",), None),
        ("release-engineering", release_engineering_readiness, True, False, ("ready",), None),
        ("state-migration", state_migration_readiness, True, True, ("ready",), None),
        ("client-framework", client_framework_readiness, True, False, ("ready",), None),
        ("domain-authority", domain_authority_readiness, True, False, ("ready",), None),
        ("catalog-service", catalog_readiness, True, True, ("ready",), None),
        ("research-state", research_state_readiness, True, True, ("ready",), None),
        ("source-ingestion", ingestion_readiness, True, True, ("ready",), None),
        ("retrieval-orchestration", retrieval_readiness, True, False, ("ready",), None),
        ("provenance-graph", provenance_readiness, True, True, ("ready",), None),
        ("language-document", language_readiness, True, False, ("ready",), None),
        ("reproducibility-service", reproducibility_readiness, True, False, ("ready",), None),
        ("connector-federation", federation_readiness, True, False, ("ready",), None),
        ("workflow-service", workflow_readiness, True, False, ("ready",), _workflow_extra),
        ("artifact-storage", artifact_storage_readiness, False, False, ("ready",), None),
        ("pipeline-engine", pipeline_readiness, False, False, ("ready",), None),
        ("cross-product-integrations", cross_product_readiness, True, False, ("ready",), _cross_product_extra),
        ("public-routing", public_routing_readiness, True, False, ("ready",), None),
    ]

    critical: dict[str, Any] = {}
    for name, fn, identity, db, states, extra in critical_specs:
        critical[name] = _probe(
            name,
            fn,
            critical=True,
            release_identity=identity,
            require_database=db,
            allowed_states=states,
            extra=extra,
        )

    advisory = {
        "distributed-compute-broker": _probe(
            "distributed-compute-broker",
            compute_broker_readiness,
            critical=False,
            release_identity=False,
            allowed_states=("ready", "degraded"),
        )
    }

    return {
        "schema": "sc-library-independent-application-live-probes/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "critical": critical,
        "advisory": advisory,
        "guardrails": guardrails(),
    }

def build_certification(payload: dict[str, Any] | None = None) -> dict[str, Any]:
    payload = dict(payload or {})
    snapshot = live_probe_snapshot()
    errors: list[str] = []

    for name, probe in snapshot["critical"].items():
        if probe.get("ready") is not True:
            errors.append("critical-probe-failed:" + name)

    require_advisory = payload.get("require_advisory") is True
    if require_advisory:
        for name, probe in snapshot["advisory"].items():
            if probe.get("ready") is not True:
                errors.append("advisory-probe-required-but-not-ready:" + name)

    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "critical": snapshot["critical"],
        "advisory": snapshot["advisory"],
        "require_advisory": require_advisory,
        "wordpress_required": False,
        "wordpress_dependency_count": 0,
        "legacy_php_domain_authority": False,
        "certified_release": "6.0.0",
    }
    fingerprint = _fp(basis)

    return {
        "schema": CERTIFICATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "certification_id": "independent-library-app-cert:" + fingerprint[:32],
        "certification_fingerprint_sha256": fingerprint,
        "certified": not errors,
        "state": "certified" if not errors else "blocked",
        "errors": errors,
        "critical_probe_count": len(snapshot["critical"]),
        "critical_ready_count": sum(1 for x in snapshot["critical"].values() if x.get("ready") is True),
        "advisory_probe_count": len(snapshot["advisory"]),
        "advisory_ready_count": sum(1 for x in snapshot["advisory"].values() if x.get("ready") is True),
        "wordpress_required": False,
        "wordpress_dependency_count": 0,
        "wordpress_role": "optional-thin-adapter",
        "legacy_php_domain_authority": False,
        "legacy_php_compatibility_may_remain": True,
        "library_api_authority": "library-api",
        "python_runtime_authority": "python-backend",
        "structured_state_authority": "postgresql",
        "certified_release": "6.0.0",
        "certified_release_name": "Independent Sustainable Catalyst Knowledge Library",
        "probes": snapshot,
        "metadata": dict(payload.get("metadata") or {}),
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    certification = build_certification()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if certification["certified"] else "blocked",
        "certified": certification["certified"],
        "critical_probe_count": certification["critical_probe_count"],
        "critical_ready_count": certification["critical_ready_count"],
        "wordpress_required": False,
        "wordpress_dependency_count": 0,
        "certified_release": "6.0.0",
        "certification": certification,
        "guardrails": guardrails(),
    }
