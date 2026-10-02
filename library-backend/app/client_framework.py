from __future__ import annotations
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "5.78.0"
BACKEND_VERSION = "2.89.0"
SDK_VERSION = "0.10.0"
CONTRACT = "sc-library-client-framework/1.0"
READINESS_CONTRACT = "sc-library-client-framework-readiness/1.0"

PRODUCT_ADAPTERS = (
    "research-librarian", "workspace", "research-lab",
    "workbench", "decision-studio", "site-intelligence",
)


def _canon(v: Any) -> str:
    return json.dumps(v, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(v: Any) -> str:
    return sha256(_canon(v).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "wordpress_required_for_sdk": False,
        "sdk_is_runtime_authority": False,
        "sdk_may_bypass_api_scope_checks": False,
        "sdk_persists_credentials": False,
        "signed_writes_use_api_v1_signature_contract": True,
        "clients_ignore_unknown_additive_fields": True,
        "retries_are_transport_only": True,
        "retry_success_implies_research_truth": False,
        "automatic_platform_core_promotion": False,
    }


def contract() -> dict[str, Any]:
    basis = {
        "sdk_version": SDK_VERSION,
        "api_version": "1.0",
        "clients": ["python", "javascript", "typescript"],
        "product_adapters": list(PRODUCT_ADAPTERS),
        "guardrails": guardrails(),
    }
    fp = _fp(basis)
    return {
        "schema": CONTRACT,
        "framework_id": "library-client-framework:" + fp[:32],
        "framework_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "sdk_version": SDK_VERSION,
        "api_version": "1.0",
        "base_path": "/api/library/v1",
        "clients": {
            "python": {"package": "sustainable-catalyst-library", "version": SDK_VERSION, "typed": True},
            "javascript": {"package": "@sustainable-catalyst/library-client", "version": SDK_VERSION, "esm": True},
            "typescript": {"package": "@sustainable-catalyst/library-client", "version": SDK_VERSION, "declarations": True},
        },
        "features": {
            "public_reads": True,
            "signed_writes": True,
            "capability_discovery": True,
            "stable_error_mapping": True,
            "bounded_transport_retries": True,
            "session_passthrough": True,
            "cross_product_adapters": True,
            "catalog_domain_client": True,
            "research_object_client": True,
            "research_state_client": True,
            "source_ingestion_client": True,
            "retrieval_orchestration_client": True,
            "provenance_citation_evidence_graph_client": True,
            "language_document_processing_client": True,
            "research_package_reproducibility_client": True,
            "connector_federation_client": True,
            "background_job_workflow_client": True,
        },
        "product_adapters": list(PRODUCT_ADAPTERS),
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    c = contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "client_count": len(c["clients"]),
        "product_adapter_count": len(PRODUCT_ADAPTERS),
        "wordpress_required": False,
        "features": c["features"],
        "guardrails": c["guardrails"],
    }
