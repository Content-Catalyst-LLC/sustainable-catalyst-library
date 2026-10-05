from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.21.0"
BACKEND_VERSION = "3.21.0"
WEB_VERSION = "2.21.0"
SDK_VERSION = "1.21.0"

CONTRACT = "sc-library-research-question-investigation-workspace/1.0"
READINESS_CONTRACT = "sc-library-research-question-investigation-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-question-investigation-bootstrap/1.0"
INVESTIGATION_CONTRACT = "sc-library-structured-investigation-plan/1.0"
MATRIX_CONTRACT = "sc-library-investigation-question-evidence-task-matrix/1.0"
COVERAGE_CONTRACT = "sc-library-investigation-coverage-analysis/1.0"
EXECUTION_CONTRACT = "sc-library-investigation-execution-plan/1.0"
RISK_CONTRACT = "sc-library-investigation-risk-register/1.0"
EXPORT_CONTRACT = "sc-library-investigation-export/1.0"
HANDOFF_CONTRACT = "sc-library-investigation-handoff-preview/1.0"

PRIORITIES = {"critical","high","medium","low","unspecified"}
TASK_TYPES = {"search","retrieve","review","compare","extract","analyze","verify","corroborate","document","other"}
EVIDENCE_KINDS = {"primary-source","publication","dataset","citation","annotation","timeline","corpus","entity-resolution","expert-source","institutional-source","other"}
RISK_TYPES = {"scope","bias","source-coverage","language","temporal","geographic","method","conflict-of-interest","data-quality","interpretation","other"}
MAX_SUBQUESTIONS = 500
MAX_HYPOTHESES = 500
MAX_EVIDENCE_NEEDS = 2000
MAX_TASKS = 5000
MAX_DECISION_POINTS = 500
MAX_STOP_CONDITIONS = 500
MAX_RISKS = 1000


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 10000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _priority(value: Any) -> str:
    p = _clean(value, 50).lower() or "unspecified"
    return p if p in PRIORITIES else "unspecified"


def guardrails() -> dict[str, bool]:
    return {
        "workspace_is_planning_and_control_layer": True,
        "workspace_is_autonomous_research_agent": False,
        "workspace_automatically_executes_tasks": False,
        "workspace_automatically_submits_searches": False,
        "workspace_automatically_persists_projects": False,
        "workspace_automatically_queues_research": False,
        "hypothesis_is_claim": False,
        "hypothesis_is_evidence": False,
        "hypothesis_is_truth": False,
        "task_completion_is_evidence": False,
        "search_result_is_evidence_automatically": False,
        "retrieval_result_is_evidence_automatically": False,
        "priority_is_truth_or_importance_score": False,
        "stop_condition_determines_truth": False,
        "missing_evidence_is_inferred": False,
        "source_quality_is_user_trust": False,
        "automatic_source_trust": False,
        "automatic_hypothesis_acceptance": False,
        "automatic_hypothesis_rejection": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "human_review_required_for_handoffs": True,
        "server_side_investigation_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "research-question",
        "subquestions",
        "hypotheses",
        "evidence-needs",
        "source-strategy",
        "investigation-tasks",
        "dependency-aware-execution-plan",
        "decision-points",
        "stop-conditions",
        "risk-and-bias-register",
        "coverage-analysis",
        "signed-existing-authority-handoffs",
        "reproducible-investigation-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "research-investigation:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/investigation",
        "api_base": "/api/library/v1/research-investigation",
        "resources": resources,
        "limits": {
            "subquestions": MAX_SUBQUESTIONS,
            "hypotheses": MAX_HYPOTHESES,
            "evidence_needs": MAX_EVIDENCE_NEEDS,
            "tasks": MAX_TASKS,
            "decision_points": MAX_DECISION_POINTS,
            "stop_conditions": MAX_STOP_CONDITIONS,
            "risks": MAX_RISKS,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "ready": True,
        "blocking": [],
        "degraded": [],
        "authority": "python-backend-composition",
        "server_side_investigation_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "handoff_authorities": {
            "research_project": "/api/library/v1/admin/research-state/projects",
            "saved_search": "/api/library/v1/admin/research-state/saved-searches",
            "research_queue": "/api/library/v1/admin/research-state/queue",
            "retrieval_plan": "/api/library/v1/admin/retrieval/plan",
        },
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/investigation",
        "readiness": readiness(),
        "priorities": sorted(PRIORITIES),
        "task_types": sorted(TASK_TYPES),
        "evidence_kinds": sorted(EVIDENCE_KINDS),
        "risk_types": sorted(RISK_TYPES),
        "operations": [
            "build","matrix","coverage","execution-plan","risk-register",
            "export","handoff-preview",
        ],
        "browser_storage_key": "sc-library-research-investigation-v1",
        "handoff_types": ["research-project","saved-search","research-queue","retrieval-plan"],
        "guardrails": guardrails(),
    }


def _normalize_subquestion(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    text = _clean(row.get("text") or row.get("question"), 12000)
    if not text:
        raise ValueError(f"subquestion-{index}-text-required")
    return {
        "subquestion_id": _clean(row.get("subquestion_id") or row.get("id"), 1000) or f"subquestion:{index}",
        "text": text,
        "priority": _priority(row.get("priority")),
        "status": _clean(row.get("status"), 100).lower() or "open",
        "parent_id": _clean(row.get("parent_id"), 1000) or None,
        "rationale": _clean(row.get("rationale"), 12000) or None,
    }


def _normalize_hypothesis(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    text = _clean(row.get("text") or row.get("hypothesis"), 12000)
    if not text:
        raise ValueError(f"hypothesis-{index}-text-required")
    return {
        "hypothesis_id": _clean(row.get("hypothesis_id") or row.get("id"), 1000) or f"hypothesis:{index}",
        "text": text,
        "subquestion_ids": [_clean(x, 1000) for x in _list(row.get("subquestion_ids")) if _clean(x, 1000)],
        "status": _clean(row.get("status"), 100).lower() or "untested",
        "priority": _priority(row.get("priority")),
        "notes": _clean(row.get("notes"), 12000) or None,
        "accepted": False,
        "rejected": False,
    }


def _normalize_evidence_need(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    description = _clean(row.get("description") or row.get("need"), 12000)
    if not description:
        raise ValueError(f"evidence-need-{index}-description-required")
    kind = _clean(row.get("kind"), 100).lower() or "other"
    if kind not in EVIDENCE_KINDS:
        kind = "other"
    return {
        "evidence_need_id": _clean(row.get("evidence_need_id") or row.get("id"), 1000) or f"evidence-need:{index}",
        "description": description,
        "kind": kind,
        "subquestion_ids": [_clean(x, 1000) for x in _list(row.get("subquestion_ids")) if _clean(x, 1000)],
        "hypothesis_ids": [_clean(x, 1000) for x in _list(row.get("hypothesis_ids")) if _clean(x, 1000)],
        "priority": _priority(row.get("priority")),
        "minimum_source_count": max(0, int(row.get("minimum_source_count") or 0)),
        "required_languages": [_clean(x, 100) for x in _list(row.get("required_languages")) if _clean(x, 100)],
        "required_date_range": _dict(row.get("required_date_range")),
        "required_geography": _clean(row.get("required_geography"), 2000) or None,
        "status": _clean(row.get("status"), 100).lower() or "open",
    }


def _normalize_task(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    description = _clean(row.get("description") or row.get("task"), 12000)
    if not description:
        raise ValueError(f"task-{index}-description-required")
    task_type = _clean(row.get("task_type") or row.get("type"), 100).lower() or "other"
    if task_type not in TASK_TYPES:
        task_type = "other"
    return {
        "task_id": _clean(row.get("task_id") or row.get("id"), 1000) or f"task:{index}",
        "description": description,
        "task_type": task_type,
        "priority": _priority(row.get("priority")),
        "status": _clean(row.get("status"), 100).lower() or "planned",
        "subquestion_ids": [_clean(x, 1000) for x in _list(row.get("subquestion_ids")) if _clean(x, 1000)],
        "hypothesis_ids": [_clean(x, 1000) for x in _list(row.get("hypothesis_ids")) if _clean(x, 1000)],
        "evidence_need_ids": [_clean(x, 1000) for x in _list(row.get("evidence_need_ids")) if _clean(x, 1000)],
        "depends_on": [_clean(x, 1000) for x in _list(row.get("depends_on")) if _clean(x, 1000)],
        "search_query": _clean(row.get("search_query"), 6000) or None,
        "source_filters": _dict(row.get("source_filters")),
        "assigned_source_ids": [_clean(x, 1000) for x in _list(row.get("assigned_source_ids")) if _clean(x, 1000)],
        "completion_is_evidence": False,
    }


def _normalize_decision_point(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    question = _clean(row.get("question") or row.get("text"), 12000)
    if not question:
        raise ValueError(f"decision-point-{index}-question-required")
    return {
        "decision_point_id": _clean(row.get("decision_point_id") or row.get("id"), 1000) or f"decision-point:{index}",
        "question": question,
        "criteria": [_clean(x, 6000) for x in _list(row.get("criteria")) if _clean(x, 6000)],
        "depends_on_task_ids": [_clean(x, 1000) for x in _list(row.get("depends_on_task_ids")) if _clean(x, 1000)],
        "status": _clean(row.get("status"), 100).lower() or "pending",
        "automatic_decision": False,
    }


def _normalize_stop_condition(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    description = _clean(row.get("description") or row.get("condition"), 12000)
    if not description:
        raise ValueError(f"stop-condition-{index}-description-required")
    return {
        "stop_condition_id": _clean(row.get("stop_condition_id") or row.get("id"), 1000) or f"stop-condition:{index}",
        "description": description,
        "condition_type": _clean(row.get("condition_type"), 100).lower() or "manual-review",
        "triggered": bool(row.get("triggered", False)),
        "determines_truth": False,
    }


def _normalize_risk(raw: Any, index: int) -> dict[str, Any]:
    row = _dict(raw)
    description = _clean(row.get("description") or row.get("risk"), 12000)
    if not description:
        raise ValueError(f"risk-{index}-description-required")
    risk_type = _clean(row.get("risk_type") or row.get("type"), 100).lower() or "other"
    if risk_type not in RISK_TYPES:
        risk_type = "other"
    return {
        "risk_id": _clean(row.get("risk_id") or row.get("id"), 1000) or f"risk:{index}",
        "risk_type": risk_type,
        "description": description,
        "severity": _clean(row.get("severity"), 100).lower() or "unspecified",
        "mitigation": _clean(row.get("mitigation"), 12000) or None,
        "status": _clean(row.get("status"), 100).lower() or "open",
    }


def normalize_investigation(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    question = _clean(payload.get("research_question") or payload.get("question"), 16000)
    if not question:
        raise ValueError("research-question-required")

    sub_raw = _list(payload.get("subquestions"))
    hyp_raw = _list(payload.get("hypotheses"))
    evidence_raw = _list(payload.get("evidence_needs"))
    tasks_raw = _list(payload.get("tasks"))
    decisions_raw = _list(payload.get("decision_points"))
    stops_raw = _list(payload.get("stop_conditions"))
    risks_raw = _list(payload.get("risks"))

    limits = [
        ("subquestion", len(sub_raw), MAX_SUBQUESTIONS),
        ("hypothesis", len(hyp_raw), MAX_HYPOTHESES),
        ("evidence-need", len(evidence_raw), MAX_EVIDENCE_NEEDS),
        ("task", len(tasks_raw), MAX_TASKS),
        ("decision-point", len(decisions_raw), MAX_DECISION_POINTS),
        ("stop-condition", len(stops_raw), MAX_STOP_CONDITIONS),
        ("risk", len(risks_raw), MAX_RISKS),
    ]
    for label, count, maximum in limits:
        if count > maximum:
            raise ValueError(f"{label}-limit-exceeded:{maximum}")

    subquestions = [_normalize_subquestion(x, i) for i, x in enumerate(sub_raw, start=1)]
    hypotheses = [_normalize_hypothesis(x, i) for i, x in enumerate(hyp_raw, start=1)]
    evidence_needs = [_normalize_evidence_need(x, i) for i, x in enumerate(evidence_raw, start=1)]
    tasks = [_normalize_task(x, i) for i, x in enumerate(tasks_raw, start=1)]
    decisions = [_normalize_decision_point(x, i) for i, x in enumerate(decisions_raw, start=1)]
    stops = [_normalize_stop_condition(x, i) for i, x in enumerate(stops_raw, start=1)]
    risks = [_normalize_risk(x, i) for i, x in enumerate(risks_raw, start=1)]

    basis = {
        "research_question": question,
        "scope": _clean(payload.get("scope"), 16000),
        "subquestions": subquestions,
        "hypotheses": hypotheses,
        "evidence_needs": evidence_needs,
        "tasks": tasks,
        "decision_points": decisions,
        "stop_conditions": stops,
        "risks": risks,
        "source_strategy": _dict(payload.get("source_strategy")),
    }
    return {
        "schema": "sc-library-investigation-input/1.0",
        "investigation_input_id": "investigation-input:" + _fp(basis)[:32],
        "investigation_input_fingerprint_sha256": _fp(basis),
        "title": _clean(payload.get("title"), 2000) or "Research investigation",
        "research_question": question,
        "scope": basis["scope"] or None,
        "subquestions": subquestions,
        "hypotheses": hypotheses,
        "evidence_needs": evidence_needs,
        "tasks": tasks,
        "decision_points": decisions,
        "stop_conditions": stops,
        "risks": risks,
        "source_strategy": _dict(payload.get("source_strategy")),
        "metadata": _dict(payload.get("metadata")),
    }


def investigation_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    rows = []
    for sq in inv["subquestions"]:
        sid = sq["subquestion_id"]
        hypotheses = [h for h in inv["hypotheses"] if sid in h["subquestion_ids"]]
        needs = [n for n in inv["evidence_needs"] if sid in n["subquestion_ids"]]
        tasks = [t for t in inv["tasks"] if sid in t["subquestion_ids"]]
        rows.append({
            "subquestion_id": sid,
            "subquestion": sq["text"],
            "priority": sq["priority"],
            "hypothesis_ids": [h["hypothesis_id"] for h in hypotheses],
            "evidence_need_ids": [n["evidence_need_id"] for n in needs],
            "task_ids": [t["task_id"] for t in tasks],
            "coverage_status": "linked" if needs or tasks else "unplanned",
            "answered": False,
        })
    basis = {"input": inv["investigation_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": MATRIX_CONTRACT,
        "matrix_id": "investigation-matrix:" + _fp(basis)[:32],
        "matrix_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "guardrails": guardrails(),
    }


def coverage_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    matrix = investigation_matrix(payload)
    task_ids = {t["task_id"] for t in inv["tasks"]}
    evidence_ids = {e["evidence_need_id"] for e in inv["evidence_needs"]}
    gaps = []
    for row in matrix["rows"]:
        if not row["evidence_need_ids"]:
            gaps.append({"kind": "subquestion-without-evidence-need", "subquestion_id": row["subquestion_id"]})
        if not row["task_ids"]:
            gaps.append({"kind": "subquestion-without-task", "subquestion_id": row["subquestion_id"]})
    for h in inv["hypotheses"]:
        if not h["subquestion_ids"]:
            gaps.append({"kind": "hypothesis-without-subquestion", "hypothesis_id": h["hypothesis_id"]})
    for e in inv["evidence_needs"]:
        linked_tasks = [t["task_id"] for t in inv["tasks"] if e["evidence_need_id"] in t["evidence_need_ids"]]
        if not linked_tasks:
            gaps.append({"kind": "evidence-need-without-task", "evidence_need_id": e["evidence_need_id"]})
    dangling_dependencies = []
    for task in inv["tasks"]:
        for dep in task["depends_on"]:
            if dep not in task_ids:
                dangling_dependencies.append({"task_id": task["task_id"], "missing_dependency": dep})
    dangling_evidence_refs = []
    for task in inv["tasks"]:
        for eid in task["evidence_need_ids"]:
            if eid not in evidence_ids:
                dangling_evidence_refs.append({"task_id": task["task_id"], "missing_evidence_need": eid})
    basis = {
        "input": inv["investigation_input_fingerprint_sha256"],
        "gaps": gaps,
        "dangling_dependencies": dangling_dependencies,
        "dangling_evidence_refs": dangling_evidence_refs,
    }
    return {
        "schema": COVERAGE_CONTRACT,
        "coverage_id": "investigation-coverage:" + _fp(basis)[:32],
        "coverage_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "gaps": gaps,
        "gap_count": len(gaps),
        "dangling_dependencies": dangling_dependencies,
        "dangling_evidence_refs": dangling_evidence_refs,
        "complete": not gaps and not dangling_dependencies and not dangling_evidence_refs,
        "completion_implies_answer": False,
        "guardrails": guardrails(),
    }


def execution_plan(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    tasks = {t["task_id"]: t for t in inv["tasks"]}
    remaining = set(tasks)
    completed: set[str] = set()
    waves = []
    while remaining:
        ready_ids = sorted(
            tid for tid in remaining
            if all(dep in completed for dep in tasks[tid]["depends_on"] if dep in tasks)
        )
        if not ready_ids:
            cycle = sorted(remaining)
            waves.append({
                "wave": len(waves) + 1,
                "task_ids": cycle,
                "blocked": True,
                "reason": "cyclic-or-unsatisfied-dependency",
            })
            break
        waves.append({
            "wave": len(waves) + 1,
            "task_ids": ready_ids,
            "blocked": False,
            "reason": None,
        })
        completed.update(ready_ids)
        remaining.difference_update(ready_ids)
    basis = {"input": inv["investigation_input_fingerprint_sha256"], "waves": waves}
    return {
        "schema": EXECUTION_CONTRACT,
        "execution_plan_id": "investigation-execution:" + _fp(basis)[:32],
        "execution_plan_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "waves": waves,
        "task_count": len(tasks),
        "automatically_executed": False,
        "automatic_search_submission": False,
        "guardrails": guardrails(),
    }


def risk_register(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    rows = list(inv["risks"])
    source_strategy = inv["source_strategy"]
    if not source_strategy:
        rows.append({
            "risk_id": "derived-risk:missing-source-strategy",
            "risk_type": "source-coverage",
            "description": "No explicit source strategy is recorded.",
            "severity": "unspecified",
            "mitigation": "Define source families, languages, jurisdictions, date ranges, and inclusion/exclusion criteria.",
            "status": "open",
            "derived_from_structure": True,
        })
    if not inv["stop_conditions"]:
        rows.append({
            "risk_id": "derived-risk:missing-stop-condition",
            "risk_type": "scope",
            "description": "No explicit investigation stop condition is recorded.",
            "severity": "unspecified",
            "mitigation": "Define when investigation should pause for review or close as sufficiently scoped.",
            "status": "open",
            "derived_from_structure": True,
        })
    basis = {"input": inv["investigation_input_fingerprint_sha256"], "rows": rows}
    return {
        "schema": RISK_CONTRACT,
        "risk_register_id": "investigation-risks:" + _fp(basis)[:32],
        "risk_register_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "risk_count": len(rows),
        "automatic_source_trust": False,
        "guardrails": guardrails(),
    }


def build_investigation(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    matrix = investigation_matrix(payload)
    coverage = coverage_analysis(payload)
    execution = execution_plan(payload)
    risks = risk_register(payload)
    basis = {
        "input": inv["investigation_input_fingerprint_sha256"],
        "matrix": matrix["matrix_fingerprint_sha256"],
        "coverage": coverage["coverage_fingerprint_sha256"],
        "execution": execution["execution_plan_fingerprint_sha256"],
        "risks": risks["risk_register_fingerprint_sha256"],
    }
    return {
        "schema": INVESTIGATION_CONTRACT,
        "investigation_id": "research-investigation:" + _fp(basis)[:32],
        "investigation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": inv["title"],
        "research_question": inv["research_question"],
        "scope": inv["scope"],
        "subquestions": inv["subquestions"],
        "hypotheses": inv["hypotheses"],
        "evidence_needs": inv["evidence_needs"],
        "tasks": inv["tasks"],
        "decision_points": inv["decision_points"],
        "stop_conditions": inv["stop_conditions"],
        "source_strategy": inv["source_strategy"],
        "investigation_matrix": matrix,
        "coverage_analysis": coverage,
        "execution_plan": execution,
        "risk_register": risks,
        "conclusion_generated": False,
        "hypotheses_adjudicated": False,
        "tasks_executed": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }


def handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    inv = normalize_investigation(payload)
    handoff_type = _clean(payload.get("handoff_type"), 100).lower() or "research-project"
    endpoints = {
        "research-project": "/api/library/v1/admin/research-state/projects",
        "saved-search": "/api/library/v1/admin/research-state/saved-searches",
        "research-queue": "/api/library/v1/admin/research-state/queue",
        "retrieval-plan": "/api/library/v1/admin/retrieval/plan",
    }
    if handoff_type not in endpoints:
        raise ValueError("unsupported-handoff-type")
    endpoint = endpoints[handoff_type]

    if handoff_type == "research-project":
        handoff_payload = {
            "title": inv["title"],
            "description": inv["research_question"],
            "metadata": {
                "scope": inv["scope"],
                "investigation_input_id": inv["investigation_input_id"],
                "subquestion_count": len(inv["subquestions"]),
                "hypothesis_count": len(inv["hypotheses"]),
                "task_count": len(inv["tasks"]),
            },
        }
    elif handoff_type == "saved-search":
        selected = _dict(payload.get("handoff_payload"))
        handoff_payload = {
            "name": _clean(selected.get("name"), 1000) or inv["title"],
            "query": _clean(selected.get("query"), 6000) or inv["research_question"],
            "filters": _dict(selected.get("filters")),
            "metadata": {"investigation_input_id": inv["investigation_input_id"]},
        }
    elif handoff_type == "research-queue":
        selected = _dict(payload.get("handoff_payload"))
        handoff_payload = {
            "title": _clean(selected.get("title"), 1000) or inv["title"],
            "description": _clean(selected.get("description"), 6000) or inv["research_question"],
            "priority": _priority(selected.get("priority")),
            "metadata": {"investigation_input_id": inv["investigation_input_id"]},
        }
    else:
        selected = _dict(payload.get("handoff_payload"))
        handoff_payload = {
            "q": _clean(selected.get("q") or selected.get("query"), 6000) or inv["research_question"],
            "mode": _clean(selected.get("mode"), 100) or "hybrid",
            "filters": _dict(selected.get("filters")),
            "metadata": {"investigation_input_id": inv["investigation_input_id"]},
        }

    basis = {"handoff_type": handoff_type, "endpoint": endpoint, "payload": handoff_payload}
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "investigation-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff_type": handoff_type,
        "endpoint": endpoint,
        "payload": handoff_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_persistence": False,
        "signed_request_required": True,
        "guardrails": guardrails(),
    }


def export_investigation(payload: dict[str, Any]) -> dict[str, Any]:
    investigation = build_investigation(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "investigation": investigation,
        "automatic_import": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {
        "investigation_id": investigation["investigation_id"],
        "fingerprint": investigation["investigation_fingerprint_sha256"],
    }
    body["export_id"] = "investigation-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-research-investigation.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
