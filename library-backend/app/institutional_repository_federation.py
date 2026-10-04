from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .institutional_research_network import (
    InstitutionalResearchNetwork,
    InstitutionalResearchNetworkError,
    build_network_connectors,
)
from .connector_federation_service import readiness as connector_federation_readiness
from .global_knowledge_federation_ii import readiness as global_federation_readiness

LIBRARY_VERSION = "6.11.0"
BACKEND_VERSION = "3.11.0"

CONTRACT = "sc-library-institutional-repository-federation/1.0"
READINESS_CONTRACT = "sc-library-institutional-repository-federation-readiness/1.0"
PROFILE_CONTRACT = "sc-library-institutional-repository-profile/1.0"
PLAN_CONTRACT = "sc-library-institutional-repository-federation-plan/1.0"
RESULT_CONTRACT = "sc-library-institutional-repository-federation-result/1.0"
SNAPSHOT_CONTRACT = "sc-library-institutional-repository-snapshot/1.0"
HANDOFF_CONTRACT = "sc-library-institutional-repository-import-handoff/1.0"

MAX_SOURCES = 20
MAX_LIMIT_PER_SOURCE = 25

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _clean(value: Any, limit: int = 4000) -> str:
    return " ".join(str(value or "").split())[:limit]

def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []

def guardrails() -> dict[str, bool]:
    return {
        "python_is_institutional_repository_federation_authority": True,
        "institutional_research_network_remains_connector_execution_authority": True,
        "connector_federation_runtime_remains_connector_policy_authority": True,
        "global_federation_remains_global_source_lens_authority": True,
        "wordpress_php_is_repository_federation_authority": False,
        "repository_visibility_implies_endorsement": False,
        "repository_visibility_implies_affiliation": False,
        "repository_visibility_implies_access_entitlement": False,
        "metadata_visibility_implies_reuse_permission": False,
        "repository_presence_implies_evidence_quality": False,
        "repository_presence_implies_truth": False,
        "doi_deduplication_implies_record_equivalence_beyond_identifier": False,
        "title_only_identity_merge": False,
        "cross_source_author_identity_inferred": False,
        "oai_pmh_is_full_text_search": False,
        "oai_pmh_harvest_is_bounded": True,
        "source_local_failure_containment": True,
        "automatic_repository_harvest": False,
        "automatic_library_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }

def _network(timeout_seconds: int = 8) -> InstitutionalResearchNetwork:
    return InstitutionalResearchNetwork(timeout_seconds=timeout_seconds)

def repository_profiles() -> list[dict[str, Any]]:
    connectors = build_network_connectors(timeout_seconds=8)
    profiles: list[dict[str, Any]] = []
    for connector in connectors:
        d = connector.descriptor
        row = d.to_dict()
        profile = {
            "schema": PROFILE_CONTRACT,
            "source_key": d.key,
            "institution": d.institution,
            "repository": d.repository,
            "source_family": d.source_family,
            "base_url": d.base_url,
            "search_mode": d.search_mode,
            "capabilities": list(d.capabilities),
            "public_metadata": bool(d.public_metadata),
            "affiliation_asserted": bool(d.affiliation_asserted),
            "endorsement_asserted": bool(d.endorsement_asserted),
            "protocol": (
                "oai-pmh" if d.source_family == "oai-pmh"
                else "dataverse-api" if d.source_family == "dataverse"
                else "dspace-rest" if d.source_family == "dspace-rest"
                else d.source_family
            ),
            "execution_authority": "institutional-research-network",
            "automatic_harvest": False,
            "automatic_import": False,
            "guardrails": guardrails(),
        }
        profile["profile_fingerprint_sha256"] = _fp({k:v for k,v in profile.items() if k not in {"profile_fingerprint_sha256"}})
        profiles.append(profile)
    return sorted(profiles, key=lambda x: x["source_key"])

def repository_profile(source_key: str) -> dict[str, Any]:
    key = _clean(source_key, 191)
    for profile in repository_profiles():
        if profile["source_key"] == key:
            return profile
    raise KeyError("institutional-repository-not-found")

def contract() -> dict[str, Any]:
    profiles = repository_profiles()
    resources = [
        "repository-profiles",
        "protocol-and-capability-discovery",
        "bounded-federated-search-plans",
        "institutional-research-network-execution",
        "exact-doi-and-source-identity-deduplication",
        "provenance-ledger-preservation",
        "repository-snapshots",
        "explicit-import-handoffs",
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_keys": [p["source_key"] for p in profiles],
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "federation_id": "institutional-repository-federation:" + _fp(basis)[:32],
        "federation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "execution_authority": "institutional-research-network",
        "connector_policy_authority": "python-connector-federation-runtime",
        "repository_count": len(profiles),
        "source_keys": [p["source_key"] for p in profiles],
        "resources": resources,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    profiles = repository_profiles()
    connector = connector_federation_readiness()
    global_ready = global_federation_readiness()
    connector_state = str(connector.get("state") or "unavailable")
    global_state = str(global_ready.get("state") or "unavailable")
    errors: list[str] = []
    if not profiles:
        errors.append("no-institutional-repositories-configured")
    if connector_state not in {"ready", "degraded"}:
        errors.append("connector-federation-not-ready")
    if global_state not in {"ready", "degraded"}:
        errors.append("global-federation-not-ready")
    degraded = connector_state == "degraded" or global_state == "degraded"
    state = "blocked" if errors else ("degraded" if degraded else "ready")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "ready": not errors,
        "errors": errors,
        "authority": "python-backend-composition",
        "repository_count": len(profiles),
        "source_keys": [p["source_key"] for p in profiles],
        "protocols": sorted({p["protocol"] for p in profiles}),
        "dependencies": {
            "institutional_research_network": {"state": "ready", "version": "2.0"},
            "connector_federation": {
                "state": connector_state,
                "library_version": connector.get("library_version"),
                "backend_version": connector.get("backend_version"),
            },
            "global_federation": {
                "state": global_state,
                "library_version": global_ready.get("library_version"),
                "backend_version": global_ready.get("backend_version"),
            },
        },
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }

def _normalize_request(payload: dict[str, Any] | None) -> dict[str, Any]:
    raw = dict(payload or {})
    query = _clean(raw.get("q") or raw.get("query"), 500)
    selected = []
    for item in _list(raw.get("source_keys") or raw.get("sources")):
        key = _clean(item, 191)
        if key and key not in selected:
            selected.append(key)
    families = []
    for item in _list(raw.get("source_families") or raw.get("families")):
        value = _clean(item, 100).lower()
        if value and value not in families:
            families.append(value)
    protocols = []
    for item in _list(raw.get("protocols")):
        value = _clean(item, 100).lower()
        if value and value not in protocols:
            protocols.append(value)
    try:
        limit = int(raw.get("limit_per_source") or 8)
    except (TypeError, ValueError):
        limit = 8
    limit = max(1, min(MAX_LIMIT_PER_SOURCE, limit))
    try:
        max_sources = int(raw.get("max_sources") or MAX_SOURCES)
    except (TypeError, ValueError):
        max_sources = MAX_SOURCES
    max_sources = max(1, min(MAX_SOURCES, max_sources))
    return {
        "q": query,
        "source_keys": sorted(selected),
        "source_families": sorted(families),
        "protocols": sorted(protocols),
        "limit_per_source": limit,
        "max_sources": max_sources,
    }

def plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    n = _normalize_request(payload)
    profiles = repository_profiles()
    if n["source_keys"]:
        profiles = [p for p in profiles if p["source_key"] in set(n["source_keys"])]
    if n["source_families"]:
        profiles = [p for p in profiles if p["source_family"].lower() in set(n["source_families"])]
    if n["protocols"]:
        profiles = [p for p in profiles if p["protocol"].lower() in set(n["protocols"])]
    profiles = profiles[:n["max_sources"]]
    unknown = sorted(set(n["source_keys"]) - {p["source_key"] for p in repository_profiles()})
    if unknown:
        raise ValueError("unknown institutional repository: " + ", ".join(unknown))
    source_plans = []
    for p in profiles:
        limitations = []
        if p["protocol"] == "oai-pmh":
            limitations.append("OAI-PMH execution is bounded metadata harvesting with local filtering, not arbitrary repository full-text search.")
        source_plans.append({
            "source_key": p["source_key"],
            "institution": p["institution"],
            "repository": p["repository"],
            "protocol": p["protocol"],
            "search_mode": p["search_mode"],
            "capabilities": p["capabilities"],
            "limit": n["limit_per_source"],
            "execution": "bounded-live-metadata-request" if n["q"] else "profile-only",
            "search_limitations": limitations,
            "automatic_import": False,
        })
    basis = {
        "query": n["q"],
        "source_keys": [x["source_key"] for x in source_plans],
        "limit_per_source": n["limit_per_source"],
        "source_plans": source_plans,
    }
    return {
        "schema": PLAN_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "plan_id": "institutional-repository-plan:" + _fp(basis)[:32],
        "plan_fingerprint_sha256": _fp(basis),
        "query": n["q"],
        "source_keys": basis["source_keys"],
        "source_count": len(source_plans),
        "limit_per_source": n["limit_per_source"],
        "source_plans": source_plans,
        "execution_authority": "institutional-research-network",
        "automatic_execution": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }

def _stable_result(result: dict[str, Any]) -> dict[str, Any]:
    def strip(value: Any) -> Any:
        if isinstance(value, dict):
            return {k: strip(v) for k, v in sorted(value.items()) if k not in {"retrieved_at", "checked_at"}}
        if isinstance(value, list):
            return [strip(v) for v in value]
        return value
    return strip(result)

def repository_snapshot(result: dict[str, Any]) -> dict[str, Any]:
    records = [dict(r) for r in _list(result.get("records")) if isinstance(r, dict)]
    status = result.get("source_status") if isinstance(result.get("source_status"), dict) else {}
    source_snapshots = []
    for key in sorted(status):
        st = status[key] if isinstance(status[key], dict) else {}
        source_records = [r for r in records if key in set(r.get("source_keys") or [r.get("source_key")])]
        profile = None
        try:
            profile = repository_profile(key)
        except KeyError:
            profile = {"source_key": key}
        row = {
            "source_key": key,
            "institution": profile.get("institution"),
            "repository": profile.get("repository"),
            "protocol": profile.get("protocol"),
            "state": st.get("state"),
            "record_count": len(source_records),
            "search_mode": st.get("search_mode"),
            "search_limitations": st.get("search_limitations") or [],
            "record_identity_keys": sorted(str(r.get("identity_key") or "") for r in source_records if r.get("identity_key")),
        }
        row["snapshot_fingerprint_sha256"] = _fp(row)
        source_snapshots.append(row)
    basis = {
        "query": result.get("query"),
        "source_snapshots": source_snapshots,
        "record_identity_keys": sorted(str(r.get("identity_key") or "") for r in records if r.get("identity_key")),
        "network_content_fingerprint": (result.get("reproducibility") or {}).get("content_fingerprint"),
    }
    return {
        "schema": SNAPSHOT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "snapshot_id": "institutional-repository-snapshot:" + _fp(basis)[:32],
        "snapshot_fingerprint_sha256": _fp(basis),
        "query": result.get("query"),
        "repository_count": len(source_snapshots),
        "record_count": len(records),
        "source_snapshots": source_snapshots,
        "network_content_fingerprint": basis["network_content_fingerprint"],
        "guardrails": guardrails(),
    }

def search(payload: dict[str, Any], *, network: InstitutionalResearchNetwork | None = None) -> dict[str, Any]:
    p = plan(payload)
    if not p["query"]:
        raise ValueError("q is required for institutional repository search")
    runner = network or _network()
    result = runner.search(
        p["query"],
        source_keys=p["source_keys"] or None,
        limit_per_source=p["limit_per_source"],
    )
    stable = _stable_result(result)
    snapshot = repository_snapshot(result)
    basis = {
        "plan_fingerprint_sha256": p["plan_fingerprint_sha256"],
        "network_result": stable,
        "snapshot_fingerprint_sha256": snapshot["snapshot_fingerprint_sha256"],
    }
    return {
        "schema": RESULT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "federation_result_id": "institutional-repository-result:" + _fp(basis)[:32],
        "federation_result_fingerprint_sha256": _fp(basis),
        "query": p["query"],
        "plan": p,
        "network_state": result.get("network_state"),
        "selected_sources": result.get("selected_sources") or [],
        "source_status": result.get("source_status") or {},
        "record_count": int(result.get("record_count") or 0),
        "observation_count": int(result.get("observation_count") or 0),
        "duplicate_observation_consolidation_count": int(result.get("duplicate_observation_consolidation_count") or 0),
        "records": result.get("records") or [],
        "errors": result.get("errors") or [],
        "repository_snapshot": snapshot,
        "reproducibility": result.get("reproducibility") or {},
        "handoffs": result.get("handoffs") or {},
        "automatic_import": False,
        "guardrails": guardrails(),
    }

def import_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    raw_records = [dict(r) for r in _list(payload.get("records")) if isinstance(r, dict)]
    selected_keys = []
    for item in _list(payload.get("identity_keys")):
        key = _clean(item, 500)
        if key and key not in selected_keys:
            selected_keys.append(key)
    if selected_keys:
        records = [r for r in raw_records if str(r.get("identity_key") or "") in set(selected_keys)]
    else:
        records = raw_records
    normalized = []
    for r in records[:500]:
        normalized.append({
            "external_identity_key": r.get("identity_key"),
            "source_keys": r.get("source_keys") or ([r.get("source_key")] if r.get("source_key") else []),
            "title": r.get("title"),
            "persistent_id": r.get("persistent_id"),
            "doi": r.get("doi"),
            "source_url": r.get("source_url"),
            "authors": r.get("authors") or [],
            "published_at": r.get("published_at"),
            "record_type": r.get("record_type"),
            "license": r.get("license"),
            "provenance_ledger": r.get("provenance_ledger") or [],
            "access_state": r.get("access_state"),
        })
    basis = {
        "records": normalized,
        "target": _clean(payload.get("target") or "library-source-ingestion", 100),
        "project_id": _clean(payload.get("project_id"), 300) or None,
    }
    return {
        "schema": HANDOFF_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff_id": "institutional-repository-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "target": basis["target"],
        "project_id": basis["project_id"],
        "record_count": len(normalized),
        "records": normalized,
        "persisted": False,
        "execution_required": True,
        "automatic_import": False,
        "automatic_evidence_promotion": False,
        "guardrails": guardrails(),
    }

def schema_registry() -> dict[str, Any]:
    return {
        "schema": "sc-library-institutional-repository-federation-schema-registry/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "schemas": {
            "contract": CONTRACT,
            "readiness": READINESS_CONTRACT,
            "repository_profile": PROFILE_CONTRACT,
            "plan": PLAN_CONTRACT,
            "result": RESULT_CONTRACT,
            "snapshot": SNAPSHOT_CONTRACT,
            "import_handoff": HANDOFF_CONTRACT,
        },
        "guardrails": guardrails(),
    }
