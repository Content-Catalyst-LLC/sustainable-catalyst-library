from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .runtime_authority import dependency_graph, guardrails as runtime_authority_guardrails

API_VERSION = "1.0"
API_PREFIX = "/api/library/v1"
CONTRACT = "sc-library-api-service-contract/1.0"
READINESS_CONTRACT = "sc-library-api-readiness/1.0"
ERROR_CONTRACT = "sc-library-api-error/1.0"
PAGE_CONTRACT = "sc-library-api-page/1.0"
ROUTE_CONTRACT = "sc-library-api-route/1.0"

ROUTES: tuple[dict[str, Any], ...] = (
    {"method":"GET","path":"/api/library/v1","name":"service-root","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/service","name":"service-contract","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/health","name":"health","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/readiness","name":"readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/capabilities","name":"capabilities","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/routes","name":"route-catalog","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/search","name":"search","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/records/{record_id}","name":"record","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/stats","name":"stats","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-authority","name":"runtime-authority","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/federation/readiness","name":"federation-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/artifacts/readiness","name":"artifact-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/pipelines/readiness","name":"pipeline-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/compute/readiness","name":"compute-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/web-application","name":"web-application","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/web-application/readiness","name":"web-application-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/identity","name":"identity-boundary","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/identity/readiness","name":"identity-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/wordpress-adapter","name":"wordpress-thin-adapter","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/wordpress-adapter/readiness","name":"wordpress-thin-adapter-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations","name":"cross-product-integrations","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations/readiness","name":"cross-product-integration-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/integrations/{product_key}","name":"cross-product-client-contract","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/integrations/{product_key}/exchange/validate","name":"cross-product-exchange-validate","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/integrations/{product_key}/bindings","name":"cross-product-binding-upsert","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration","name":"wordpress-state-migration-contract","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration/readiness","name":"wordpress-state-migration-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/validate","name":"wordpress-state-migration-validate","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/import","name":"wordpress-state-migration-import","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/state-migration/{run_id}/certify","name":"wordpress-state-migration-certify","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/state-migration/certifications/{certification_id}","name":"wordpress-state-retirement-certification","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/public-routing","name":"public-routing-bridge","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/public-routing/readiness","name":"public-routing-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/seo/records/{record_id}","name":"record-seo-descriptor","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/embed/records/{record_id}","name":"record-embed-descriptor","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/session","name":"current-session","access":"session-optional","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/session/login","name":"session-login","access":"public-credential-exchange","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/session/logout","name":"session-logout","access":"session-csrf","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/access/evaluate","name":"access-evaluate","access":"session-optional","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities","name":"identity-create","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities/{identity_id}/password","name":"identity-password-set","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/identities/{identity_id}/roles","name":"identity-role-bind","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/access-grants","name":"identity-access-grant","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/research-jobs","name":"submit-research-job","access":"signed-service","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/service-contracts","name":"persist-service-contract","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/release-engineering","name":"release-engineering","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/release-engineering/readiness","name":"release-engineering-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/release-engineering/validate","name":"release-engineering-validate","access":"signed-service","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/client-framework","name":"client-framework","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/client-framework/readiness","name":"client-framework-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority","name":"python-domain-authority","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority/readiness","name":"python-domain-authority-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/domain-authority/migration-plan","name":"python-domain-migration-plan","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/catalog","name":"python-catalog-service","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/catalog/readiness","name":"python-catalog-service-readiness","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/research-objects/{record_id}","name":"research-object","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/catalog/records/validate","name":"catalog-record-validate","access":"signed-admin","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/catalog/records","name":"catalog-record-upsert","access":"signed-admin","stability":"stable"},
    {"method":"DELETE","path":"/api/library/v1/admin/catalog/records/{record_id}","name":"catalog-record-delete","access":"signed-admin","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-certification","name":"runtime-certification","access":"public","stability":"stable"},
    {"method":"GET","path":"/api/library/v1/runtime-certification/readiness","name":"runtime-certification-readiness","access":"public","stability":"stable"},
    {"method":"POST","path":"/api/library/v1/admin/runtime-certification/evaluate","name":"runtime-certification-evaluate","access":"signed-service","stability":"stable"},
)

CAPABILITY_FAMILIES: dict[str, dict[str, Any]] = {
    "discovery": {"resources":["search","records","stats"],"direct_api":True},
    "federation": {"resources":["sources","connectors","global-knowledge-federation"],"direct_api":True},
    "language": {"resources":["original-language","ocr-htr-transcription","linguistic-corpus","entity-resolution","translation-alignment"],"direct_api":True},
    "research-execution": {"resources":["research-jobs","workers","pipelines","compute"],"direct_api":True},
    "artifacts": {"resources":["research-artifacts","derivations","integrity"],"direct_api":True},
    "transparency": {"resources":["source-quality-signals","trust-policies"],"direct_api":True},
    "cross-civilizational": {"resources":["evidence-links","scientific-data-links"],"direct_api":True},
    "platform-core": {"resources":["bindings","handoffs","outbox"],"direct_api":True,"authority":"platform-core"},
    "web-application": {"resources":["search-shell","reader","discovery","system-status"],"direct_api":True,"authority":"client"},
    "identity-access": {"resources":["identities","sessions","roles","access-grants"],"direct_api":True,"authority":"library-service"},
    "wordpress-adapter": {"resources":["routing","seo","embeds","health","identity-handoff","legacy-presentation"],"direct_api":True,"authority":"client-adapter"},
    "public-web": {"resources":["canonical-routing","record-seo","launch-links","record-embeds","public-origin"],"direct_api":True,"authority":"library-service-contract"},
    "cross-product-integration": {"resources":["research-librarian","workspace","research-lab","workbench","decision-studio","site-intelligence"],"direct_api":True,"authority":"library-service"},
    "state-migration": {"resources":["wordpress-inventory","migration-manifest","migration-import","retirement-certification"],"direct_api":True,"authority":"library-service"},
    "release-engineering": {"resources":["manifest","preflight","rollback","artifact-integrity"],"direct_api":True,"authority":"library-service"},
    "client-framework": {"resources":["python-sdk","javascript-client","typescript-contracts","signed-requests","retries","cross-product-adapters"],"direct_api":True,"authority":"library-service"},
    "python-domain-authority": {"resources":["domain-registry","php-retirement-policy","migration-plan","authority-readiness"],"direct_api":True,"authority":"python-backend"},
    "catalog-domain": {"resources":["catalog-contract","catalog-write","research-object","record-revisioning","publication-state"],"direct_api":True,"authority":"python-backend"},
    "runtime-certification": {"resources":["wordpress-failure","runtime-probes","certification"],"direct_api":True,"authority":"library-service"},
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, bool]:
    return {
        "api_v1_independent_of_wordpress": True,
        "wordpress_required_for_api_v1": False,
        "wordpress_proxy_is_authoritative_api": False,
        "legacy_v1_routes_are_api_v1_contract": False,
        "breaking_changes_allowed_within_api_v1": False,
        "additive_fields_allowed_within_api_v1": True,
        "error_envelope_is_stable": True,
        "pagination_contract_is_stable": True,
        "signed_mutations_required": True,
        "public_reads_may_be_rate_limited": True,
        "api_contract_implies_research_truth": False,
        "automatic_platform_core_promotion": False,
    }


def route_catalog() -> list[dict[str, Any]]:
    return [{"schema": ROUTE_CONTRACT, **dict(r)} for r in ROUTES]

def capability_catalog() -> dict[str, Any]:
    return {
        "schema": "sc-library-api-capabilities/1.0",
        "api_version": API_VERSION,
        "families": CAPABILITY_FAMILIES,
        "guardrails": guardrails(),
    }


def auth_contract() -> dict[str, Any]:
    return {
        "public_read": {"required": False, "rate_limitable": True},
        "signed_service": {
            "required": True,
            "headers": ["Authorization", "X-SC-Timestamp", "X-SC-Signature"],
            "signature_scope": "method + request-path + timestamp + raw-body",
        },
        "signed_admin": {
            "required": True,
            "headers": ["Authorization", "X-SC-Timestamp", "X-SC-Signature"],
            "signature_scope": "method + request-path + timestamp + raw-body",
        },
        "library_session": {
            "required": False,
            "cookie": "sc_library_session",
            "model": "opaque-revocable-server-session",
            "csrf_header_for_cookie_mutations": "X-SC-CSRF-Token",
            "wordpress_cookie_authoritative": False,
        },
    }


def error_envelope(code: str, message: str, *, status: int = 400, details: Any = None, request_id: str | None = None) -> dict[str, Any]:
    out = {"schema": ERROR_CONTRACT, "error": {"code": str(code), "message": str(message), "status": int(status)}}
    if details is not None:
        out["error"]["details"] = details
    if request_id:
        out["request_id"] = request_id
    return out


def page_envelope(items: list[Any], *, limit: int, offset: int, total: int | None = None) -> dict[str, Any]:
    limit = max(1, min(100, int(limit)))
    offset = max(0, int(offset))
    out: dict[str, Any] = {"schema": PAGE_CONTRACT, "items": list(items), "page": {"limit": limit, "offset": offset, "count": len(items)}}
    if total is not None:
        total = max(0, int(total))
        out["page"]["total"] = total
        out["page"]["has_more"] = offset + len(items) < total
        out["page"]["next_offset"] = offset + len(items) if out["page"]["has_more"] else None
    return out


def service_contract() -> dict[str, Any]:
    routes = route_catalog()
    basis = {"api_version": API_VERSION, "base_path": API_PREFIX, "routes": routes, "capabilities": CAPABILITY_FAMILIES, "auth": auth_contract(), "guardrails": guardrails()}
    fingerprint = _fp(basis)
    return {
        "schema": CONTRACT,
        "contract_id": "library-api-service-contract:" + fingerprint[:32],
        "contract_fingerprint_sha256": fingerprint,
        "library_version": "5.70.0",
        "backend_version": "2.81.0",
        "api_version": API_VERSION,
        "base_path": API_PREFIX,
        "state": "stable",
        "compatibility": {
            "breaking_change_policy": "new-major-api-version-required",
            "additive_fields": "allowed",
            "unknown_fields": "clients-must-ignore",
            "legacy_routes": "/v1/* remain compatibility/internal surfaces and are not the API v1 contract",
        },
        "routes": routes,
        "capabilities": CAPABILITY_FAMILIES,
        "auth": auth_contract(),
        "error_contract": ERROR_CONTRACT,
        "pagination_contract": PAGE_CONTRACT,
        "wordpress": {"role":"client-adapter","required":False,"authoritative":False},
        "runtime_authority": {"dependency_graph": dependency_graph(), "guardrails": runtime_authority_guardrails()},
        "guardrails": guardrails(),
        "persisted": False,
    }


def validate_service_contract(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    if not isinstance(payload, dict):
        payload = {}
        errors.append("payload-must-be-object")
    if payload.get("api_version", API_VERSION) != API_VERSION:
        errors.append("api-version-must-be-1.0")
    if payload.get("base_path", API_PREFIX) != API_PREFIX:
        errors.append("base-path-must-be-/api/library/v1")
    if payload.get("wordpress_required") is True:
        errors.append("wordpress-api-dependency-prohibited")
    if payload.get("wordpress_authoritative") is True:
        errors.append("wordpress-authoritative-api-prohibited")
    if payload.get("breaking_changes_within_v1") is True:
        errors.append("breaking-change-within-v1-prohibited")
    return {"schema": "sc-library-api-service-contract-validation/1.0", "valid": not errors, "errors": errors, "normalized": service_contract(), "guardrails": guardrails()}


def readiness() -> dict[str, Any]:
    db_state = "unavailable"
    counts = {"contracts": 0, "events": 0}
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            for table, key in [("library_api_service_contracts", "contracts"), ("library_api_service_contract_events", "events")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}")
                counts[key] = int(cur.fetchone()["n"])
            db_state = "ready"
    except Exception:
        pass
    contract = service_contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": "5.70.0",
        "backend_version": "2.81.0",
        "api_version": API_VERSION,
        "base_path": API_PREFIX,
        "state": "ready" if db_state == "ready" else "degraded",
        "database": db_state,
        "contract_id": contract["contract_id"],
        "contract_fingerprint_sha256": contract["contract_fingerprint_sha256"],
        "route_count": len(contract["routes"]),
        "capability_family_count": len(contract["capabilities"]),
        "wordpress_required": False,
        "counts": counts,
        "guardrails": guardrails(),
    }


def persist_service_contract(provenance: dict[str, Any] | None = None) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    contract = service_contract()
    provenance = dict(provenance or {})
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_api_service_contracts(
                contract_id,api_version,state,base_path,route_catalog,capability_catalog,auth_contract,
                error_contract,pagination_contract,contract_fingerprint,provenance,guardrails
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT(contract_id) DO NOTHING""",
            (contract["contract_id"], contract["api_version"], contract["state"], contract["base_path"], Jsonb(contract["routes"]), Jsonb(contract["capabilities"]), Jsonb(contract["auth"]), contract["error_contract"], contract["pagination_contract"], contract["contract_fingerprint_sha256"], Jsonb(provenance), Jsonb(contract["guardrails"])),
        )
        cur.execute("INSERT INTO library_api_service_contract_events(contract_id,event_type,details) VALUES (%s,'published',%s)", (contract["contract_id"], Jsonb({"api_version":API_VERSION,"base_path":API_PREFIX,"route_count":len(contract["routes"])})))
        conn.commit()
    contract["persisted"] = True
    contract["provenance"] = provenance
    return contract
