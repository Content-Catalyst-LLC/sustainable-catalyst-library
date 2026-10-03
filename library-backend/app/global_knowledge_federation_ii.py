from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .advanced_discovery import (
    execute as execute_advanced_discovery,
    plan as plan_advanced_discovery,
    readiness as advanced_discovery_readiness,
)
from .connector_federation_service import (
    connector_status as federation_connector_status,
    plan_execution as plan_connector_execution,
    readiness as connector_federation_readiness,
)
from .global_source_federation import registry

LIBRARY_VERSION = "6.5.0"
BACKEND_VERSION = "3.5.0"
CONTRACT = "sc-library-global-knowledge-federation-ii/1.0"
READINESS_CONTRACT = "sc-library-global-knowledge-federation-ii-readiness/1.0"
LENSES_CONTRACT = "sc-library-global-knowledge-federation-ii-lenses/1.0"
SOURCE_PROFILE_CONTRACT = "sc-library-global-federation-source-profile/1.0"
PLAN_CONTRACT = "sc-library-global-federation-discovery-plan/1.0"
DISCOVERY_CONTRACT = "sc-library-global-federated-discovery/1.0"
MAX_SOURCES = 100


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any) -> str:
    return str(value or "").strip().lower()


def _terms(value: Any) -> list[str]:
    if value is None:
        return []
    if isinstance(value, str):
        value = [value]
    if not isinstance(value, (list, tuple, set)):
        return []
    return sorted({_clean(item) for item in value if _clean(item)})


# These are discovery-lens hints, not quality judgments or exhaustive linguistic claims.
# The canonical source/connector records remain in global_source_federation.py.
SOURCE_LENS_HINTS: dict[str, dict[str, list[str]]] = {
    "crossref": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-scholarly"]},
    "openalex": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-scholarly"]},
    "datacite": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-scholarly"]},
    "pubmed": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["biomedical"]},
    "pmc": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["biomedical"]},
    "europepmc": {"regions": ["global", "europe"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["biomedical", "european"]},
    "ucd": {"regions": ["europe", "ireland"], "languages": ["en", "ga"], "scripts": ["latin"], "contexts": ["irish", "european"]},
    "dri": {"regions": ["europe", "ireland"], "languages": ["en", "ga"], "scripts": ["latin"], "contexts": ["irish", "european", "cultural-heritage"]},
    "nli": {"regions": ["europe", "ireland"], "languages": ["en", "ga"], "scripts": ["latin"], "contexts": ["irish", "european", "cultural-heritage"]},
    "british-library": {"regions": ["europe", "united-kingdom"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["british", "global-cultural-heritage"]},
    "europeana": {"regions": ["europe"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["european", "cultural-heritage"]},
    "hal": {"regions": ["europe", "france"], "languages": ["fr", "en", "und"], "scripts": ["latin"], "contexts": ["french", "european", "scholarly"]},
    "doaj": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-open-scholarship"]},
    "zenodo": {"regions": ["global", "europe"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-open-scholarship", "research-data"]},
    "openaire": {"regions": ["global", "europe"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-open-scholarship", "european"]},
    "core": {"regions": ["global", "united-kingdom"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["global-open-scholarship"]},
    "scielo": {"regions": ["latin-america", "global-south"], "languages": ["es", "pt", "en"], "scripts": ["latin"], "contexts": ["latin-american", "open-scholarship"]},
    "la-referencia": {"regions": ["latin-america"], "languages": ["es", "pt"], "scripts": ["latin"], "contexts": ["latin-american", "institutional-repositories"]},
    "cinii": {"regions": ["east-asia", "japan"], "languages": ["ja", "en"], "scripts": ["han", "hiragana", "katakana", "latin"], "contexts": ["japanese", "east-asian"]},
    "jstage": {"regions": ["east-asia", "japan"], "languages": ["ja", "en"], "scripts": ["han", "hiragana", "katakana", "latin"], "contexts": ["japanese", "east-asian"]},
    "ndl-search": {"regions": ["east-asia", "japan"], "languages": ["ja", "en"], "scripts": ["han", "hiragana", "katakana", "latin"], "contexts": ["japanese", "cultural-heritage"]},
    "cnki": {"regions": ["east-asia", "china"], "languages": ["zh", "en"], "scripts": ["han", "latin"], "contexts": ["chinese", "east-asian"]},
    "cyberleninka": {"regions": ["eurasia", "russia"], "languages": ["ru"], "scripts": ["cyrillic"], "contexts": ["russian-language", "eurasian"]},
    "elibrary-ru": {"regions": ["eurasia", "russia"], "languages": ["ru"], "scripts": ["cyrillic"], "contexts": ["russian-language", "eurasian"]},
    "sid-iran": {"regions": ["middle-east", "iran"], "languages": ["fa", "en"], "scripts": ["arabic", "latin"], "contexts": ["persian-language", "iranian"]},
    "irandoc": {"regions": ["middle-east", "iran"], "languages": ["fa", "en"], "scripts": ["arabic", "latin"], "contexts": ["persian-language", "iranian", "institutional-repositories"]},
    "dergipark": {"regions": ["middle-east", "europe", "turkey"], "languages": ["tr", "en", "und"], "scripts": ["latin"], "contexts": ["turkish", "anatolian", "open-scholarship"]},
    "qatar-digital-library": {"regions": ["middle-east", "gulf"], "languages": ["ar", "en"], "scripts": ["arabic", "latin"], "contexts": ["arabic-language", "gulf", "cultural-heritage"]},
    "arabic-collections-online": {"regions": ["middle-east", "north-africa"], "languages": ["ar"], "scripts": ["arabic"], "contexts": ["arabic-language", "cultural-heritage"]},
    "ajol": {"regions": ["africa", "global-south"], "languages": ["en", "fr", "pt", "und"], "scripts": ["latin", "multiple"], "contexts": ["african", "open-scholarship"]},
    "scielo-south-africa": {"regions": ["africa", "southern-africa", "global-south"], "languages": ["en", "und"], "scripts": ["latin", "multiple"], "contexts": ["african", "open-scholarship"]},
    "shodhganga": {"regions": ["south-asia", "india"], "languages": ["en", "hi", "und"], "scripts": ["latin", "devanagari", "multiple-indic"], "contexts": ["indian", "south-asian", "theses"]},
    "world-bank-data": {"regions": ["global"], "languages": ["en", "und"], "scripts": ["multiple"], "contexts": ["development-data", "public-statistics"]},
    "eurostat": {"regions": ["europe"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["european", "public-statistics"]},
    "oecd-data": {"regions": ["global"], "languages": ["en", "fr", "und"], "scripts": ["latin", "multiple"], "contexts": ["public-statistics", "policy-data"]},
    "un-data": {"regions": ["global"], "languages": ["und"], "scripts": ["multiple"], "contexts": ["public-statistics", "un-system"]},
}


def guardrails() -> dict[str, bool]:
    return {
        "original_language_is_canonical": True,
        "translation_is_derived_representation": True,
        "transliteration_is_derived_representation": True,
        "all_transformations_require_provenance": True,
        "source_quality_signals_separate_from_user_trust_choices": True,
        "user_trust_policy_is_user_controlled": True,
        "federation_membership_implies_source_quality": False,
        "federation_membership_implies_source_endorsement": False,
        "federation_membership_implies_partnership": False,
        "federation_membership_implies_evidence_truth": False,
        "lens_hints_are_exhaustive_language_coverage": False,
        "lens_hints_are_quality_rankings": False,
        "connector_health_implies_research_quality": False,
        "registry_only_source_is_silently_executed": False,
        "browser_handoff_is_silently_executed": False,
        "automatic_external_fetch": False,
        "automatic_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "wordpress_required": False,
    }


def _profile_from_source(source: dict[str, Any]) -> dict[str, Any]:
    source_id = _clean(source.get("source_id"))
    connector_id = _clean(source.get("connector_id"))
    hints = SOURCE_LENS_HINTS.get(source_id, {})
    try:
        status = federation_connector_status(connector_id)
    except Exception:
        status = {"mode": "registry-only", "python_native": False, "browser_handoff": False}
    return {
        "schema": SOURCE_PROFILE_CONTRACT,
        "source_id": source_id,
        "name": source.get("name"),
        "institution_id": source.get("institution_id"),
        "source_family": source.get("source_family"),
        "access_mode": source.get("access_mode"),
        "coverage_scope": list(source.get("coverage_scope") or []),
        "content_types": list(source.get("content_types") or []),
        "collection_ids": list(source.get("collection_ids") or []),
        "connector_id": connector_id,
        "connector_mode": status.get("mode") or "registry-only",
        "python_native": bool(status.get("python_native")),
        "browser_handoff": bool(status.get("browser_handoff")),
        "regions": list(hints.get("regions") or (["global"] if "global" in (source.get("coverage_scope") or []) else [])),
        "languages": list(hints.get("languages") or ["und"]),
        "scripts": list(hints.get("scripts") or ["multiple"]),
        "context_lenses": list(hints.get("contexts") or []),
        "lens_metadata_is_discovery_hint": True,
        "guardrails": guardrails(),
    }


def source_profiles() -> list[dict[str, Any]]:
    snap = registry.snapshot()
    return [_profile_from_source(source) for source in snap.get("sources") or []]


def source_profile(source_id: str) -> dict[str, Any]:
    bundle = registry.source(source_id)
    profile = _profile_from_source(bundle["source"])
    profile["institution"] = bundle.get("institution")
    profile["collections"] = bundle.get("collections")
    profile["registry_fingerprint_sha256"] = bundle.get("registry_fingerprint_sha256")
    return profile


def _lens_index(profiles: list[dict[str, Any]], field: str) -> list[dict[str, Any]]:
    grouped: dict[str, list[str]] = {}
    for profile in profiles:
        for value in profile.get(field) or []:
            key = _clean(value)
            if key:
                grouped.setdefault(key, []).append(profile["source_id"])
    return [
        {"id": key, "source_count": len(sorted(set(values))), "source_ids": sorted(set(values))}
        for key, values in sorted(grouped.items())
    ]


def lenses() -> dict[str, Any]:
    profiles = source_profiles()
    modes: dict[str, list[str]] = {}
    for profile in profiles:
        modes.setdefault(_clean(profile.get("connector_mode")) or "registry-only", []).append(profile["source_id"])
    basis = {
        "source_ids": [p["source_id"] for p in profiles],
        "regions": _lens_index(profiles, "regions"),
        "languages": _lens_index(profiles, "languages"),
        "scripts": _lens_index(profiles, "scripts"),
        "contexts": _lens_index(profiles, "context_lenses"),
    }
    return {
        "schema": LENSES_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "lens_fingerprint_sha256": _fp(basis),
        "source_count": len(profiles),
        "regions": basis["regions"],
        "languages": basis["languages"],
        "scripts": basis["scripts"],
        "context_lenses": basis["contexts"],
        "connector_modes": [
            {"id": key, "source_count": len(sorted(set(values))), "source_ids": sorted(set(values))}
            for key, values in sorted(modes.items())
        ],
        "guardrails": guardrails(),
    }


def contract() -> dict[str, Any]:
    registry_ready = registry.readiness()
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "registry_fingerprint_sha256": registry_ready.get("registry_fingerprint_sha256"),
        "resources": [
            "global-source-lenses",
            "multilingual-source-profiles",
            "regional-and-context-lenses",
            "user-trust-policy-separation",
            "federated-discovery-plans",
            "advanced-discovery-local-execution",
            "connector-execution-plans",
            "original-language-first-query-representations",
        ],
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "federation_id": "global-knowledge-federation-ii:" + _fp(basis)[:32],
        "federation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "registry_foundation": "v5.44.0",
        "advanced_discovery_foundation": "v6.4.0",
        "wordpress_required": False,
        "automatic_external_fetch": False,
        "resources": basis["resources"],
        "guardrails": guardrails(),
    }


def _normalize_request(payload: dict[str, Any] | None) -> dict[str, Any]:
    raw = dict(payload or {})
    max_sources = max(1, min(MAX_SOURCES, int(raw.get("max_sources") or 50)))
    normalized = {
        "q": str(raw.get("q") or "").strip()[:4000],
        "language": _clean(raw.get("language") or raw.get("language_tag")) or None,
        "script": _clean(raw.get("script")) or None,
        "regions": _terms(raw.get("regions") or raw.get("region")),
        "languages": _terms(raw.get("languages")),
        "scripts": _terms(raw.get("scripts")),
        "context_lenses": _terms(raw.get("context_lenses") or raw.get("contexts")),
        "collections": _terms(raw.get("collections") or raw.get("collection_ids")),
        "source_families": _terms(raw.get("source_families") or raw.get("families")),
        "source_ids": _terms(raw.get("source_ids")),
        "trusted_source_ids": _terms(raw.get("trusted_source_ids")),
        "excluded_source_ids": _terms(raw.get("excluded_source_ids")),
        "include_browser_handoffs": raw.get("include_browser_handoffs", True) is not False,
        "include_registry_only": raw.get("include_registry_only", True) is not False,
        "include_python_adapter_pending": raw.get("include_python_adapter_pending", True) is not False,
        "cross_language": raw.get("cross_language", True) is not False,
        "query_representations": raw.get("query_representations") if isinstance(raw.get("query_representations"), list) else [],
        "mode": _clean(raw.get("mode") or "hybrid") or "hybrid",
        "rerank": _clean(raw.get("rerank") or "neural") or "neural",
        "max_sources": max_sources,
    }
    if normalized["language"] and normalized["language"] not in normalized["languages"]:
        normalized["languages"].append(normalized["language"])
        normalized["languages"].sort()
    if normalized["script"] and normalized["script"] not in normalized["scripts"]:
        normalized["scripts"].append(normalized["script"])
        normalized["scripts"].sort()
    return normalized


def _matches(profile: dict[str, Any], n: dict[str, Any]) -> bool:
    source_id = profile["source_id"]
    if source_id in set(n["excluded_source_ids"]):
        return False
    if n["source_ids"] and source_id not in set(n["source_ids"]):
        return False
    if n["regions"] and not set(n["regions"]).intersection(_terms(profile.get("regions"))):
        return False
    if n["languages"] and not ({"und"} | set(n["languages"])).intersection(_terms(profile.get("languages"))):
        return False
    if n["scripts"] and not ({"multiple"} | set(n["scripts"])).intersection(_terms(profile.get("scripts"))):
        return False
    if n["context_lenses"] and not set(n["context_lenses"]).intersection(_terms(profile.get("context_lenses"))):
        return False
    if n["collections"] and not set(n["collections"]).intersection(_terms(profile.get("collection_ids"))):
        return False
    if n["source_families"] and _clean(profile.get("source_family")) not in set(n["source_families"]):
        return False
    mode = _clean(profile.get("connector_mode"))
    if mode == "browser-handoff" and not n["include_browser_handoffs"]:
        return False
    if mode == "registry-only" and not n["include_registry_only"]:
        return False
    if mode == "python-adapter-pending" and not n["include_python_adapter_pending"]:
        return False
    return True


def plan(payload: dict[str, Any] | None) -> dict[str, Any]:
    n = _normalize_request(payload)
    selected = [p for p in source_profiles() if _matches(p, n)]
    selected = sorted(selected, key=lambda p: p["source_id"])[: n["max_sources"]]
    trusted = set(n["trusted_source_ids"])
    source_plans: list[dict[str, Any]] = []
    mode_counts: dict[str, int] = {}
    for profile in selected:
        connector_id = profile.get("connector_id")
        try:
            execution = plan_connector_execution({"connector_id": connector_id, "query": {"q": n["q"]}})
        except Exception as exc:
            execution = {
                "connector_id": connector_id,
                "source_id": profile["source_id"],
                "mode": profile.get("connector_mode") or "registry-only",
                "permitted": False,
                "action": "registry-reference-only",
                "planning_error": str(exc),
            }
        mode = _clean(execution.get("mode")) or "registry-only"
        mode_counts[mode] = mode_counts.get(mode, 0) + 1
        source_plans.append({
            "source_id": profile["source_id"],
            "source_name": profile.get("name"),
            "connector_id": connector_id,
            "connector_mode": mode,
            "execution": execution,
            "user_trust": "trusted" if profile["source_id"] in trusted else "unspecified",
            "regions": profile.get("regions") or [],
            "languages": profile.get("languages") or [],
            "scripts": profile.get("scripts") or [],
            "context_lenses": profile.get("context_lenses") or [],
        })
    local_discovery_plan = None
    if n["q"]:
        local_discovery_plan = plan_advanced_discovery({
            "q": n["q"],
            "language": n["language"],
            "script": n["script"],
            "cross_language": n["cross_language"],
            "query_representations": n["query_representations"],
            "mode": n["mode"],
            "rerank": n["rerank"],
            "limit": 20,
        })
    basis = {
        "normalized": n,
        "source_ids": [item["source_id"] for item in source_plans],
        "connector_modes": mode_counts,
        "local_discovery_plan_id": (local_discovery_plan or {}).get("plan_id"),
    }
    return {
        "schema": PLAN_CONTRACT,
        "plan_id": "global-federation-plan:" + _fp(basis)[:32],
        "plan_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend-composition",
        "normalized": n,
        "selected_source_count": len(source_plans),
        "connector_modes": dict(sorted(mode_counts.items())),
        "source_plans": source_plans,
        "local_advanced_discovery": local_discovery_plan,
        "external_execution_state": "planned-not-executed",
        "automatic_external_fetch": False,
        "trust_policy": {
            "trusted_source_ids": n["trusted_source_ids"],
            "excluded_source_ids": n["excluded_source_ids"],
            "trust_changes_source_quality_signals": False,
            "unspecified_sources_are_automatically_distrusted": False,
        },
        "guardrails": guardrails(),
    }


def discover(payload: dict[str, Any] | None) -> dict[str, Any]:
    n = _normalize_request(payload)
    if not n["q"]:
        raise ValueError("q is required for global federated discovery")
    federation_plan = plan(n)
    local = execute_advanced_discovery({
        "q": n["q"],
        "language": n["language"],
        "script": n["script"],
        "cross_language": n["cross_language"],
        "query_representations": n["query_representations"],
        "mode": n["mode"],
        "rerank": n["rerank"],
        "limit": 20,
    })
    basis = {
        "federation_plan_id": federation_plan["plan_id"],
        "local_plan_id": local.get("plan_id") or local.get("search_id"),
        "query": n["q"],
    }
    return {
        "schema": DISCOVERY_CONTRACT,
        "discovery_id": "global-federated-discovery:" + _fp(basis)[:32],
        "discovery_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "local-results-plus-external-source-plan",
        "query": n["q"],
        "local_library_discovery": local,
        "global_federation_plan": federation_plan,
        "external_sources_executed": False,
        "automatic_external_fetch": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    registry_ready = registry.readiness()
    connector_ready = connector_federation_readiness()
    discovery_ready = advanced_discovery_readiness()
    lens_snapshot = lenses()
    registry_state = registry_ready.get("state")
    discovery_state = discovery_ready.get("state")
    state = "ready" if registry_state == "ready" and discovery_state in {"ready", "degraded"} else "degraded"
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "authority": "python-backend-composition",
        "wordpress_required": False,
        "database_migration_required": False,
        "source_count": lens_snapshot.get("source_count", 0),
        "region_lens_count": len(lens_snapshot.get("regions") or []),
        "language_lens_count": len(lens_snapshot.get("languages") or []),
        "script_lens_count": len(lens_snapshot.get("scripts") or []),
        "context_lens_count": len(lens_snapshot.get("context_lenses") or []),
        "dependencies": {
            "global_source_registry": {"state": registry_state, "version": registry_ready.get("version")},
            "connector_federation": {"state": connector_ready.get("state"), "library_version": connector_ready.get("library_version")},
            "advanced_discovery": {"state": discovery_state, "library_version": discovery_ready.get("library_version")},
        },
        "capabilities": {
            "global_source_lenses": True,
            "regional_language_script_context_lenses": True,
            "user_trust_policy_separation": True,
            "cross_language_discovery_planning": True,
            "connector_execution_planning": True,
            "local_advanced_discovery_execution": True,
            "automatic_external_fetch": False,
            "automatic_external_import": False,
        },
        "guardrails": guardrails(),
    }
