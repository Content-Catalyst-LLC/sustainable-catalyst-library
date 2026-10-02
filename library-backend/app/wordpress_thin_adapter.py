from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .runtime_authority import dependency_graph, guardrails as runtime_guardrails
from .identity_access import boundary_contract as identity_boundary_contract

LIBRARY_VERSION = "5.80.0"
BACKEND_VERSION = "2.91.0"
CONTRACT = "sc-library-wordpress-thin-adapter/1.0"
READINESS_CONTRACT = "sc-library-wordpress-thin-adapter-readiness/1.0"
CONSOLIDATION_CONTRACT = "sc-library-wordpress-thin-adapter-consolidation/1.0"
CERTIFICATION_CONTRACT = "sc-library-wordpress-thin-adapter-certification/1.0"

ALLOWED_RESPONSIBILITIES = (
    "public-routing", "seo-and-public-metadata", "launch-and-embed-surfaces",
    "health-and-status-display", "optional-identity-handoff",
    "legacy-presentation-compatibility", "api-client-adaptation",
)

PROHIBITED_AUTHORITIES = (
    "publication-catalog-write-authority", "domain-logic-authority",
    "research-object-authority", "research-state-authority", "project-state-authority",
    "collection-state-authority", "saved-research-state-authority",
    "source-ingestion-authority", "source-normalization-authority",
    "retrieval-orchestration-authority", "search-ranking-authority",
    "provenance-authority", "citation-authority", "evidence-graph-authority",
    "language-intelligence-authority", "original-language-authority",
    "ocr-htr-transcription-authority", "linguistic-corpus-authority",
    "cross-language-resolution-authority", "translation-alignment-authority",
    "document-intelligence-authority", "research-package-authority",
    "reproducibility-authority", "research-execution-authority",
    "research-job-authority", "background-workflow-authority",
    "worker-routing-authority", "pipeline-execution-authority", "artifact-authority",
    "pipeline-authority", "compute-authority", "identity-authority", "session-authority",
    "credential-authority", "federation-authority", "connector-routing-authority",
    "connector-execution-policy-authority", "global-source-registry-authority",
    "trust-policy-authority", "platform-core-promotion-authority",
)

def _canon(v: Any) -> str:
    return json.dumps(v, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(v: Any) -> str:
    return sha256(_canon(v).encode("utf-8")).hexdigest()

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
        "legacy_php_domain_modules_are_compatibility_only": True,
        "new_domain_behavior_must_use_library_api_v1": True,
        "mass_legacy_php_deletion_required_for_v579": False,
        "wordpress_can_promote_to_platform_core": False,
        "new_research_capability_may_require_wordpress": False,
    }

def consolidation_manifest() -> dict[str, Any]:
    basis = {
        "phase": "v5.79.0",
        "allowed_responsibilities": list(ALLOWED_RESPONSIBILITIES),
        "prohibited_authorities": list(PROHIBITED_AUTHORITIES),
        "guardrails": guardrails(),
    }
    return {
        "schema": CONSOLIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "phase": "wordpress-thin-adapter-consolidation",
        "state": "consolidated-contract",
        "authority": "python-backend-contract",
        "wordpress_role": "optional-thin-adapter",
        "wordpress_authoritative": False,
        "library_api_authority": "library-api",
        "php_domain_authority_files": 0,
        "legacy_compatibility_present": True,
        "mass_legacy_deletion_performed": False,
        "next_gate": "v5.80.0-independent-library-application-certification",
        "allowed_responsibilities": list(ALLOWED_RESPONSIBILITIES),
        "prohibited_authorities": list(PROHIBITED_AUTHORITIES),
        "manifest_fingerprint_sha256": _fp(basis),
        "guardrails": guardrails(),
    }

def contract() -> dict[str, Any]:
    graph = dependency_graph()
    identity = identity_boundary_contract()
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "adapter": {
            "id": "wordpress", "role": "thin-adapter", "authoritative": False,
            "required_for_research_execution": False, "required_for_library_web": False,
            "consolidated": True,
        },
        "allowed_responsibilities": list(ALLOWED_RESPONSIBILITIES),
        "prohibited_authorities": list(PROHIBITED_AUTHORITIES),
        "authoritative_services": [
            "library-api", "python-backend", "postgresql", "research-workers",
            "artifact-storage", "pipeline-engine", "compute-broker",
            "library-identity-session-access", "global-knowledge-federation",
            "background-workflow-service", "platform-core-contracts",
        ],
        "identity_handoff": {
            "supported_as_adapter_role": True, "authoritative": False,
            "wordpress_cookie_is_library_session": False,
            "library_session_authority": identity.get("session_authority", "library-service"),
        },
        "consolidation": consolidation_manifest(),
        "dependency_graph": graph,
        "runtime_guardrails": runtime_guardrails(),
        "guardrails": guardrails(),
    }

def validate_adapter_claim(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload = dict(payload or {})
    errors = []
    if payload.get("wordpress_authoritative") is True: errors.append("wordpress-authority-prohibited")
    if payload.get("wordpress_required_for_research_execution") is True: errors.append("wordpress-research-runtime-dependency-prohibited")
    if payload.get("wordpress_owns_sessions") is True: errors.append("wordpress-session-authority-prohibited")
    if payload.get("wordpress_cookie_is_library_session") is True: errors.append("wordpress-cookie-session-equivalence-prohibited")
    if payload.get("automatic_platform_core_promotion") is True: errors.append("automatic-platform-core-promotion-prohibited")
    return {"schema":"sc-library-wordpress-thin-adapter-validation/1.0","valid":not errors,"errors":errors,"normalized":contract()}

def certify_consolidation(payload: dict[str, Any] | None) -> dict[str, Any]:
    payload = dict(payload or {})
    errors = []
    if str(payload.get("library_version") or "") != LIBRARY_VERSION: errors.append("library-version-must-be-5.79.0")
    if str(payload.get("wordpress_role") or "") != "thin-adapter": errors.append("wordpress-role-must-be-thin-adapter")
    if payload.get("wordpress_authoritative") is not False: errors.append("wordpress-authoritative-must-be-false")
    if payload.get("legacy_domain_authority") is not False: errors.append("legacy-domain-authority-must-be-false")
    if payload.get("api_v1_required_for_domain_behavior") is not True: errors.append("api-v1-domain-boundary-required")
    if str(payload.get("library_api_authority") or "") != "library-api": errors.append("library-api-authority-must-be-library-api")
    try: php_count = int(payload.get("php_domain_authority_files"))
    except (TypeError, ValueError): php_count = -1
    if php_count != 0: errors.append("php-domain-authority-file-count-must-be-zero")
    allowed = payload.get("allowed_responsibilities") if isinstance(payload.get("allowed_responsibilities"), list) else []
    if set(map(str, allowed)) - set(ALLOWED_RESPONSIBILITIES): errors.append("unknown-allowed-responsibilities")
    prohibited = payload.get("prohibited_authorities") if isinstance(payload.get("prohibited_authorities"), list) else []
    missing = sorted(set(PROHIBITED_AUTHORITIES) - set(map(str, prohibited)))
    if missing: errors.append("missing-prohibited-authorities:" + ",".join(missing))
    normalized = {
        "library_version": LIBRARY_VERSION,
        "wordpress_role": "thin-adapter",
        "wordpress_authoritative": False,
        "legacy_domain_authority": False,
        "api_v1_required_for_domain_behavior": True,
        "library_api_authority": "library-api",
        "php_domain_authority_files": 0,
        "allowed_responsibilities": list(ALLOWED_RESPONSIBILITIES),
        "prohibited_authorities": list(PROHIBITED_AUTHORITIES),
    }
    basis = {"payload": normalized, "errors": errors}
    return {
        "schema": CERTIFICATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "certified": not errors,
        "errors": errors,
        "certification_id": "wordpress-thin-adapter-cert:" + _fp(basis)[:32],
        "certification_fingerprint_sha256": _fp(basis),
        "normalized": normalized,
        "consolidation": consolidation_manifest(),
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    c = contract()
    wordpress_edges = int(c["dependency_graph"].get("wordpress_dependency_count", 0) or 0)
    state = "ready" if wordpress_edges == 0 else "blocked"
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "wordpress_dependency_count": wordpress_edges,
        "adapter": c["adapter"],
        "allowed_responsibilities": c["allowed_responsibilities"],
        "prohibited_authorities": c["prohibited_authorities"],
        "identity_handoff": c["identity_handoff"],
        "consolidation_ready": state == "ready",
        "consolidation": c["consolidation"],
        "guardrails": c["guardrails"],
    }
