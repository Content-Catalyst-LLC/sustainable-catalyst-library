from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .historical_archive_primary_source import (
    analyze_primary_source,
    build_primary_source_packet,
    build_timeline,
    compare_primary_sources,
    ingestion_handoff,
    normalize_primary_source,
    plan_archive_search,
    provenance_chain,
    readiness as historical_archive_readiness,
    source_type_registry,
)
from .institutional_repository_federation import (
    repository_profiles,
    search as search_institutional_repositories,
)

LIBRARY_VERSION = "6.13.0"
BACKEND_VERSION = "3.13.0"
WEB_VERSION = "2.13.0"
SDK_VERSION = "1.13.0"

CONTRACT = "sc-library-historical-archives-research-workspace/1.0"
READINESS_CONTRACT = "sc-library-historical-archives-research-workspace-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-historical-archives-research-workspace-bootstrap/1.0"
SEARCH_CONTRACT = "sc-library-historical-archives-research-workspace-search/1.0"
SOURCE_CONTRACT = "sc-library-historical-archives-source-workspace/1.0"
COMPARE_CONTRACT = "sc-library-historical-archives-comparison-workspace/1.0"
TIMELINE_CONTRACT = "sc-library-historical-archives-timeline-workspace/1.0"
PACKET_CONTRACT = "sc-library-historical-archives-packet-workspace/1.0"
HANDOFF_CONTRACT = "sc-library-historical-archives-handoff-workspace/1.0"

MAX_WORKSPACE_SOURCES = 50


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []


def _clean(value: Any, limit: int = 4000) -> str:
    return " ".join(str(value or "").split())[:limit]


def guardrails() -> dict[str, bool]:
    return {
        "python_is_historical_archives_workspace_authority": True,
        "historical_archive_v612_remains_primary_source_intelligence_authority": True,
        "institutional_repository_federation_remains_repository_execution_authority": True,
        "workspace_search_requires_explicit_user_execution": True,
        "workspace_search_is_automatic_harvest": False,
        "workspace_search_results_imply_authenticity": False,
        "workspace_search_results_imply_truth": False,
        "primary_source_label_implies_truth": False,
        "primary_source_label_implies_accuracy": False,
        "ocr_htr_transcription_are_original_text": False,
        "translation_is_original_text": False,
        "uncertain_dates_collapsed_to_false_precision": False,
        "source_criticism_is_authenticity_certification": False,
        "source_criticism_is_truth_scoring": False,
        "cross_source_disagreement_is_auto_resolved": False,
        "workspace_packet_is_automatically_persisted": False,
        "workspace_handoff_is_automatically_executed": False,
        "automatic_library_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "archive-search-and-repository-selection",
        "primary-source-normalization-and-source-criticism",
        "original-surrogate-ocr-htr-transcription-translation-lineage",
        "side-by-side-primary-source-comparison",
        "uncertainty-preserving-historical-timeline",
        "reproducible-primary-source-packet-preview",
        "explicit-library-ingestion-handoff-preview",
        "standalone-web-route-/research/archives",
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "workspace_id": "historical-archives-workspace:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend",
        "route": "/research/archives",
        "resources": resources,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    archive = historical_archive_readiness()
    profiles = repository_profiles()
    archive_state = str(_dict(archive).get("state") or "unknown")
    blocking: list[str] = []
    degraded: list[str] = []
    if archive_state in {"blocked", "unavailable", "failed"}:
        blocking.append("historical-archive-primary-source-intelligence:" + archive_state)
    elif archive_state != "ready":
        degraded.append("historical-archive-primary-source-intelligence:" + archive_state)
    if not profiles:
        degraded.append("no-institutional-repository-profiles")
    state = "blocked" if blocking else ("degraded" if degraded else "ready")
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": state,
        "ready": not blocking,
        "blocking": blocking,
        "degraded": degraded,
        "repository_profile_count": len(profiles),
        "dependency": {
            "historical_archive_primary_source_intelligence": {
                "state": archive_state,
                "library_version": archive.get("library_version"),
                "backend_version": archive.get("backend_version"),
            }
        },
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    types = source_type_registry()
    profiles = repository_profiles()
    repositories = [
        {
            "source_key": p.get("source_key"),
            "institution": p.get("institution"),
            "repository": p.get("repository"),
            "protocol": p.get("protocol"),
            "source_family": p.get("source_family"),
            "search_mode": p.get("search_mode"),
            "capabilities": p.get("capabilities") or [],
        }
        for p in profiles
    ]
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "route": "/research/archives",
        "readiness": readiness(),
        "source_types": types.get("source_types") or [],
        "repositories": repositories,
        "source_editor": {
            "fields": [
                "title", "source_type", "creators", "date", "repository",
                "archival_context", "original_language", "original_script",
                "digital_surrogate", "derivations", "rights", "notes",
            ],
            "original_language_first": True,
            "derived_representations_explicit": True,
        },
        "workspace_actions": [
            "search", "analyze-source", "compare-sources", "build-timeline",
            "preview-packet", "preview-ingestion-handoff",
        ],
        "guardrails": guardrails(),
    }


def search_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    plan = plan_archive_search(raw)
    execute = bool(raw.get("execute"))
    result: dict[str, Any] | None = None
    error: str | None = None
    if execute:
        try:
            result = search_institutional_repositories(raw)
        except Exception as exc:  # preserve the plan even when a live connector is unavailable
            error = str(exc)[:1000]
    basis = {
        "plan": plan.get("plan_fingerprint_sha256"),
        "execute": execute,
        "result": _dict(result).get("federation_result_fingerprint_sha256"),
        "error": error,
    }
    return {
        "schema": SEARCH_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "workspace_search_id": "historical-archives-workspace-search:" + _fp(basis)[:32],
        "workspace_search_fingerprint_sha256": _fp(basis),
        "query": plan.get("query"),
        "plan": plan,
        "execution_requested": execute,
        "execution_state": "completed" if result is not None else "degraded" if error else "planned",
        "result": result,
        "error": error,
        "automatic_import": False,
        "guardrails": guardrails(),
    }


def source_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    source_payload = _dict(raw.get("source") or raw)
    source = normalize_primary_source(source_payload)
    provenance = provenance_chain({"source": source})
    criticism = analyze_primary_source({"source": source})
    basis = {
        "source": source.get("object_fingerprint_sha256"),
        "provenance": provenance.get("provenance_chain_fingerprint_sha256"),
        "criticism": criticism.get("source_criticism_fingerprint_sha256"),
    }
    return {
        "schema": SOURCE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_workspace_id": "historical-source-workspace:" + _fp(basis)[:32],
        "source_workspace_fingerprint_sha256": _fp(basis),
        "source": source,
        "provenance": provenance,
        "source_criticism": criticism,
        "authenticity_certified": False,
        "truth_score": None,
        "guardrails": guardrails(),
    }


def compare_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    sources = [_dict(x) for x in _list(raw.get("sources"))[:MAX_WORKSPACE_SOURCES] if isinstance(x, dict)]
    if len(sources) < 2:
        raise ValueError("at least two sources are required for comparison")
    comparison = compare_primary_sources({"sources": sources})
    timeline = build_timeline({"sources": sources})
    basis = {
        "comparison": comparison.get("comparison_fingerprint_sha256"),
        "timeline": timeline.get("timeline_fingerprint_sha256"),
    }
    return {
        "schema": COMPARE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "comparison_workspace_id": "historical-comparison-workspace:" + _fp(basis)[:32],
        "comparison_workspace_fingerprint_sha256": _fp(basis),
        "comparison": comparison,
        "timeline": timeline,
        "disagreement_auto_resolved": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }


def timeline_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    sources = [_dict(x) for x in _list(raw.get("sources"))[:MAX_WORKSPACE_SOURCES] if isinstance(x, dict)]
    if not sources:
        raise ValueError("at least one source is required for a historical timeline")
    timeline = build_timeline({"sources": sources})
    return {
        "schema": TIMELINE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "timeline": timeline,
        "uncertain_dates_preserved": True,
        "false_precision_added": False,
        "guardrails": guardrails(),
    }


def packet_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    packet = build_primary_source_packet(raw)
    return {
        "schema": PACKET_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "packet": packet,
        "preview": True,
        "persisted": False,
        "externally_published": False,
        "guardrails": guardrails(),
    }


def handoff_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    handoff = ingestion_handoff(raw)
    return {
        "schema": HANDOFF_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff": handoff,
        "preview": True,
        "executed": False,
        "automatic_import": False,
        "guardrails": guardrails(),
    }
