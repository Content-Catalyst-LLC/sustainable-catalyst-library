from __future__ import annotations

import json
import platform
from datetime import datetime, timezone
from hashlib import sha256
from typing import Any

from . import __version__ as BACKEND_VERSION
from .unified_runtime_contract import execute_runtime, runtime_contract_status

REPRODUCIBILITY_STATUS_SCHEMA = "sc-library-cross-runtime-reproducibility/1.0"
EXECUTION_ENVIRONMENT_SCHEMA = "sc-library-execution-environment/1.0"
EXECUTION_LINEAGE_SCHEMA = "sc-library-execution-lineage/1.0"
REPRODUCIBILITY_RECORD_SCHEMA = "sc-library-reproducibility-record/1.0"
RUNTIME_VERIFICATION_SCHEMA = "sc-library-runtime-verification/1.0"

DETERMINISTIC_REPLAY_WORKLOADS = {"research-corpus-build", "dataset-export", "native-graph-query"}
CROSS_RUNTIME_COMPARABLE_WORKLOADS = {"native-graph-query"}


def _clean(value: Any) -> str:
    return " ".join(str(value or "").strip().split())


def _stable_hash(value: Any) -> str:
    payload = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
    return sha256(payload.encode("utf-8")).hexdigest()


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _semantic_projection(workload: str, result: Any) -> Any:
    """Remove execution-transport fields while preserving research output content."""
    if not isinstance(result, dict):
        return result
    projected = dict(result)
    for key in ["generated_at", "created_at", "started_at", "finished_at", "execution_id"]:
        projected.pop(key, None)
    if workload == "native-graph-query":
        projected.pop("runtime", None)
    return projected


def execution_environment_snapshot() -> dict[str, Any]:
    status = runtime_contract_status()
    runtimes = []
    for item in status.get("runtimes") or []:
        if not isinstance(item, dict):
            continue
        runtimes.append({
            "runtime_id": item.get("runtime_id"),
            "engine": item.get("engine"),
            "runtime_version": item.get("runtime_version"),
            "language_version": item.get("language_version"),
            "transport": item.get("transport"),
            "available": bool(item.get("available")),
            "capabilities": list(item.get("capabilities") or []),
        })
    basis = {
        "backend_version": BACKEND_VERSION,
        "python_implementation": platform.python_implementation(),
        "python_version": platform.python_version(),
        "system": platform.system(),
        "machine": platform.machine(),
        "runtime_contract_fingerprint_sha256": status.get("contract_fingerprint_sha256"),
        "runtimes": [
            {k: r.get(k) for k in ["runtime_id", "engine", "runtime_version", "language_version", "transport", "capabilities"]}
            for r in runtimes
        ],
    }
    return {
        "schema": EXECUTION_ENVIRONMENT_SCHEMA,
        **basis,
        "captured_at": _now(),
        "environment_fingerprint_sha256": _stable_hash(basis),
    }


def reproducibility_status() -> dict[str, Any]:
    env = execution_environment_snapshot()
    return {
        "schema": REPRODUCIBILITY_STATUS_SCHEMA,
        "backend_version": BACKEND_VERSION,
        "execution_environment": env,
        "contracts": {
            "execution_environment": EXECUTION_ENVIRONMENT_SCHEMA,
            "execution_lineage": EXECUTION_LINEAGE_SCHEMA,
            "reproducibility_record": REPRODUCIBILITY_RECORD_SCHEMA,
            "runtime_verification": RUNTIME_VERIFICATION_SCHEMA,
        },
        "capabilities": [
            "execution-environment-capture",
            "input-output-fingerprints",
            "runtime-routing-lineage",
            "parent-child-execution-lineage",
            "same-runtime-replay-verification",
            "cross-runtime-observed-output-comparison",
            "explicit-fallback-lineage",
        ],
        "deterministic_replay_workloads": sorted(DETERMINISTIC_REPLAY_WORKLOADS),
        "cross_runtime_comparable_workloads": sorted(CROSS_RUNTIME_COMPARABLE_WORKLOADS),
        "authority": {
            "research_semantics": "python-library-backend",
            "runtime_routing": "python-library-backend",
            "durable_governed_research_objects": "platform-core",
        },
        "guardrails": {
            "matching_output_fingerprints_prove_scientific_equivalence": False,
            "different_output_fingerprints_prove_one_runtime_is_wrong": False,
            "runtime_replay_proves_source_truth": False,
            "runtime_replay_proves_research_quality": False,
            "execution_lineage_automatically_promotes_core_objects": False,
            "platform_core_durable_authority_changed": False,
        },
    }


def create_reproducibility_record(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("reproducibility request must be an object")
    input_payload = payload.get("input") if isinstance(payload.get("input"), dict) else payload.get("payload")
    if not isinstance(input_payload, dict):
        input_payload = {}
    request = {
        "workload": payload.get("workload") or payload.get("operation"),
        "runtime": payload.get("runtime") or payload.get("requested_runtime") or "auto",
        "allow_fallback": payload.get("allow_fallback", True),
        "input": input_payload,
    }
    env = execution_environment_snapshot()
    execution = execute_runtime(request)
    workload = str(execution.get("workload") or "")
    semantic_result = _semantic_projection(workload, execution.get("result"))
    input_fingerprint = _stable_hash(input_payload)
    semantic_result_fingerprint = _stable_hash(semantic_result)
    reproducibility_basis = {
        "workload": workload,
        "input_fingerprint_sha256": input_fingerprint,
        "semantic_result_fingerprint_sha256": semantic_result_fingerprint,
        "selected_runtime": (execution.get("routing") or {}).get("selected_runtime"),
        "runtime_version": next((r.get("runtime_version") for r in env.get("runtimes", []) if r.get("engine") == (execution.get("routing") or {}).get("selected_runtime")), None),
        "runtime_contract_fingerprint_sha256": env.get("runtime_contract_fingerprint_sha256"),
        "environment_fingerprint_sha256": env.get("environment_fingerprint_sha256"),
    }
    reproducibility_fingerprint = _stable_hash(reproducibility_basis)
    parent_execution_id = _clean(payload.get("parent_execution_id")) or None
    parent_lineage_fingerprint = _clean(payload.get("parent_lineage_fingerprint_sha256")) or None
    root_execution_id = _clean(payload.get("root_execution_id")) or parent_execution_id or execution.get("execution_id")
    lineage_basis = {
        "parent_lineage_fingerprint_sha256": parent_lineage_fingerprint,
        "reproducibility_fingerprint_sha256": reproducibility_fingerprint,
    }
    lineage = {
        "schema": EXECUTION_LINEAGE_SCHEMA,
        "execution_id": execution.get("execution_id"),
        "parent_execution_id": parent_execution_id,
        "root_execution_id": root_execution_id,
        "parent_lineage_fingerprint_sha256": parent_lineage_fingerprint,
        "lineage_fingerprint_sha256": _stable_hash(lineage_basis),
        "fallback_used": bool((execution.get("routing") or {}).get("fallback_used")),
        "requested_runtime": (execution.get("routing") or {}).get("requested_runtime"),
        "selected_runtime": (execution.get("routing") or {}).get("selected_runtime"),
    }
    return {
        "schema": REPRODUCIBILITY_RECORD_SCHEMA,
        "record_id": "repro-record:" + reproducibility_fingerprint[:32],
        "created_at": _now(),
        "workload": workload,
        "state": execution.get("state"),
        "input": input_payload,
        "input_fingerprint_sha256": input_fingerprint,
        "request_fingerprint_sha256": execution.get("request_fingerprint_sha256"),
        "result_fingerprint_sha256": execution.get("result_fingerprint_sha256"),
        "semantic_result_fingerprint_sha256": semantic_result_fingerprint,
        "reproducibility_fingerprint_sha256": reproducibility_fingerprint,
        "execution_environment": env,
        "routing": execution.get("routing"),
        "lineage": lineage,
        "execution": execution,
        "guardrails": {
            "reproducibility_record_proves_truth": False,
            "reproducibility_record_proves_quality": False,
            "matching_fingerprint_proves_scientific_equivalence": False,
            "runtime_difference_is_interpreted_automatically": False,
            "automatic_platform_core_promotion": False,
        },
    }


def verify_reproducibility(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("verification request must be an object")
    record = payload.get("record")
    if not isinstance(record, dict) or record.get("schema") != REPRODUCIBILITY_RECORD_SCHEMA:
        raise ValueError("record must be a sc-library-reproducibility-record/1.0 object")
    workload = str(record.get("workload") or "")
    target_runtime = _clean(payload.get("runtime") or payload.get("target_runtime") or (record.get("routing") or {}).get("selected_runtime") or "auto").lower()
    baseline_runtime = str((record.get("routing") or {}).get("selected_runtime") or "")
    cross_runtime = bool(target_runtime not in {"", "auto", baseline_runtime})
    if workload not in DETERMINISTIC_REPLAY_WORKLOADS:
        return {
            "schema": RUNTIME_VERIFICATION_SCHEMA,
            "status": "not-applicable",
            "reason": "workload-is-not-deterministic-replay",
            "workload": workload,
            "baseline_runtime": baseline_runtime,
            "target_runtime": target_runtime,
            "cross_runtime": cross_runtime,
            "guardrails": {"cross_runtime_equivalence_proven": False, "scientific_validity_proven": False},
        }
    if cross_runtime and workload not in CROSS_RUNTIME_COMPARABLE_WORKLOADS:
        return {
            "schema": RUNTIME_VERIFICATION_SCHEMA,
            "status": "not-applicable",
            "reason": "workload-has-no-cross-runtime-implementation",
            "workload": workload,
            "baseline_runtime": baseline_runtime,
            "target_runtime": target_runtime,
            "cross_runtime": True,
            "guardrails": {"cross_runtime_equivalence_proven": False, "scientific_validity_proven": False},
        }
    rerun = create_reproducibility_record({
        "workload": workload,
        "runtime": target_runtime or baseline_runtime or "auto",
        "allow_fallback": False if cross_runtime else bool(payload.get("allow_fallback", False)),
        "input": record.get("input") if isinstance(record.get("input"), dict) else {},
        "parent_execution_id": (record.get("lineage") or {}).get("execution_id"),
        "parent_lineage_fingerprint_sha256": (record.get("lineage") or {}).get("lineage_fingerprint_sha256"),
        "root_execution_id": (record.get("lineage") or {}).get("root_execution_id"),
    })
    input_match = record.get("input_fingerprint_sha256") == rerun.get("input_fingerprint_sha256")
    semantic_match = record.get("semantic_result_fingerprint_sha256") == rerun.get("semantic_result_fingerprint_sha256")
    exact_result_match = record.get("result_fingerprint_sha256") == rerun.get("result_fingerprint_sha256")
    status = "verified" if input_match and semantic_match else "differs"
    interpretation = "observed-output-match" if semantic_match else "observed-output-difference"
    return {
        "schema": RUNTIME_VERIFICATION_SCHEMA,
        "status": status,
        "interpretation": interpretation,
        "verified_at": _now(),
        "workload": workload,
        "baseline_record_id": record.get("record_id"),
        "baseline_runtime": baseline_runtime,
        "target_runtime": (rerun.get("routing") or {}).get("selected_runtime"),
        "cross_runtime": cross_runtime,
        "input_fingerprint_match": input_match,
        "semantic_result_fingerprint_match": semantic_match,
        "exact_result_fingerprint_match": exact_result_match,
        "baseline_semantic_result_fingerprint_sha256": record.get("semantic_result_fingerprint_sha256"),
        "rerun_semantic_result_fingerprint_sha256": rerun.get("semantic_result_fingerprint_sha256"),
        "rerun_record": rerun,
        "guardrails": {
            "cross_runtime_equivalence_proven": False,
            "scientific_validity_proven": False,
            "matching_output_means_same_research_semantics": False,
            "difference_means_one_runtime_is_wrong": False,
            "human_interpretation_required_for_material_differences": True,
            "automatic_platform_core_promotion": False,
        },
    }
