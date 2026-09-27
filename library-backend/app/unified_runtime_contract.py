from __future__ import annotations

import json
import platform
from datetime import datetime, timezone
from hashlib import sha256
from typing import Any
from uuid import uuid4

from . import __version__ as BACKEND_VERSION
from .ingestion_job_fabric import GO_INGESTION_VERSION, ingestion_fabric_status, submit_ingestion_job
from .native_graph_query import query_native_graph
from .native_graph_runtime import NATIVE_GRAPH_VERSION, native_graph_runtime_status
from .research_corpus_builder import build_research_corpus, export_research_corpus

RUNTIME_CONTRACT = "sc-library-research-runtime-contract/1.0"
RUNTIME_DESCRIPTOR_SCHEMA = "sc-library-runtime-descriptor/1.0"
ROUTING_DECISION_SCHEMA = "sc-library-runtime-routing-decision/1.0"
EXECUTION_ENVELOPE_SCHEMA = "sc-library-runtime-execution-envelope/1.0"

RUNTIME_IDS = {
    "python": "python-library-backend",
    "go": "go-ingestion-runtime",
    "rust": "rust-graph-runtime",
}

WORKLOADS: dict[str, dict[str, Any]] = {
    "research-corpus-build": {
        "primary": "python",
        "compatible": ["python"],
        "execution_mode": "synchronous",
        "capability": "research-corpus-build",
    },
    "dataset-export": {
        "primary": "python",
        "compatible": ["python"],
        "execution_mode": "synchronous",
        "capability": "dataset-export",
    },
    "ingestion-job-submit": {
        "primary": "go",
        "compatible": ["go"],
        "execution_mode": "asynchronous-dispatch",
        "capability": "ingestion-job-submit",
    },
    "native-graph-query": {
        "primary": "rust",
        "compatible": ["rust", "python"],
        "fallback": "python",
        "execution_mode": "synchronous",
        "capability": "native-graph-query",
    },
}

WORKLOAD_ALIASES = {
    "corpus-build": "research-corpus-build",
    "research-corpus": "research-corpus-build",
    "corpus-export": "dataset-export",
    "export": "dataset-export",
    "ingestion": "ingestion-job-submit",
    "ingestion-submit": "ingestion-job-submit",
    "graph-query": "native-graph-query",
    "native-query": "native-graph-query",
}


def _clean(value: Any) -> str:
    return " ".join(str(value or "").strip().split())


def _stable_hash(value: Any) -> str:
    payload = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
    return sha256(payload.encode("utf-8")).hexdigest()


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _normalize_runtime(value: Any) -> str:
    raw = _clean(value).lower().replace("_", "-") or "auto"
    aliases = {
        "python-library-backend": "python",
        "go-ingestion-runtime": "go",
        "rust-graph-runtime": "rust",
    }
    raw = aliases.get(raw, raw)
    if raw not in {"auto", "python", "go", "rust"}:
        raise ValueError(f"unsupported runtime preference: {raw}")
    return raw


def _normalize_workload(value: Any) -> str:
    raw = _clean(value).lower().replace("_", "-")
    raw = WORKLOAD_ALIASES.get(raw, raw)
    if raw not in WORKLOADS:
        raise ValueError(f"unsupported research workload: {raw or '<empty>'}")
    return raw


def _python_descriptor() -> dict[str, Any]:
    return {
        "schema": RUNTIME_DESCRIPTOR_SCHEMA,
        "runtime_id": RUNTIME_IDS["python"],
        "engine": "python",
        "runtime_version": BACKEND_VERSION,
        "language_version": platform.python_version(),
        "available": True,
        "transport": "in-process",
        "execution_modes": ["synchronous", "orchestration"],
        "capabilities": [
            "research-corpus-build",
            "dataset-export",
            "research-semantics",
            "provenance-policy",
            "runtime-routing",
            "native-graph-query-fallback",
        ],
        "authority": {
            "research_semantics": True,
            "provenance_policy": True,
            "runtime_routing": True,
            "durable_governed_research_objects": False,
        },
    }


def _go_descriptor(status: dict[str, Any] | None = None) -> dict[str, Any]:
    status = dict(status or ingestion_fabric_status())
    reported = status.get("reported") if isinstance(status.get("reported"), dict) else {}
    return {
        "schema": RUNTIME_DESCRIPTOR_SCHEMA,
        "runtime_id": RUNTIME_IDS["go"],
        "engine": "go",
        "runtime_version": str(status.get("runtime_version") or GO_INGESTION_VERSION),
        "available": bool(status.get("available")),
        "transport": "internal-http-sidecar",
        "execution_modes": ["asynchronous-dispatch"],
        "capabilities": [
            "ingestion-job-submit",
            "priority-queue",
            "idempotency",
            "retry",
            "cancellation",
            "backpressure",
            "worker-health",
            "state-file-durability",
        ],
        "reported_durability": reported.get("durability"),
        "authority": {
            "research_semantics": False,
            "source_validity": False,
            "evidence_truth": False,
            "durable_governed_research_objects": False,
        },
    }


def _rust_descriptor(status: dict[str, Any] | None = None) -> dict[str, Any]:
    status = dict(status or native_graph_runtime_status())
    return {
        "schema": RUNTIME_DESCRIPTOR_SCHEMA,
        "runtime_id": RUNTIME_IDS["rust"],
        "engine": "rust",
        "runtime_version": str(status.get("reported_version") or status.get("runtime_version") or NATIVE_GRAPH_VERSION),
        "available": bool(status.get("available")),
        "transport": "local-native-binary",
        "execution_modes": ["synchronous-native-compute"],
        "capabilities": [
            "native-graph-query",
            "bounded-pathfinding",
            "filtered-neighborhood",
            "reachability",
            "connected-components",
            "induced-subgraph",
            "structural-stats",
        ],
        "authority": {
            "research_semantics": False,
            "evidence_truth": False,
            "causality": False,
            "consensus": False,
            "durable_governed_research_objects": False,
        },
    }


def runtime_contract_status() -> dict[str, Any]:
    descriptors = [_python_descriptor(), _go_descriptor(), _rust_descriptor()]
    descriptor_map = {d["engine"]: d for d in descriptors}
    contract_basis = {
        "schema": RUNTIME_CONTRACT,
        "backend_version": BACKEND_VERSION,
        "workloads": WORKLOADS,
        "runtimes": [
            {k: d.get(k) for k in ["runtime_id", "engine", "runtime_version", "transport", "execution_modes", "capabilities", "authority"]}
            for d in descriptors
        ],
    }
    return {
        "schema": RUNTIME_CONTRACT,
        "backend_version": BACKEND_VERSION,
        "runtimes": descriptors,
        "runtime_index": {k: v["runtime_id"] for k, v in descriptor_map.items()},
        "workloads": [
            {
                "workload": name,
                "primary_runtime": cfg["primary"],
                "compatible_runtimes": list(cfg["compatible"]),
                "fallback_runtime": cfg.get("fallback"),
                "execution_mode": cfg["execution_mode"],
            }
            for name, cfg in WORKLOADS.items()
        ],
        "contract_fingerprint_sha256": _stable_hash(contract_basis),
        "authority": {
            "public_api": "python-library-backend",
            "research_semantics": "python-library-backend",
            "runtime_routing": "python-library-backend",
            "ingestion_execution": "go-ingestion-runtime",
            "graph_structural_compute": "rust-graph-runtime",
            "durable_governed_research_objects": "platform-core",
        },
        "guardrails": {
            "runtime_selection_implies_evidence_quality": False,
            "runtime_success_implies_result_truth": False,
            "faster_runtime_implies_better_research": False,
            "cross_runtime_result_is_automatically_equivalent": False,
            "runtime_execution_automatically_promotes_core_objects": False,
            "python_research_semantics_authority_changed": False,
            "platform_core_durable_authority_changed": False,
        },
    }


def resolve_runtime(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("runtime routing request must be an object")
    workload = _normalize_workload(payload.get("workload") or payload.get("operation"))
    requested = _normalize_runtime(payload.get("runtime") or payload.get("requested_runtime") or "auto")
    allow_fallback = bool(payload.get("allow_fallback", True))
    cfg = WORKLOADS[workload]
    status = runtime_contract_status()
    descriptors = {d["engine"]: d for d in status["runtimes"]}

    if requested == "auto":
        selected = cfg["primary"]
    else:
        selected = requested
        if selected not in cfg["compatible"]:
            raise ValueError(f"runtime {selected} is not compatible with workload {workload}")

    fallback_used = False
    primary_unavailable = not bool(descriptors[selected].get("available"))
    if primary_unavailable and allow_fallback:
        fallback = cfg.get("fallback")
        if fallback and fallback in cfg["compatible"] and descriptors[fallback].get("available"):
            selected = fallback
            fallback_used = True

    execution_ready = bool(descriptors[selected].get("available"))
    basis = {
        "workload": workload,
        "requested_runtime": requested,
        "selected_runtime": selected,
        "allow_fallback": allow_fallback,
        "fallback_used": fallback_used,
        "contract_fingerprint_sha256": status["contract_fingerprint_sha256"],
    }
    return {
        "schema": ROUTING_DECISION_SCHEMA,
        **basis,
        "selected_runtime_id": RUNTIME_IDS[selected],
        "execution_ready": execution_ready,
        "execution_mode": cfg["execution_mode"],
        "compatible_runtimes": list(cfg["compatible"]),
        "fallback_runtime": cfg.get("fallback"),
        "routing_fingerprint_sha256": _stable_hash(basis),
        "reason": (
            "explicit-compatible-runtime"
            if requested != "auto" and not fallback_used
            else "primary-runtime-available"
            if not fallback_used
            else "primary-runtime-unavailable-explicit-fallback"
        ),
        "guardrails": {
            "routing_is_research_quality_judgment": False,
            "routing_changes_research_semantics": False,
            "fallback_is_silent": False,
            "fallback_requires_policy_permission": True,
            "platform_core_governance_changed": False,
        },
    }


def _dispatch(workload: str, selected: str, input_payload: dict[str, Any]) -> tuple[dict[str, Any], str]:
    if workload == "research-corpus-build":
        return build_research_corpus(input_payload), "completed"
    if workload == "dataset-export":
        return export_research_corpus(input_payload), "completed"
    if workload == "ingestion-job-submit":
        return submit_ingestion_job(input_payload), "accepted"
    if workload == "native-graph-query":
        corpus = input_payload.get("corpus") if isinstance(input_payload.get("corpus"), dict) else {}
        query = input_payload.get("query") if isinstance(input_payload.get("query"), dict) else {}
        query = dict(query)
        query["runtime"] = "rust" if selected == "rust" else "python"
        result = query_native_graph(corpus, query)
        used = str((result.get("runtime") or {}).get("used") or "")
        if selected == "rust" and used != "rust":
            raise RuntimeError("Rust was selected by the unified runtime contract but native execution did not complete in Rust")
        return result, "completed"
    raise ValueError(f"no dispatcher for workload {workload}")


def execute_runtime(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("runtime execution request must be an object")
    input_payload = payload.get("input") if isinstance(payload.get("input"), dict) else payload.get("payload")
    if not isinstance(input_payload, dict):
        input_payload = {}
    route_request = {
        "workload": payload.get("workload") or payload.get("operation"),
        "runtime": payload.get("runtime") or payload.get("requested_runtime") or "auto",
        "allow_fallback": payload.get("allow_fallback", True),
    }
    decision = resolve_runtime(route_request)
    if not decision["execution_ready"]:
        raise RuntimeError(
            f"selected runtime {decision['selected_runtime']} is unavailable for workload {decision['workload']}"
        )
    started_at = _now()
    request_basis = {
        "workload": decision["workload"],
        "requested_runtime": decision["requested_runtime"],
        "selected_runtime": decision["selected_runtime"],
        "allow_fallback": decision["allow_fallback"],
        "input": input_payload,
    }
    request_fingerprint = _stable_hash(request_basis)
    result, state = _dispatch(decision["workload"], decision["selected_runtime"], input_payload)
    return {
        "schema": EXECUTION_ENVELOPE_SCHEMA,
        "execution_id": "runtime-execution:" + uuid4().hex,
        "request_fingerprint_sha256": request_fingerprint,
        "started_at": started_at,
        "finished_at": _now(),
        "state": state,
        "workload": decision["workload"],
        "routing": decision,
        "result": result,
        "result_fingerprint_sha256": _stable_hash(result),
        "authority": {
            "research_semantics": "python-library-backend",
            "runtime_routing": "python-library-backend",
            "durable_governed_research_objects": "platform-core",
        },
        "guardrails": {
            "successful_execution_implies_truth": False,
            "successful_execution_implies_quality": False,
            "runtime_choice_changes_research_semantics": False,
            "cross_runtime_equivalence_proven": False,
            "automatic_platform_core_promotion": False,
        },
    }
