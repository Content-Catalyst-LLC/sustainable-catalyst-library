from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .global_source_federation import registry
from .global_knowledge_federation import (
    build_default_certification,
    ingest_certification,
    readiness as global_knowledge_readiness,
    validate_certification_payload,
)

LIBRARY_VERSION = "6.2.0"
BACKEND_VERSION = "3.2.0"
CONTRACT = "sc-library-python-connector-federation-runtime/1.0"
READINESS_CONTRACT = "sc-library-python-connector-federation-readiness/1.0"
EXECUTION_PLAN_CONTRACT = "sc-library-connector-execution-plan/1.0"
CONNECTOR_STATUS_CONTRACT = "sc-library-connector-runtime-status/1.0"


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any) -> str:
    return str(value or "").strip().lower()


def guardrails() -> dict[str, bool]:
    return {
        "python_is_connector_federation_authority": True,
        "wordpress_php_is_connector_authority": False,
        "wordpress_connector_fallback_allowed": False,
        "registry_membership_implies_source_quality": False,
        "registry_membership_implies_evidence_truth": False,
        "source_quality_signals_separate_from_user_trust_choices": True,
        "raw_source_preservation_required": True,
        "original_language_preserved_as_received": True,
        "automatic_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "browser_handoff_is_connector_execution": False,
        "pending_adapter_is_silently_executed": False,
    }


def _mode(connector: dict[str, Any]) -> str:
    authority = _clean(connector.get("execution_authority"))
    connector_id = _clean(connector.get("connector_id"))
    if authority.startswith("python-") or connector_id.startswith("python-"):
        return "python-native"
    if authority == "browser-handoff" or connector_id.startswith("browser-"):
        return "browser-handoff"
    if "wordpress" in authority or connector_id.startswith("wordpress-"):
        return "python-adapter-pending"
    return "registry-only"


def _connector_status_from_bundle(bundle: dict[str, Any]) -> dict[str, Any]:
    connector = dict(bundle.get("connector") or {})
    source = dict(bundle.get("source") or {})
    mode = _mode(connector)
    executable = mode == "python-native"
    browser_handoff = mode == "browser-handoff"
    status_basis = {
        "connector_id": connector.get("connector_id"),
        "source_id": connector.get("source_id"),
        "mode": mode,
        "capabilities": connector.get("capabilities") or [],
        "transport": connector.get("transport"),
        "authentication": connector.get("authentication"),
    }
    return {
        "schema": CONNECTOR_STATUS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "connector_id": connector.get("connector_id"),
        "source_id": connector.get("source_id"),
        "source_name": source.get("name"),
        "mode": mode,
        "python_native": executable,
        "browser_handoff": browser_handoff,
        "executable_by_library_backend": executable,
        "wordpress_fallback_allowed": False,
        "capabilities": connector.get("capabilities") or [],
        "transport": connector.get("transport"),
        "authentication": connector.get("authentication"),
        "pagination": connector.get("pagination"),
        "rate_limit_policy": connector.get("rate_limit_policy"),
        "provenance_fields": connector.get("provenance_fields") or [],
        "language_policy": connector.get("language_policy"),
        "translation_behavior": connector.get("translation_behavior"),
        "runtime_status_fingerprint_sha256": _fp(status_basis),
        "guardrails": guardrails(),
    }


def contract() -> dict[str, Any]:
    registry_ready = registry.readiness()
    resources = [
        "global-source-registry",
        "source-descriptors",
        "collection-descriptors",
        "connector-contracts",
        "connector-runtime-status",
        "connector-execution-plans",
        "connector-manifest-validation",
        "global-knowledge-federation-certification",
    ]
    basis = {
        "authority": "python-backend",
        "registry_fingerprint_sha256": registry_ready.get("registry_fingerprint_sha256"),
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "service_id": "library-connector-federation:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative",
        "authority": "python-backend",
        "resources": resources,
        "registry_fingerprint_sha256": registry_ready.get("registry_fingerprint_sha256"),
        "wordpress": {
            "role": "presentation-and-api-client",
            "required": False,
            "authoritative": False,
            "connector_fallback_allowed": False,
        },
        "guardrails": guardrails(),
    }


def snapshot(*, family: str = "", capability: str = "", collection: str = "", authority: str = "", q: str = "") -> dict[str, Any]:
    payload = registry.snapshot(family=family, capability=capability, collection=collection, authority=authority, q=q)
    payload["runtime"] = {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "wordpress_required": False,
    }
    return payload


def source(source_id: str) -> dict[str, Any]:
    payload = registry.source(source_id)
    payload["runtime_status"] = _connector_status_from_bundle(payload)
    return payload


def connector(connector_id: str) -> dict[str, Any]:
    payload = registry.connector(connector_id)
    payload["runtime_status"] = _connector_status_from_bundle(payload)
    return payload


def collections() -> dict[str, Any]:
    payload = registry.collections()
    payload["runtime"] = {"library_version": LIBRARY_VERSION, "backend_version": BACKEND_VERSION, "authority": "python-backend"}
    return payload


def connector_status(connector_id: str) -> dict[str, Any]:
    return _connector_status_from_bundle(registry.connector(connector_id))


def validate_connector(payload: dict[str, Any]) -> dict[str, Any]:
    result = registry.validate_connector_manifest(payload)
    result["library_version"] = LIBRARY_VERSION
    result["backend_version"] = BACKEND_VERSION
    result["authority"] = "python-backend"
    result["guardrails"] = {**dict(result.get("governance") or {}), **guardrails()}
    return result


def plan_execution(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    connector_id = _clean(payload.get("connector_id"))
    source_id = _clean(payload.get("source_id"))
    capability = _clean(payload.get("capability"))
    if not connector_id and not source_id:
        raise ValueError("connector-id-or-source-id-required")
    bundle = registry.connector(connector_id) if connector_id else registry.source(source_id)
    status = _connector_status_from_bundle(bundle)
    capabilities = {_clean(x) for x in status.get("capabilities") or []}
    if capability and capability not in capabilities:
        raise ValueError("connector-capability-not-supported")
    mode = status["mode"]
    permitted = mode in {"python-native", "browser-handoff"}
    action = "execute-python-adapter" if mode == "python-native" else "open-browser-handoff" if mode == "browser-handoff" else "await-python-adapter"
    request_basis = {
        "connector_id": status.get("connector_id"),
        "source_id": status.get("source_id"),
        "capability": capability or None,
        "mode": mode,
        "query": payload.get("query") if isinstance(payload.get("query"), dict) else {},
        "options": payload.get("options") if isinstance(payload.get("options"), dict) else {},
    }
    return {
        "schema": EXECUTION_PLAN_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "plan_id": "connector-plan:" + _fp(request_basis)[:32],
        "plan_fingerprint_sha256": _fp(request_basis),
        "connector_id": status.get("connector_id"),
        "source_id": status.get("source_id"),
        "capability": capability or None,
        "mode": mode,
        "permitted": permitted,
        "action": action,
        "python_backend_executes": mode == "python-native",
        "browser_handoff": mode == "browser-handoff",
        "wordpress_fallback_allowed": False,
        "query": request_basis["query"],
        "options": request_basis["options"],
        "connector_status": status,
        "guardrails": guardrails(),
    }


def federation_certification(*, persist: bool = False) -> dict[str, Any]:
    certification = build_default_certification()
    if persist:
        certification = ingest_certification({
            "components": certification.get("components") or {},
            "scope": {"runtime": "python-connector-federation-v5.77.0"},
            "provenance": {"authority": "python-backend", "basis": "connector-federation-runtime-certification"},
        })
    certification["runtime"] = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "wordpress_required": False,
    }
    return certification


def validate_federation_certification(payload: dict[str, Any]) -> dict[str, Any]:
    result = validate_certification_payload(payload)
    result["library_version"] = LIBRARY_VERSION
    result["backend_version"] = BACKEND_VERSION
    result["authority"] = "python-backend"
    return result


def readiness() -> dict[str, Any]:
    registry_ready = registry.readiness()
    knowledge_ready = global_knowledge_readiness()
    snap = registry.snapshot()
    modes = {"python-native": 0, "browser-handoff": 0, "python-adapter-pending": 0, "registry-only": 0}
    for item in snap.get("connectors") or []:
        modes[_mode(item)] = modes.get(_mode(item), 0) + 1
    state = "ready" if registry_ready.get("state") == "ready" and knowledge_ready.get("state") in {"ready", "degraded"} else "degraded"
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "authority": "python-backend",
        "wordpress_required": False,
        "wordpress_fallback_allowed": False,
        "registry": registry_ready,
        "global_knowledge_federation": {
            "state": knowledge_ready.get("state"),
            "database": knowledge_ready.get("database"),
            "component_count": knowledge_ready.get("component_count"),
            "ready_component_count": knowledge_ready.get("ready_component_count"),
        },
        "connector_modes": modes,
        "source_count": (snap.get("counts") or {}).get("sources", 0),
        "connector_count": (snap.get("counts") or {}).get("connectors", 0),
        "guardrails": guardrails(),
    }
