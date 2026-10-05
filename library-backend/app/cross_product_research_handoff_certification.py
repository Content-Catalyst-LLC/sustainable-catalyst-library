from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.29.0"
BACKEND_VERSION = "3.29.0"
WEB_VERSION = "2.29.0"
SDK_VERSION = "1.29.0"

CONTRACT = "sc-library-cross-product-research-handoff-certification/1.0"
READINESS_CONTRACT = "sc-library-cross-product-research-handoff-certification-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-cross-product-research-handoff-certification-bootstrap/1.0"
PRODUCT_CONTRACT = "sc-library-cross-product-research-contract/1.0"
HANDOFF_CONTRACT = "sc-library-cross-product-research-handoff/1.0"
HANDOFF_VALIDATION_CONTRACT = "sc-library-cross-product-research-handoff-validation/1.0"
COMPATIBILITY_CONTRACT = "sc-library-cross-product-compatibility-matrix/1.0"
FAILURE_AUDIT_CONTRACT = "sc-library-cross-product-failure-behavior-audit/1.0"
CERTIFICATION_CONTRACT = "sc-library-cross-product-contract-certification/1.0"
EXPORT_CONTRACT = "sc-library-cross-product-contract-certification-export/1.0"

PRODUCTS: dict[str, dict[str, Any]] = {
    "research-librarian": {
        "display_name": "Research Librarian AI",
        "role": "research-discovery-guidance-and-source-planning",
        "contract": "sc-research-librarian-research-exchange/1.0",
        "minimum_version": "11.6.0",
        "authority": "research-librarian",
        "accepts": ["research-project", "research-question", "investigation", "source", "citation", "knowledge-graph", "research-package"],
        "returns": ["source-plan", "research-guidance", "discovery-result", "citation-reference", "research-context"],
        "persistence": "research-librarian-owned-state-or-explicit-library-write",
    },
    "workspace": {
        "display_name": "Workspace",
        "role": "research-compute-analysis-and-reproducibility",
        "contract": "sc-workspace-research-exchange/1.0",
        "minimum_version": "3.76.0",
        "authority": "workspace",
        "accepts": ["research-project", "research-question", "investigation", "dataset", "evidence", "knowledge-graph", "research-package", "artifact"],
        "returns": ["analysis", "notebook", "dataset", "table", "figure", "model", "simulation", "calculation", "graph", "report", "artifact"],
        "persistence": "workspace-owned-execution-state-or-explicit-library-write",
    },
    "research-lab": {
        "display_name": "Research Lab",
        "role": "scientific-experimentation-model-validation-and-benchmarking",
        "contract": "sc-lab-research-exchange/1.0",
        "minimum_version": "0.155.0",
        "authority": "research-lab",
        "accepts": ["research-project", "research-question", "dataset", "evidence", "model", "knowledge-graph", "artifact"],
        "returns": ["experiment", "benchmark", "model", "metric", "figure", "table", "artifact", "report"],
        "persistence": "research-lab-owned-experiment-state-or-explicit-library-write",
    },
    "workbench": {
        "display_name": "Workbench",
        "role": "scientific-calculation-simulation-and-prototyping",
        "contract": "sc-workbench-research-exchange/1.0",
        "minimum_version": "11.6.0",
        "authority": "workbench",
        "accepts": ["research-project", "dataset", "statistical-result", "model", "artifact"],
        "returns": ["calculation", "simulation", "equation", "model", "table", "figure", "artifact", "report"],
        "persistence": "workbench-owned-compute-state-or-explicit-library-write",
    },
    "site-intelligence": {
        "display_name": "Site Intelligence",
        "role": "geospatial-earth-observation-country-and-site-analysis",
        "contract": "sc-site-intelligence-research-exchange/1.0",
        "minimum_version": "4.42.1",
        "authority": "site-intelligence",
        "accepts": ["research-project", "place", "dataset", "geospatial-layer", "source", "artifact"],
        "returns": ["site-analysis", "country-profile", "geospatial-layer", "map", "observation", "figure", "artifact", "report"],
        "persistence": "site-intelligence-owned-analysis-state-or-explicit-library-write",
    },
    "decision-studio": {
        "display_name": "Decision Studio",
        "role": "decision-packet-scenario-and-risk-analysis",
        "contract": "sc-decision-studio-research-exchange/1.0",
        "minimum_version": "2.0.1",
        "authority": "decision-studio",
        "accepts": ["research-project", "claim", "evidence", "dataset", "statistical-result", "synthesis", "research-package", "artifact"],
        "returns": ["decision-packet", "scenario", "risk-analysis", "comparison", "artifact", "report"],
        "persistence": "decision-studio-owned-decision-state-or-explicit-library-write",
    },
    "platform-core": {
        "display_name": "Platform Core",
        "role": "shared-object-contract-provenance-and-governed-meaning",
        "contract": "sc-platform-core-research-exchange/1.0",
        "minimum_version": "3.77.0",
        "authority": "platform-core",
        "accepts": ["research-object", "provenance", "citation", "evidence", "model", "artifact", "knowledge-graph", "handoff-envelope"],
        "returns": ["typed-object", "provenance-envelope", "validated-contract", "governed-link", "artifact-reference"],
        "persistence": "platform-core-owned-governed-contract-state",
    },
}

REQUIRED_CERTIFICATION_DIMENSIONS = (
    "object-types",
    "schema-version",
    "authority-boundary",
    "provenance",
    "signed-write-boundary",
    "failure-behavior",
    "backward-compatibility",
)

FAILURE_CLASSES = (
    "target-unavailable",
    "contract-version-mismatch",
    "unsupported-object-type",
    "missing-provenance",
    "unsigned-persistence-request",
    "authority-claim-conflict",
    "partial-result",
    "duplicate-delivery",
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return hashlib.sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, list) else []


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def guardrails() -> dict[str, Any]:
    return {
        "certification_is_new_product_authority": False,
        "certification_is_new_transport_authority": False,
        "library_is_remote_execution_authority": False,
        "library_is_downstream_persistence_authority": False,
        "downstream_products_are_library_authorities": False,
        "originating_product_authorities_remain_authoritative": True,
        "platform_core_governed_meaning_authority_preserved": True,
        "handoff_preserves_object_identity": True,
        "handoff_preserves_schema_version": True,
        "handoff_preserves_provenance": True,
        "persistence_requires_explicit_signed_write": True,
        "remote_runtime_observation_required_for_live_runtime_certification": True,
        "contract_certification_implies_remote_runtime_availability": False,
        "contract_certification_implies_research_truth": False,
        "transport_success_implies_research_truth": False,
        "automatic_cross_product_push_delivery": False,
        "automatic_remote_execution": False,
        "automatic_result_import": False,
        "automatic_artifact_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def product_contract(product_key: str) -> dict[str, Any]:
    if product_key not in PRODUCTS:
        raise ValueError(f"unknown product: {product_key}")
    product = PRODUCTS[product_key]
    basis = {"product_key": product_key, **product, "dimensions": REQUIRED_CERTIFICATION_DIMENSIONS}
    return {
        "schema": PRODUCT_CONTRACT,
        "product_contract_id": f"cross-product-contract:{product_key}:{_fp(basis)[:24]}",
        "product_contract_fingerprint_sha256": _fp(basis),
        "product_key": product_key,
        **product,
        "api_version": "1.0",
        "certification_dimensions": list(REQUIRED_CERTIFICATION_DIMENSIONS),
        "live_runtime_observation_required": True,
        "remote_runtime_certified": False,
        "guardrails": guardrails(),
    }


def contract() -> dict[str, Any]:
    products = {key: product_contract(key) for key in PRODUCTS}
    basis = {"products": products, "dimensions": REQUIRED_CERTIFICATION_DIMENSIONS, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "certification_registry_id": "cross-product-research-certification:" + _fp(basis)[:32],
        "certification_registry_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "contract-certification-authority",
        "authority": "library-api-contract-certification",
        "route": "/research/integration-certification",
        "api_base": "/api/library/v1/cross-product-certification",
        "product_count": len(products),
        "products": products,
        "certification_dimensions": list(REQUIRED_CERTIFICATION_DIMENSIONS),
        "next_release": "6.30.0",
        "next_release_name": "Unified Research Project Workspace",
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
        "blocking": [],
        "degraded": [],
        "authority": "library-api-contract-certification",
        "registered_product_count": len(PRODUCTS),
        "contract_certification_ready": True,
        "handoff_validation_ready": True,
        "compatibility_matrix_ready": True,
        "failure_behavior_audit_ready": True,
        "runtime_observation_framework_ready": True,
        "all_product_contracts_structurally_certifiable": True,
        "all_product_live_runtimes_certified": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/integration-certification",
        "readiness": readiness(),
        "products": [{"product_key": key, "display_name": value["display_name"], "role": value["role"], "minimum_version": value["minimum_version"]} for key, value in PRODUCTS.items()],
        "operations": [
            "product-contract",
            "handoff",
            "validate-handoff",
            "compatibility-matrix",
            "failure-behavior-audit",
            "certify",
            "export",
        ],
        "failure_classes": list(FAILURE_CLASSES),
        "certification_dimensions": list(REQUIRED_CERTIFICATION_DIMENSIONS),
        "browser_storage_key": "sc-library-cross-product-certification-v1",
        "guardrails": guardrails(),
    }


def build_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    source_product = _clean(payload.get("source_product")) or "library"
    target_product = _clean(payload.get("target_product"))
    if not target_product or target_product not in PRODUCTS:
        raise ValueError("target_product must be a registered cross-product target")
    object_refs = []
    for index, raw in enumerate(_list(payload.get("object_refs"))):
        item = _dict(raw)
        object_id = _clean(item.get("object_id") or item.get("id") or item.get("ref"))
        object_type = _clean(item.get("object_type") or item.get("type") or item.get("kind"))
        authority = _clean(item.get("authority"))
        if not object_id or not object_type or not authority:
            raise ValueError(f"object_refs[{index}] requires object_id, object_type, and authority")
        object_refs.append({
            "object_id": object_id,
            "object_type": object_type,
            "authority": authority,
            "version": _clean(item.get("version")),
            "content_fingerprint_sha256": _clean(item.get("content_fingerprint_sha256")),
            "provenance": _dict(item.get("provenance")),
        })
    if not object_refs:
        raise ValueError("at least one object_ref is required")
    intent = _clean(payload.get("intent")) or "continue-research"
    correlation = {
        "library_project_id": _clean(payload.get("library_project_id")),
        "target_project_id": _clean(payload.get("target_project_id")),
        "parent_handoff_id": _clean(payload.get("parent_handoff_id")),
    }
    target = product_contract(target_product)
    basis = {
        "source_product": source_product,
        "target_product": target_product,
        "intent": intent,
        "object_refs": object_refs,
        "correlation": correlation,
        "target_contract_fingerprint_sha256": target["product_contract_fingerprint_sha256"],
    }
    fingerprint = _fp(basis)
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "cross-product-handoff:" + fingerprint[:32],
        "handoff_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_product": source_product,
        "target_product": target_product,
        "intent": intent,
        "object_refs": object_refs,
        "correlation": correlation,
        "target_contract": {
            "schema": target["contract"],
            "minimum_version": target["minimum_version"],
            "contract_fingerprint_sha256": target["product_contract_fingerprint_sha256"],
        },
        "provenance_preserved": True,
        "signed_persistence_required": True,
        "transport": {"mode": "explicit-consumer-pull-or-signed-delivery", "automatic_push": False, "remote_execution_started": False},
        "persisted": False,
        "guardrails": guardrails(),
    }


def validate_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    packet = _dict(payload.get("handoff")) if "handoff" in _dict(payload) else _dict(payload)
    errors: list[str] = []
    warnings: list[str] = []
    if packet.get("schema") != HANDOFF_CONTRACT:
        errors.append("handoff schema mismatch")
    target_key = _clean(packet.get("target_product"))
    if not target_key or target_key not in PRODUCTS:
        errors.append("target product is not registered")
    object_refs = _list(packet.get("object_refs"))
    if not object_refs:
        errors.append("object_refs are required")
    for index, raw in enumerate(object_refs):
        item = _dict(raw)
        if not _clean(item.get("object_id")):
            errors.append(f"object_refs[{index}] object_id is required")
        if not _clean(item.get("object_type")):
            errors.append(f"object_refs[{index}] object_type is required")
        if not _clean(item.get("authority")):
            errors.append(f"object_refs[{index}] authority is required")
        if not _dict(item.get("provenance")):
            warnings.append(f"object_refs[{index}] has no expanded provenance payload")
    if _dict(packet.get("transport")).get("automatic_push") is True:
        errors.append("automatic push is prohibited")
    if packet.get("signed_persistence_required") is not True:
        errors.append("signed persistence boundary must be explicit")
    if target_key in PRODUCTS:
        target = product_contract(target_key)
        supplied = _clean(_dict(packet.get("target_contract")).get("contract_fingerprint_sha256"))
        if supplied and supplied != target["product_contract_fingerprint_sha256"]:
            errors.append("target contract fingerprint mismatch")
    return {
        "schema": HANDOFF_VALIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "object_count": len(object_refs),
        "authority_boundaries_preserved": not any("authority" in x for x in errors),
        "signed_persistence_boundary_preserved": "signed persistence boundary must be explicit" not in errors,
        "remote_execution_started": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def compatibility_matrix(payload: dict[str, Any] | None = None) -> dict[str, Any]:
    payload = _dict(payload)
    observations = _dict(payload.get("observations"))
    rows = []
    for key in PRODUCTS:
        contract_row = product_contract(key)
        observation = _dict(observations.get(key))
        observed_version = _clean(observation.get("version"))
        observed_contract = _clean(observation.get("contract"))
        structural = True
        live_observed = bool(observation.get("reachable"))
        contract_match = observed_contract == contract_row["contract"] if observed_contract else None
        rows.append({
            "product_key": key,
            "display_name": contract_row["display_name"],
            "minimum_version": contract_row["minimum_version"],
            "expected_contract": contract_row["contract"],
            "observed_version": observed_version,
            "observed_contract": observed_contract,
            "structural_contract_ready": structural,
            "live_runtime_observed": live_observed,
            "observed_contract_match": contract_match,
            "backward_compatibility_policy": "same-major-contract; additive-minor-fields; unknown-fields-tolerated",
        })
    return {
        "schema": COMPATIBILITY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "product_count": len(rows),
        "all_structural_contracts_ready": all(row["structural_contract_ready"] for row in rows),
        "all_live_runtimes_observed": all(row["live_runtime_observed"] for row in rows),
        "live_runtime_observation_count": sum(1 for row in rows if row["live_runtime_observed"]),
        "guardrails": guardrails(),
    }


def failure_behavior_audit(payload: dict[str, Any] | None = None) -> dict[str, Any]:
    payload = _dict(payload)
    behaviors = {
        "target-unavailable": "retain-source-state-and-return-retryable-failure",
        "contract-version-mismatch": "reject-handoff-before-persistence",
        "unsupported-object-type": "reject-offending-object-without-coercion",
        "missing-provenance": "degrade-or-reject-per-contract-never-invent-lineage",
        "unsigned-persistence-request": "reject-write",
        "authority-claim-conflict": "reject-authority-escalation",
        "partial-result": "preserve-partial-state-label-and-provenance",
        "duplicate-delivery": "require-idempotency-key-or-content-fingerprint",
    }
    requested = _list(payload.get("failure_classes")) or list(FAILURE_CLASSES)
    unknown = [x for x in requested if x not in behaviors]
    audited = [{"failure_class": key, "expected_behavior": behaviors[key], "data_loss_permitted": False, "truth_promotion_permitted": False} for key in requested if key in behaviors]
    return {
        "schema": FAILURE_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "pass" if not unknown else "review-required",
        "unknown_failure_classes": unknown,
        "audited": audited,
        "failure_count": len(audited),
        "guardrails": guardrails(),
    }


def certify(payload: dict[str, Any] | None = None) -> dict[str, Any]:
    payload = _dict(payload)
    observations = _dict(payload.get("observations"))
    matrix = compatibility_matrix({"observations": observations})
    failure_audit = failure_behavior_audit(payload)
    product_results = []
    for row in matrix["rows"]:
        obs = _dict(observations.get(row["product_key"]))
        runtime_observed = row["live_runtime_observed"]
        contract_match = row["observed_contract_match"]
        live_certified = bool(runtime_observed and contract_match is True and obs.get("authority_boundary_preserved") is True and obs.get("provenance_preserved") is True and obs.get("signed_write_boundary_preserved") is True)
        product_results.append({
            "product_key": row["product_key"],
            "structural_contract_certified": True,
            "live_runtime_observed": runtime_observed,
            "live_runtime_certified": live_certified,
            "observation_fingerprint_sha256": _fp(obs) if obs else None,
        })
    all_structural = all(x["structural_contract_certified"] for x in product_results)
    all_live = all(x["live_runtime_certified"] for x in product_results)
    basis = {"product_results": product_results, "failure_audit": failure_audit, "matrix": matrix}
    return {
        "schema": CERTIFICATION_CONTRACT,
        "certification_id": "cross-product-certification:" + _fp(basis)[:32],
        "certification_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "certified" if all_structural and failure_audit["state"] == "pass" else "review-required",
        "structural_contract_certification": all_structural,
        "failure_behavior_certification": failure_audit["state"] == "pass",
        "all_product_live_runtimes_certified": all_live,
        "live_runtime_certification_requires_observations": True,
        "products": product_results,
        "compatibility_matrix": matrix,
        "failure_behavior_audit": failure_audit,
        "persisted": False,
        "guardrails": guardrails(),
    }


def export_certification(payload: dict[str, Any] | None = None) -> dict[str, Any]:
    certification = certify(payload)
    content_obj = {
        "schema": "sc-library-cross-product-contract-certification-bundle/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "registry": contract(),
        "certification": certification,
        "guardrails": guardrails(),
    }
    content = json.dumps(content_obj, sort_keys=True, indent=2, ensure_ascii=False) + "\n"
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-cross-product-research-contract-certification.json",
        "media_type": "application/json",
        "sha256": hashlib.sha256(content.encode("utf-8")).hexdigest(),
        "content": content,
        "persisted": False,
        "guardrails": guardrails(),
    }
