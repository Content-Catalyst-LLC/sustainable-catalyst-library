from __future__ import annotations
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "5.71.0"
BACKEND_VERSION = "2.82.0"
CONTRACT = "sc-library-python-domain-authority/1.0"
READINESS_CONTRACT = "sc-library-python-domain-authority-readiness/1.0"
MIGRATION_PLAN_CONTRACT = "sc-library-python-domain-migration-plan/1.0"

DOMAINS: dict[str, dict[str, Any]] = {
    "catalog-record-read": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/search", "/api/library/v1/records/{record_id}", "/api/library/v1/research-objects/{record_id}", "/api/library/v1/stats"],
        "wordpress_role": "presentation-and-linking-only",
    },
    "identity-session-access": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/identity", "/api/library/v1/session", "/api/library/v1/access/evaluate"],
        "wordpress_role": "optional-identity-handoff",
    },
    "artifacts-pipelines-compute": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/artifacts/readiness", "/api/library/v1/pipelines/readiness", "/api/library/v1/compute/readiness"],
        "wordpress_role": "status-and-launch-surfaces-only",
    },
    "cross-product-integration": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/integrations", "/api/library/v1/client-framework"],
        "wordpress_role": "none-required",
    },
    "publication-catalog-write": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/catalog", "/api/library/v1/admin/catalog/records", "/api/library/v1/research-objects/{record_id}"],
        "wordpress_role": "presentation-and-api-client-only",
    },
    "research-projects-collections-saved-state": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/research-state", "/api/library/v1/admin/research-state/owners/{owner_identity_id}"],
        "wordpress_role": "presentation-and-api-client-only",
    },
    "source-ingestion-normalization": {
        "authority": "migration-pending", "state": "planned-v5.72",
        "wordpress_role": "legacy-compatible-until-cutover",
    },
    "retrieval-orchestration": {
        "authority": "python-backend", "state": "authoritative-foundation",
        "wordpress_role": "query-ui-only",
    },
    "provenance-citation-evidence-graph": {
        "authority": "migration-active", "state": "planned-v5.74",
        "wordpress_role": "legacy-compatible-until-cutover",
    },
    "language-document-intelligence": {
        "authority": "python-backend", "state": "authoritative-runtime-with-legacy-php-surfaces",
        "wordpress_role": "presentation-only-target",
    },
    "connector-federation-runtime": {
        "authority": "python-backend", "state": "authoritative-runtime-with-legacy-php-surfaces",
        "wordpress_role": "configuration-presentation-target",
    },
    "background-workflows": {
        "authority": "python-backend", "state": "authoritative-runtime",
        "wordpress_role": "status-only-target",
    },
}

MIGRATION_SEQUENCE = (
    ("5.70.0", "Python Catalog, Publication & Research Object Service"),
    ("5.71.0", "Python Research Projects, Collections & Saved Research State"),
    ("5.72.0", "Python Source Ingestion & Normalization Service"),
    ("5.73.0", "Python Retrieval & Search Orchestration"),
    ("5.74.0", "Python Provenance, Citation & Evidence Graph Service"),
    ("5.75.0", "Python Language Intelligence & Document Processing"),
    ("5.76.0", "Python Research Package & Reproducibility Service"),
    ("5.77.0", "Python Connector & Federation Runtime"),
    ("5.78.0", "Python Background Job & Workflow Consolidation"),
    ("5.79.0", "WordPress Thin Adapter Consolidation"),
    ("5.80.0", "Independent Library Application Certification"),
)

def _canon(v: Any) -> str: return json.dumps(v, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
def _fp(v: Any) -> str: return sha256(_canon(v).encode("utf-8")).hexdigest()

def guardrails() -> dict[str, bool]:
    return {
        "python_owns_library_domain_behavior": True,
        "wordpress_php_is_domain_authority": False,
        "new_research_domain_logic_in_php_by_default": False,
        "wordpress_rendering_php_allowed": True,
        "wordpress_lifecycle_php_allowed": True,
        "wordpress_routing_and_seo_php_allowed": True,
        "legacy_php_domain_logic_may_be_retired_incrementally": True,
        "mass_rewrite_required": False,
        "api_v1_remains_stability_boundary": True,
        "postgresql_remains_structured_state_authority": True,
        "runtime_success_implies_research_truth": False,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str, Any]:
    basis={"domains":DOMAINS,"guardrails":guardrails(),"migration_sequence":MIGRATION_SEQUENCE}
    fp=_fp(basis)
    authoritative=sorted(k for k,v in DOMAINS.items() if str(v.get("authority")).startswith("python-backend"))
    pending=sorted(k for k,v in DOMAINS.items() if not str(v.get("authority")).startswith("python-backend"))
    return {
        "schema": CONTRACT,
        "contract_id": "library-python-domain-authority:"+fp[:32],
        "contract_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "migration-active",
        "default_domain_authority": "python-backend",
        "wordpress_role": "optional-thin-adapter",
        "authoritative_domains": authoritative,
        "migration_domains": pending,
        "domains": DOMAINS,
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    c=contract()
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready",
        "default_domain_authority": "python-backend",
        "wordpress_required": False,
        "authoritative_domain_count": len(c["authoritative_domains"]),
        "migration_domain_count": len(c["migration_domains"]),
        "guardrails": c["guardrails"],
    }

def migration_plan() -> dict[str, Any]:
    return {
        "schema": MIGRATION_PLAN_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "principle": "research-data-compute-intelligence-logic-belongs-in-python; wordpress-php-is-an-adapter",
        "sequence": [{"version":v,"title":title} for v,title in MIGRATION_SEQUENCE],
        "cutover_rule": "retire legacy PHP authority only after API parity, production readiness, rollback path, and WordPress-independent certification",
        "guardrails": guardrails(),
    }
