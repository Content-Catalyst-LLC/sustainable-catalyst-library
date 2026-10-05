from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .catalog_service import get_research_object, readiness as catalog_readiness
from .retrieval_orchestration import execute as execute_retrieval, facet_snapshot, readiness as retrieval_readiness
from .provenance_graph_service import (
    record_provenance,
    citations_for_record,
    evidence_graph,
    readiness as provenance_readiness,
)
from .research_state import owner_state, readiness as research_state_readiness

LIBRARY_VERSION = "6.26.0"
BACKEND_VERSION = "3.26.0"
WEB_VERSION = "2.26.0"
SDK_VERSION = "1.26.0"

CONTRACT = "sc-library-independent-research-interface/1.0"
READINESS_CONTRACT = "sc-library-independent-research-interface-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-independent-research-interface-bootstrap/1.0"
SEARCH_CONTRACT = "sc-library-independent-research-interface-search/1.0"
RECORD_CONTEXT_CONTRACT = "sc-library-independent-research-interface-record-context/1.0"
OWNER_CONTEXT_CONTRACT = "sc-library-independent-research-interface-owner-context/1.0"

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def guardrails() -> dict[str, bool]:
    return {
        "research_interface_is_composition_layer": True,
        "research_interface_is_new_state_authority": False,
        "research_interface_changes_source_content": False,
        "research_interface_changes_evidence_status": False,
        "research_interface_changes_truth_status": False,
        "working_set_browser_state_is_authoritative": False,
        "working_set_browser_state_is_persistent_research_state": False,
        "saved_projects_require_library_identity": True,
        "saved_projects_use_python_research_state_service": True,
        "search_uses_python_retrieval_orchestration": True,
        "record_context_uses_python_catalog_and_provenance_services": True,
        "ranking_score_is_truth_probability": False,
        "semantic_similarity_is_evidence_equivalence": False,
        "wordpress_required": False,
        "wordpress_is_research_interface_authority": False,
        "api_v1_is_service_boundary": True,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str, Any]:
    surfaces = [
        {"id": "research-home", "route": "/research", "purpose": "integrated-research-entry"},
        {"id": "discovery", "route": "/research#discovery", "purpose": "faceted-search-and-retrieval"},
        {"id": "working-set", "route": "/research#working-set", "purpose": "browser-local-source-shortlist"},
        {"id": "historical-archives", "route": "/research/archives", "purpose": "primary-source-and-archival-research-workspace"},
        {"id": "primary-source-criticism", "route": "/research/archives/compare", "purpose": "primary-source-comparison-and-source-criticism-workspace"},
        {"id": "historical-event-timeline", "route": "/research/archives/timeline", "purpose": "uncertainty-preserving-historical-event-and-chronology-workspace"},
        {"id": "research-annotation-notes", "route": "/research/notes", "purpose": "source-anchored-research-annotation-and-scholarly-notes-workspace"},
        {"id": "citation-bibliographic-workspace", "route": "/research/citations", "purpose": "citation-workspace-and-bibliographic-intelligence"},
        {"id": "corpus-computational-linguistics-workspace", "route": "/research/corpus", "purpose": "corpus-and-computational-linguistics-analysis"},
        {"id": "entity-place-historical-toponym-workspace", "route": "/research/entities", "purpose": "multilingual-entity-place-and-historical-toponym-resolution"},
        {"id": "research-synthesis-workspace", "route": "/research/synthesis", "purpose": "cross-source-research-synthesis-and-disagreement-preservation"},
        {"id": "research-question-investigation-workspace", "route": "/research/investigation", "purpose": "research-question-framing-and-investigation-control"},
        {"id": "evidence-matrix-claim-support-workspace", "route": "/research/evidence", "purpose": "claim-evidence-matrix-and-support-analysis"},
        {"id": "dataset-discovery-statistical-evidence-workspace", "route": "/research/data", "purpose": "dataset-discovery-and-statistical-evidence-analysis"},
        {"id": "geospatial-place-research-workspace", "route": "/research/geospatial", "purpose": "geospatial-and-place-based-research-analysis"},
        {"id": "research-package-composer-workspace", "route": "/research/package", "purpose": "research-package-composition-audit-and-handoff"},
        {"id": "research-publication-studio", "route": "/research/publication", "purpose": "publication-draft-editorial-audit-and-publishing-handoff"},
        {"id": "record-context", "route": "/record/{record_id}", "purpose": "research-object-provenance-citations-evidence"},
        {"id": "saved-research", "route": "/account", "purpose": "service-native-project-and-collection-handoff"},
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "surfaces": surfaces,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "contract_id": "independent-research-interface:" + _fp(basis)[:32],
        "contract_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "application": "independent-library-web",
        "web_route": "/research",
        "api_base_path": "/api/library/v1/research-interface",
        "surfaces": surfaces,
        "components": {
            "retrieval": "python-retrieval-orchestration",
            "research_objects": "python-catalog-service",
            "provenance": "python-provenance-citation-evidence-graph-service",
            "saved_research": "python-research-state-service",
            "identity": "library-service-native-identity",
        },
        "handoffs": {
            "public_search": "/api/library/v1/research-interface/search",
            "record_context": "/api/library/v1/research-interface/records/{record_id}",
            "owner_state": "/api/library/v1/admin/research-interface/owners/{owner_identity_id}",
            "project_write": "/api/library/v1/workspaces/projects",
            "saved_workspace": "/api/library/v1/workspaces",
            "collection_write": "/api/library/v1/admin/research-state/collections",
        },
        "working_set": {
            "default_store": "browser-local",
            "authoritative": False,
            "persistent_research_state": False,
            "upgrade_path": "library-saved-workspace-project",
            "session_save_endpoint": "/api/library/v1/workspaces/projects/{project_id}/working-set",
        },
        "wordpress": {
            "role": "optional-adapter",
            "required": False,
            "authoritative": False,
        },
        "next_release": "6.27.0",
        "next_release_name": "Unified Research Knowledge Graph",
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    catalog = catalog_readiness()
    retrieval = retrieval_readiness()
    provenance = provenance_readiness()
    state = research_state_readiness()

    checks = {
        "catalog": catalog.get("state") == "ready",
        "retrieval": retrieval.get("state") == "ready",
        "provenance": provenance.get("state") in {"ready", "degraded"},
        "research_state": state.get("state") == "ready",
    }
    errors = [name + "-not-ready" for name, ok in checks.items() if not ok]
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready" if not errors else "blocked",
        "ready": not errors,
        "errors": errors,
        "authority": "python-backend",
        "wordpress_required": False,
        "components": {
            "catalog": catalog,
            "retrieval": retrieval,
            "provenance": provenance,
            "research_state": state,
        },
        "guardrails": guardrails(),
    }

def bootstrap() -> dict[str, Any]:
    facets = facet_snapshot()
    c = contract()
    basis = {
        "modes": ["hybrid", "lexical", "semantic"],
        "sorts": ["relevance", "updated", "newest", "oldest", "title"],
        "facets": facets.get("facets", {}),
        "working_set": c["working_set"],
    }
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "bootstrap_id": "research-interface-bootstrap:" + _fp(basis)[:32],
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "search": {
            "modes": ["hybrid", "lexical", "semantic"],
            "sorts": ["relevance", "updated", "newest", "oldest", "title"],
            "default_mode": "hybrid",
            "default_sort": "relevance",
            "max_page_size": 100,
        },
        "facets": facets.get("facets", {}),
        "working_set": c["working_set"],
        "handoffs": c["handoffs"],
        "guardrails": guardrails(),
    }

def search(payload: dict[str, Any] | None) -> dict[str, Any]:
    request = dict(payload or {})
    request["rerank"] = "none"
    result = execute_retrieval(request)
    return {
        "schema": SEARCH_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "interface": "independent-library-research-interface",
        "result": result,
        "working_set_persistence": "browser-local-until-explicit-project-or-collection-write",
        "guardrails": guardrails(),
    }

def record_context(
    record_id: str,
    *,
    include_body: bool = True,
    version_limit: int = 12,
    evidence_depth: int = 1,
    evidence_limit: int = 120,
) -> dict[str, Any]:
    obj = get_research_object(record_id, include_body=include_body, public_only=True)
    if obj is None:
        raise ValueError("research-object-not-found")

    provenance = record_provenance(record_id, version_limit=max(1, min(50, int(version_limit))))
    citations = citations_for_record(record_id, direction="both", limit=max(1, min(250, int(evidence_limit))))
    graph = evidence_graph(
        record_id,
        depth=max(1, min(2, int(evidence_depth))),
        limit=max(1, min(250, int(evidence_limit))),
        include_core=True,
    )
    basis = {
        "record_id": record_id,
        "content_hash": (obj.get("identity") or {}).get("content_hash"),
        "revision": (obj.get("identity") or {}).get("revision"),
        "lineage": provenance.get("lineage_fingerprint_sha256"),
        "graph": graph.get("graph_fingerprint_sha256"),
    }
    return {
        "schema": RECORD_CONTEXT_CONTRACT,
        "context_id": "research-record-context:" + _fp(basis)[:32],
        "context_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_id": record_id,
        "research_object": obj,
        "provenance": {
            "lineage_fingerprint_sha256": provenance.get("lineage_fingerprint_sha256"),
            "record_versions": provenance.get("record_versions", []),
            "normalization_lineage": provenance.get("normalization_lineage", []),
            "ingest_events": provenance.get("ingest_events", []),
        },
        "citations": {
            "count": citations.get("count", 0),
            "items": citations.get("items", []),
        },
        "evidence_graph": {
            "node_count": graph.get("node_count", 0),
            "edge_count": graph.get("edge_count", 0),
            "nodes": graph.get("nodes", []),
            "edges": graph.get("edges", []),
            "graph_fingerprint_sha256": graph.get("graph_fingerprint_sha256"),
        },
        "guardrails": guardrails(),
    }

def owner_workspace(owner_identity_id: str) -> dict[str, Any]:
    snapshot = owner_state(owner_identity_id)
    return {
        "schema": OWNER_CONTEXT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "owner_identity_id": owner_identity_id,
        "authority": "python-research-state-service",
        "state": snapshot,
        "interface_handoffs": contract()["handoffs"],
        "guardrails": guardrails(),
    }
