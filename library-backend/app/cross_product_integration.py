from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.0.0"
BACKEND_VERSION = "3.0.0"
CONTRACT = "sc-library-cross-product-service-integration/1.0"
READINESS_CONTRACT = "sc-library-cross-product-service-readiness/1.0"
PRODUCT_CONTRACT = "sc-library-cross-product-client/1.0"
EXCHANGE_CONTRACT = "sc-library-cross-product-exchange-validation/1.0"
BINDING_CONTRACT = "sc-library-cross-product-service-binding/1.0"

PRODUCTS: dict[str, dict[str, Any]] = {
    "research-librarian": {
        "display_name": "Research Librarian AI",
        "role": "research-discovery-and-guidance-client",
        "capability_families": ["discovery","federation","language","transparency","cross-civilizational","artifacts"],
        "required_scopes": ["library:read","private:read","projects:read","artifacts:read","jobs:submit"],
        "handoffs": ["record-reference","source-bundle","artifact-reference","research-job","project-context"],
    },
    "workspace": {
        "display_name": "Workspace",
        "role": "compute-and-reproducibility-client",
        "capability_families": ["discovery","artifacts","research-execution","platform-core"],
        "required_scopes": ["library:read","projects:read","projects:write","artifacts:read","artifacts:write","jobs:submit","service:execute"],
        "handoffs": ["record-reference","artifact-reference","research-job","runtime-handoff","project-context","core-handoff"],
    },
    "research-lab": {
        "display_name": "Research Lab",
        "role": "scientific-experimentation-client",
        "capability_families": ["discovery","language","artifacts","research-execution","platform-core"],
        "required_scopes": ["library:read","projects:read","projects:write","artifacts:read","artifacts:write","jobs:submit","service:execute"],
        "handoffs": ["record-reference","corpus-reference","artifact-reference","research-job","experiment-context","core-handoff"],
    },
    "workbench": {
        "display_name": "Workbench",
        "role": "scientific-compute-and-prototyping-client",
        "capability_families": ["discovery","artifacts","research-execution","platform-core"],
        "required_scopes": ["library:read","projects:read","artifacts:read","artifacts:write","jobs:submit","service:execute"],
        "handoffs": ["record-reference","artifact-reference","research-job","runtime-handoff","core-handoff"],
    },
    "decision-studio": {
        "display_name": "Decision Studio",
        "role": "decision-support-client",
        "capability_families": ["discovery","artifacts","transparency","cross-civilizational","platform-core"],
        "required_scopes": ["library:read","projects:read","artifacts:read"],
        "handoffs": ["record-reference","source-bundle","artifact-reference","evidence-context","core-handoff"],
    },
    "site-intelligence": {
        "display_name": "Site Intelligence",
        "role": "geospatial-and-country-intelligence-client",
        "capability_families": ["discovery","federation","artifacts","platform-core"],
        "required_scopes": ["library:read","projects:read","artifacts:read","service:execute"],
        "handoffs": ["record-reference","source-bundle","artifact-reference","geospatial-context","core-handoff"],
    },
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "products_call_library_api_directly": True,
        "wordpress_required_for_cross_product_calls": False,
        "downstream_products_are_library_authorities": False,
        "downstream_products_may_mutate_library_state_without_scope": False,
        "library_object_ids_remain_library_owned": True,
        "downstream_derivatives_may_have_downstream_ownership": True,
        "handoffs_preserve_source_ids_and_provenance": True,
        "service_credentials_are_never_returned_by_contract": True,
        "service_bindings_persist_no_secret_material": True,
        "platform_core_owns_governed_research_meaning": True,
        "library_auto_promotes_to_platform_core": False,
        "integration_readiness_implies_research_truth": False,
    }


def service_principal_template(product_key: str) -> dict[str, Any]:
    p = PRODUCTS[product_key]
    return {
        "principal_type": "service",
        "handle": f"sc-{product_key}",
        "role": "service",
        "required_scopes": list(p["required_scopes"]),
        "credential_transport": "signed-service-request",
        "credential_material_in_contract": False,
    }


def product_contract(product_key: str) -> dict[str, Any]:
    if product_key not in PRODUCTS:
        raise KeyError(product_key)
    p = PRODUCTS[product_key]
    basis = {"product_key": product_key, **p, "guardrails": guardrails()}
    fp = _fp(basis)
    return {
        "schema": PRODUCT_CONTRACT,
        "integration_id": "library-product-integration:" + product_key + ":" + fp[:16],
        "product_key": product_key,
        "display_name": p["display_name"],
        "role": p["role"],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "api_version": "1.0",
        "base_path": "/api/library/v1",
        "direct_library_api": True,
        "wordpress_required": False,
        "authoritative_for_library_state": False,
        "capability_families": list(p["capability_families"]),
        "required_scopes": list(p["required_scopes"]),
        "handoff_types": list(p["handoffs"]),
        "service_principal_template": service_principal_template(product_key),
        "ownership": {
            "library_records": "library",
            "library_artifacts": "library",
            "library_jobs": "library",
            "downstream_derivative_objects": product_key,
            "governed_research_meaning": "platform-core",
        },
        "guardrails": guardrails(),
    }


def registry_contract() -> dict[str, Any]:
    clients = {k: product_contract(k) for k in PRODUCTS}
    basis = {"clients": clients, "guardrails": guardrails()}
    fp = _fp(basis)
    return {
        "schema": CONTRACT,
        "registry_id": "library-cross-product-registry:" + fp[:32],
        "registry_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "api_version": "1.0",
        "product_count": len(clients),
        "products": clients,
        "authority": "library-service",
        "platform_core_boundary": "governed-research-meaning-and-exchange",
        "guardrails": guardrails(),
    }


def validate_exchange(product_key: str, payload: dict[str, Any] | None) -> dict[str, Any]:
    if product_key not in PRODUCTS:
        raise KeyError(product_key)
    payload = dict(payload or {})
    contract = product_contract(product_key)
    requested_family = str(payload.get("capability_family") or "").strip()
    requested_handoff = str(payload.get("handoff_type") or "").strip()
    requested_scopes = sorted({str(x) for x in (payload.get("requested_scopes") or []) if str(x)})
    errors: list[str] = []
    if requested_family and requested_family not in contract["capability_families"]:
        errors.append("capability-family-not-granted")
    if requested_handoff and requested_handoff not in contract["handoff_types"]:
        errors.append("handoff-type-not-granted")
    allowed_scopes = set(contract["required_scopes"])
    extra_scopes = [x for x in requested_scopes if x not in allowed_scopes]
    if extra_scopes:
        errors.append("scope-not-granted:" + ",".join(extra_scopes))
    if payload.get("claim_library_authority") is True:
        errors.append("downstream-library-authority-prohibited")
    if payload.get("automatic_platform_core_promotion") is True:
        errors.append("automatic-platform-core-promotion-prohibited")
    return {
        "schema": EXCHANGE_CONTRACT,
        "product_key": product_key,
        "valid": not errors,
        "errors": errors,
        "requested": {
            "capability_family": requested_family or None,
            "handoff_type": requested_handoff or None,
            "requested_scopes": requested_scopes,
        },
        "contract": contract,
        "guardrails": guardrails(),
    }


def build_binding(product_key: str, payload: dict[str, Any]) -> dict[str, Any]:
    if product_key not in PRODUCTS:
        raise KeyError(product_key)
    contract = product_contract(product_key)
    client_base_url = str(payload.get("client_base_url") or "").strip().rstrip("/")
    if client_base_url and not client_base_url.startswith(("https://", "http://")):
        raise ValueError("client_base_url must be an absolute HTTP(S) URL")
    service_identity_id = str(payload.get("service_identity_id") or "").strip() or None
    basis = {
        "product_key": product_key,
        "client_base_url": client_base_url,
        "service_identity_id": service_identity_id,
        "capability_families": contract["capability_families"],
        "required_scopes": contract["required_scopes"],
    }
    fp = _fp(basis)
    return {
        "schema": BINDING_CONTRACT,
        "binding_id": "library-product-binding:" + product_key + ":" + fp[:24],
        "product_key": product_key,
        "client_base_url": client_base_url,
        "service_identity_id": service_identity_id,
        "status": "active",
        "capability_families": contract["capability_families"],
        "scopes": contract["required_scopes"],
        "metadata": dict(payload.get("metadata") or {}),
        "secret_material_persisted": False,
        "guardrails": guardrails(),
    }


def persist_binding(product_key: str, payload: dict[str, Any]) -> dict[str, Any]:
    binding = build_binding(product_key, payload)
    from .db import get_pool, json_value
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_cross_product_service_bindings(
                binding_id,product_key,service_identity_id,client_base_url,status,capability_families,scopes,metadata
            ) VALUES (%s,%s,%s,%s,%s,%s::jsonb,%s::jsonb,%s::jsonb)
            ON CONFLICT(product_key) DO UPDATE SET
                service_identity_id=EXCLUDED.service_identity_id, client_base_url=EXCLUDED.client_base_url,
                status=EXCLUDED.status, capability_families=EXCLUDED.capability_families, scopes=EXCLUDED.scopes,
                metadata=EXCLUDED.metadata, updated_at=now()
            RETURNING binding_id,product_key,service_identity_id,client_base_url,status,capability_families,scopes,metadata,created_at,updated_at""",
            (binding["binding_id"], product_key, binding["service_identity_id"], binding["client_base_url"], binding["status"],
             json_value(binding["capability_families"]), json_value(binding["scopes"]), json_value(binding["metadata"])),
        )
        row = dict(cur.fetchone())
        cur.execute(
            "INSERT INTO library_cross_product_service_events(binding_id,product_key,event_type,details) VALUES (%s,%s,'binding-upserted',%s::jsonb)",
            (row["binding_id"], product_key, json_value({"secret_material_persisted": False})),
        )
        conn.commit()
    return {"schema": BINDING_CONTRACT, **row, "secret_material_persisted": False, "guardrails": guardrails()}


def readiness() -> dict[str, Any]:
    counts = {"bindings": 0, "events": 0}
    database = "unavailable"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [("library_cross_product_service_bindings","bindings"),("library_cross_product_service_events","events")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
        database = "ready"
    except Exception:
        database = "unavailable"
    reg = registry_contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if len(PRODUCTS) == 6 else "blocked",
        "database": database,
        "product_count": len(PRODUCTS),
        "products": sorted(PRODUCTS),
        "direct_library_api": True,
        "wordpress_required": False,
        "counts": counts,
        "registry_fingerprint_sha256": reg["registry_fingerprint_sha256"],
        "guardrails": guardrails(),
    }
