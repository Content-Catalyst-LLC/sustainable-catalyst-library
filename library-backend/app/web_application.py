from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

WEB_VERSION = "3.0.0"
CONTRACT = "sc-library-web-application/1.0"
READINESS_CONTRACT = "sc-library-web-readiness/1.0"

SURFACES: tuple[dict[str, Any], ...] = (
    {"id":"workspaces","label":"Workspaces","route":"/account?section=workspaces","api":"/api/library/v1/workspaces","public":False,"index":False},
    {"id":"research","label":"Research","route":"/research","api":"/api/library/v1/navigation","public":True,"index":False},
    {"id":"historical-archives","label":"Archives","route":"/research/archives","api":"/api/library/v1/historical-archives/workspace","public":True,"index":False,"parent_surface":"research"},
    {"id":"primary-source-criticism","label":"Source Criticism","route":"/research/archives/compare","api":"/api/library/v1/historical-archives/source-criticism","public":True,"index":False,"parent_surface":"historical-archives"},
    {"id":"historical-event-timeline","label":"Timeline","route":"/research/archives/timeline","api":"/api/library/v1/historical-archives/timeline-workspace","public":True,"index":False,"parent_surface":"historical-archives"},
    {"id":"research-annotation-notes","label":"Notes","route":"/research/notes","api":"/api/library/v1/annotations","public":True,"index":False,"parent_surface":"research"},
    {"id":"citation-bibliographic-workspace","label":"Citations","route":"/research/citations","api":"/api/library/v1/citations/workspace","public":True,"index":False,"parent_surface":"research"},
    {"id":"corpus-computational-linguistics-workspace","label":"Corpus","route":"/research/corpus","api":"/api/library/v1/corpus-workspace","public":True,"index":False,"parent_surface":"research"},
    {"id":"entity-place-historical-toponym-workspace","label":"Entities & Places","route":"/research/entities","api":"/api/library/v1/entity-place-workspace","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-synthesis-workspace","label":"Synthesis","route":"/research/synthesis","api":"/api/library/v1/research-synthesis","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-question-investigation-workspace","label":"Investigation","route":"/research/investigation","api":"/api/library/v1/research-investigation","public":True,"index":False,"parent_surface":"research"},
    {"id":"evidence-matrix-claim-support-workspace","label":"Evidence","route":"/research/evidence","api":"/api/library/v1/evidence-matrix","public":True,"index":False,"parent_surface":"research"},
    {"id":"dataset-discovery-statistical-evidence-workspace","label":"Data","route":"/research/data","api":"/api/library/v1/statistical-evidence","public":True,"index":False,"parent_surface":"research"},
    {"id":"geospatial-place-research-workspace","label":"Geospatial","route":"/research/geospatial","api":"/api/library/v1/geospatial-research","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-package-composer-workspace","label":"Package","route":"/research/package","api":"/api/library/v1/research-package-composer","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-publication-studio","label":"Publication","route":"/research/publication","api":"/api/library/v1/research-publication","public":True,"index":False,"parent_surface":"research"},
    {"id":"unified-research-knowledge-graph","label":"Graph","route":"/research/graph","api":"/api/library/v1/research-knowledge-graph","public":True,"index":False,"parent_surface":"research"},
    {"id":"library-workspace-research-integration","label":"Workspace","route":"/research/workspace","api":"/api/library/v1/workspace-integration","public":True,"index":False,"parent_surface":"research"},
    {"id":"cross-product-research-handoff-certification","label":"Integration Certification","route":"/research/integration-certification","api":"/api/library/v1/cross-product-certification","public":True,"index":False,"parent_surface":"research"},
    {"id":"unified-research-project-workspace","label":"Project","route":"/research/project","api":"/api/library/v1/research-project-workspace","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-dependency-lineage-graph","label":"Lineage","route":"/research/project/lineage","api":"/api/library/v1/research-lineage-graph","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-review-revision-versioning","label":"Review & Versions","route":"/research/project/review","api":"/api/library/v1/research-review-versioning","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-package-validation-publication-readiness","label":"Publication Readiness","route":"/research/package/readiness","api":"/api/library/v1/research-package-readiness","public":True,"index":False,"parent_surface":"research"},
    {"id":"portable-research-object-exchange","label":"Exchange","route":"/research/exchange","api":"/api/library/v1/research-object-exchange","public":True,"index":False,"parent_surface":"research"},
    {"id":"collaborative-research-rooms-ii","label":"Rooms","route":"/research/rooms","api":"/api/library/v1/research-rooms","public":True,"index":False,"parent_surface":"research"},
    {"id":"library-librarian-unified-research-intelligence","label":"Research Intelligence","route":"/research/intelligence","api":"/api/library/v1/research-intelligence","public":True,"index":False,"parent_surface":"research"},
    {"id":"research-reproducibility-audit-console","label":"Reproducibility Audit","route":"/research/audit","api":"/api/library/v1/research-audit","public":True,"index":False,"parent_surface":"research"},
    {"id":"cross-library-cross-institution-research-federation","label":"Research Federation","route":"/research/federation","api":"/api/library/v1/research-federation","public":True,"index":False,"parent_surface":"research"},
    {"id":"search","label":"Search","route":"/search","api":"/api/library/v1/search","public":True,"index":False,"alias_of":"research","navigation_mode":"search"},
    {"id":"reader","label":"Reader","route":"/record/{record_id}","api":"/api/library/v1/records/{record_id}","public":True,"index":True},
    {"id":"discover","label":"Discover","route":"/discover","api":"/api/library/v1/capabilities","public":True,"index":False,"alias_of":"research","navigation_mode":"discover"},
    {"id":"system","label":"System","route":"/system","api":"/api/library/v1/readiness","public":True,"index":False},
    {"id":"account","label":"Account","route":"/account","api":"/api/library/v1/session","public":True,"index":False},
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
        "library_version": "7.0.0",
        "backend_version": "4.0.0",
        "web_version": WEB_VERSION,
        "state": "independent-primary-application",
        "deployment_model": "independent-primary-web-service",
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
            "library_session_authority": True,
            "wordpress_session_authority": False,
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
