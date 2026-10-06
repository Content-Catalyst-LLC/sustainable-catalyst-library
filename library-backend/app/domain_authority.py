from __future__ import annotations
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.31.0"
BACKEND_VERSION = "3.31.0"
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
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/ingestion", "/api/library/v1/admin/ingestion/records"],
        "wordpress_role": "upload-configuration-presentation-only",
    },
    "retrieval-orchestration": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/search", "/api/library/v1/retrieval", "/api/library/v1/retrieval/search", "/api/library/v1/retrieval/facets"],
        "wordpress_role": "query-ui-and-api-client-only",
    },
    "provenance-citation-evidence-graph": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/provenance", "/api/library/v1/provenance/records/{record_id}", "/api/library/v1/citations/{record_id}", "/api/library/v1/evidence-graph/{record_id}"],
        "wordpress_role": "presentation-and-api-client-only",
    },
    "language-document-intelligence": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/language", "/api/library/v1/language/captures/{capture_id}", "/api/library/v1/language/derivations/{run_id}", "/api/library/v1/language/corpora/{corpus_id}", "/api/library/v1/language/alignments/{matrix_id}", "/api/library/v1/language/documents/{record_id}/intelligence"],
        "wordpress_role": "presentation-and-api-client-only",
    },
    "research-package-reproducibility": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/reproducibility", "/api/library/v1/reproducibility/packages/{package_id}", "/api/library/v1/admin/reproducibility/packages", "/api/library/v1/admin/reproducibility/packages/{package_id}/verify"],
        "wordpress_role": "presentation-and-api-client-only",
    },
    "connector-federation-runtime": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/federation", "/api/library/v1/federation/sources/{source_id}", "/api/library/v1/federation/connectors/{connector_id}", "/api/library/v1/admin/federation/plan"],
        "wordpress_role": "presentation-and-api-client-only",
        "wordpress_connector_fallback": False,
    },
    "unified-discovery-research-navigation": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/navigation", "/api/library/v1/navigation/bootstrap", "/api/library/v1/navigation/resolve"],
        "wordpress_role": "optional-adapter",
        "canonical_web_route": "/research",
        "legacy_alias_routes": ["/search", "/discover"],
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
        "state_authority": "existing-python-domain-services",
    },
    "research-projects-saved-workspaces": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/saved-workspaces", "/api/library/v1/workspaces", "/api/library/v1/workspaces/projects", "/api/library/v1/workspaces/projects/{project_id}/working-set"],
        "state_authority": "python-research-state-service",
        "persistence": "existing-postgresql-research-state",
        "database_migration_required": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "advanced-semantic-cross-language-discovery": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/discovery", "/api/library/v1/discovery/readiness", "/api/library/v1/discovery/plan", "/api/library/v1/discovery/search", "/api/library/v1/discovery/projects/{project_id}/search"],
        "state_authority": "existing-python-retrieval-language-and-research-state-services",
        "database_migration_required": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "global-knowledge-federation-ii": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/federation/global", "/api/library/v1/federation/global/readiness", "/api/library/v1/federation/global/lenses", "/api/library/v1/federation/global/plan", "/api/library/v1/federation/global/discover"],
        "state_authority": "existing-global-source-registry-connector-federation-and-advanced-discovery-services",
        "database_migration_required": False,
        "external_execution": "explicit-plan-only",
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "research-graph-evidence-navigation": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/research-graph", "/api/library/v1/research-graph/readiness", "/api/library/v1/research-graph/records/{record_id}/neighborhood", "/api/library/v1/research-graph/records/{record_id}/summary", "/api/library/v1/research-graph/path"],
        "state_authority": "existing-provenance-citation-and-library-edge-services",
        "database_migration_required": False,
        "graph_store_created": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "living-collections-research-projects": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/living-research", "/api/library/v1/living-research/readiness", "/api/library/v1/living-research/projects/{project_id}/brief", "/api/library/v1/living-research/collections/{collection_id}", "/api/library/v1/living-research/collections", "/api/library/v1/living-research/collections/{collection_id}/refresh", "/api/library/v1/living-research/collections/{collection_id}/apply"],
        "state_authority": "existing-python-research-state-postgresql-service",
        "retrieval_authority": "advanced-discovery",
        "graph_authority": "research-graph-navigation",
        "database_migration_required": False,
        "automatic_collection_membership_mutation": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "dataset-table-structured-evidence-objects": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/structured-evidence", "/api/library/v1/structured-evidence/readiness", "/api/library/v1/structured-evidence/schemas", "/api/library/v1/structured-evidence/datasets", "/api/library/v1/structured-evidence/tables", "/api/library/v1/structured-evidence/objects", "/api/library/v1/structured-evidence/validate"],
        "lineage_authority": "research-corpus-builder",
        "database_migration_required": False,
        "automatic_truth_promotion": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "scientific-literature-intelligence": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/scientific-literature", "/api/library/v1/scientific-literature/readiness", "/api/library/v1/scientific-literature/schemas", "/api/library/v1/scientific-literature/publications/normalize", "/api/library/v1/scientific-literature/publications/analyze", "/api/library/v1/scientific-literature/sets/analyze", "/api/library/v1/scientific-literature/reviews/analyze", "/api/library/v1/scientific-literature/validate"],
        "composition": ["scientific-document-intelligence", "literature-review", "provenance-citation-evidence-graph", "dataset-table-structured-evidence-objects", "advanced-discovery"],
        "database_migration_required": False,
        "automatic_truth_promotion": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "research-package-publishing-reproducible-exports": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/research-package-publishing", "/api/library/v1/research-package-publishing/readiness", "/api/library/v1/research-package-publishing/formats", "/api/library/v1/research-package-publishing/preview", "/api/library/v1/research-package-publishing/export", "/api/library/v1/research-package-publishing/validate", "/api/library/v1/admin/research-package-publishing/persist"],
        "composition": ["research-package-reproducibility", "artifacts-pipelines-compute", "dataset-table-structured-evidence-objects", "scientific-literature-intelligence"],
        "persisted_byte_authority": "existing-content-addressed-artifact-store",
        "database_migration_required": False,
        "external_publication_automatic": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "institutional-repository-federation": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/institutional-repositories", "/api/library/v1/institutional-repositories/readiness", "/api/library/v1/institutional-repositories/schemas", "/api/library/v1/institutional-repositories/repositories", "/api/library/v1/institutional-repositories/repositories/{source_key}", "/api/library/v1/institutional-repositories/plan", "/api/library/v1/institutional-repositories/search", "/api/library/v1/institutional-repositories/handoff"],
        "composition": ["institutional-research-network", "connector-federation-runtime", "global-knowledge-federation-ii", "source-ingestion"],
        "protocols": ["dspace-rest", "dataverse-api", "oai-pmh"],
        "database_migration_required": False,
        "automatic_repository_harvest": False,
        "automatic_library_import": False,
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "independent-research-interface": {
        "authority": "python-backend", "state": "authoritative-composition",
        "api": ["/api/library/v1/research-interface", "/api/library/v1/research-interface/bootstrap", "/api/library/v1/research-interface/search", "/api/library/v1/research-interface/records/{record_id}"],
        "wordpress_role": "optional-adapter",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
        "state_authority": "existing-python-domain-services",
    },
    "independent-library-product": {
        "authority": "python-backend", "state": "independent-primary",
        "api": ["/api/library/v1/product", "/api/library/v1/product/readiness", "/api/library/v1/product/release"],
        "wordpress_role": "optional-adapter",
        "api_version": "1.0",
        "web_version": "2.31.0",
        "sdk_version": "1.31.0",
    },
    "independent-application-certification": {
        "authority": "python-backend", "state": "certified-foundation",
        "api": ["/api/library/v1/independent-application", "/api/library/v1/independent-application/readiness", "/api/library/v1/independent-application/certification", "/api/library/v1/admin/independent-application/certify"],
        "wordpress_role": "not-required",
        "next_release": "6.0.0",
    },
    "wordpress-thin-adapter": {
        "authority": "python-backend", "state": "consolidated",
        "api": ["/api/library/v1/wordpress-adapter", "/api/library/v1/wordpress-adapter/readiness", "/api/library/v1/wordpress-adapter/consolidation", "/api/library/v1/admin/wordpress-adapter/consolidation/certify"],
        "wordpress_role": "optional-thin-adapter",
        "php_domain_authority_files": 0,
        "legacy_compatibility_present": True,
    },
    "background-workflows": {
        "authority": "python-backend", "state": "authoritative",
        "api": ["/api/library/v1/workflows", "/api/library/v1/workflows/readiness", "/api/library/v1/admin/workflows/jobs", "/api/library/v1/admin/workflows/pipelines", "/api/library/v1/admin/workflows/recover-expired-leases"],
        "wordpress_role": "presentation-and-api-client-only",
        "postgresql_job_state_authority": True,
        "redis_job_state_authority": False,
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
