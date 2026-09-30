from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

WEB_VERSION = "1.0.0"
CONTRACT = "sc-library-web-application/1.0"
READINESS_CONTRACT = "sc-library-web-readiness/1.0"

SURFACES: tuple[dict[str, Any], ...] = (
    {"id":"search","label":"Search","route":"#/search","api":"/api/library/v1/search","public":True},
    {"id":"reader","label":"Reader","route":"#/record/{record_id}","api":"/api/library/v1/records/{record_id}","public":True},
    {"id":"discover","label":"Discover","route":"#/discover","api":"/api/library/v1/capabilities","public":True},
    {"id":"system","label":"System","route":"#/system","api":"/api/library/v1/readiness","public":True},
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def application_contract() -> dict[str, Any]:
    basis = {
        "web_version": WEB_VERSION,
        "api_version": "1.0",
        "api_base_path": "/api/library/v1",
        "surfaces": SURFACES,
        "wordpress_required": False,
    }
    fp = sha256(_canon(basis).encode("utf-8")).hexdigest()
    return {
        "schema": CONTRACT,
        "application_id": "sustainable-catalyst-library-web",
        "application_contract_id": "library-web-application:" + fp[:32],
        "application_fingerprint_sha256": fp,
        "library_version": "5.60.0",
        "backend_version": "2.71.0",
        "web_version": WEB_VERSION,
        "state": "foundation",
        "deployment_model": "independent-static-web-service",
        "api": {"version":"1.0","base_path":"/api/library/v1","same_origin_proxy_supported":True},
        "surfaces": [dict(x) for x in SURFACES],
        "wordpress": {"role":"optional-adapter","required":False,"request_path_dependency":False},
        "guardrails": {
            "research_state_owned_by_web_client": False,
            "research_execution_owned_by_web_client": False,
            "wordpress_required": False,
            "api_v1_is_authoritative_service_boundary": True,
            "web_client_may_be_replaced_without_data_migration": True,
            "client_side_secrets_permitted": False,
        },
    }


def readiness() -> dict[str, Any]:
    c = application_contract()
    return {
        "schema": READINESS_CONTRACT,
        "state": "ready",
        "library_version": c["library_version"],
        "backend_version": c["backend_version"],
        "web_version": c["web_version"],
        "application_id": c["application_id"],
        "api_version": c["api"]["version"],
        "api_base_path": c["api"]["base_path"],
        "surface_count": len(c["surfaces"]),
        "wordpress_required": False,
        "guardrails": c["guardrails"],
    }
