from __future__ import annotations

from collections import Counter
from datetime import datetime, timezone
from hashlib import sha256
import json
from typing import Any, Iterable

REVIEW_SCHEMA = "sc-library-literature-review/1.0"
PROTOCOL_SCHEMA = "sc-library-review-protocol/1.0"
DECISION_SCHEMA = "sc-library-review-record-decision/1.0"
EXTRACTION_SCHEMA = "sc-library-review-extraction/1.0"
SNAPSHOT_SCHEMA = "sc-library-review-snapshot/1.0"
CHANGESET_SCHEMA = "sc-library-review-change-set/1.0"

STAGES = ("discovery", "title-abstract", "full-text", "included")
DECISIONS = {"include", "exclude", "uncertain", "duplicate"}


def _clean(value: Any) -> str:
    return " ".join(str(value or "").strip().split())


def _list(value: Any) -> list[Any]:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return [value]


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _record_id(record: dict[str, Any]) -> str:
    return _clean(record.get("record_id") or record.get("id"))


def _stable_hash(value: Any) -> str:
    payload = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
    return sha256(payload.encode("utf-8")).hexdigest()


def _string_list(value: Any) -> list[str]:
    out: list[str] = []
    seen: set[str] = set()
    for item in _list(value):
        text = _clean(item.get("label") if isinstance(item, dict) else item)
        key = text.casefold()
        if text and key not in seen:
            seen.add(key)
            out.append(text)
    return out


def normalize_protocol(protocol: dict[str, Any] | None) -> dict[str, Any]:
    p = _dict(protocol)
    searches: list[dict[str, Any]] = []
    for raw in _list(p.get("search_strategies") or p.get("searches")):
        if not isinstance(raw, dict):
            continue
        query = _clean(raw.get("query") or raw.get("search_string"))
        source = _clean(raw.get("source") or raw.get("database") or raw.get("connector"))
        executed_at = _clean(raw.get("executed_at") or raw.get("searched_at") or raw.get("date"))
        if query or source:
            searches.append({
                "source": source or None,
                "query": query or None,
                "executed_at": executed_at or None,
                "result_count": raw.get("result_count") if isinstance(raw.get("result_count"), int) else None,
                "notes": _clean(raw.get("notes")) or None,
            })
    normalized = {
        "schema": PROTOCOL_SCHEMA,
        "title": _clean(p.get("title")) or "Untitled literature review",
        "research_question": _clean(p.get("research_question") or p.get("question")) or None,
        "objective": _clean(p.get("objective")) or None,
        "framework": _clean(p.get("framework")) or None,
        "inclusion_criteria": _string_list(p.get("inclusion_criteria")),
        "exclusion_criteria": _string_list(p.get("exclusion_criteria")),
        "search_strategies": searches,
        "date_limits": _dict(p.get("date_limits")),
        "languages": _string_list(p.get("languages")),
        "source_types": _string_list(p.get("source_types")),
        "extraction_fields": _string_list(p.get("extraction_fields")),
        "synthesis_plan": _clean(p.get("synthesis_plan")) or None,
        "registered_identifier": _clean(p.get("registered_identifier") or p.get("registration")) or None,
        "protocol_version": _clean(p.get("protocol_version") or p.get("version")) or "1",
    }
    normalized["protocol_fingerprint_sha256"] = _stable_hash({k: v for k, v in normalized.items() if k not in {"schema", "protocol_fingerprint_sha256"}})
    normalized["protocol_id"] = "review-protocol:" + normalized["protocol_fingerprint_sha256"][:20]
    return normalized


def normalize_decisions(decisions: Iterable[dict[str, Any]]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for index, raw in enumerate(decisions):
        if not isinstance(raw, dict):
            continue
        rid = _clean(raw.get("record_id") or raw.get("id"))
        if not rid:
            continue
        stage = _clean(raw.get("stage")).lower().replace("_", "-") or "title-abstract"
        if stage not in STAGES:
            raise ValueError(f"unsupported review stage: {stage}")
        decision = _clean(raw.get("decision")).lower()
        if decision not in DECISIONS:
            raise ValueError(f"unsupported screening decision for {rid}: {decision or '<missing>'}")
        reason = _clean(raw.get("reason") or raw.get("exclusion_reason"))
        reviewer = _clean(raw.get("reviewer") or raw.get("reviewer_id"))
        decided_at = _clean(raw.get("decided_at") or raw.get("timestamp"))
        item = {
            "schema": DECISION_SCHEMA,
            "record_id": rid,
            "stage": stage,
            "decision": decision,
            "reason": reason or None,
            "reviewer": reviewer or None,
            "decided_at": decided_at or None,
            "decision_index": index,
            "human_decision": bool(raw.get("human_decision", True)),
        }
        item["decision_id"] = "review-decision:" + _stable_hash(item)[:20]
        out.append(item)
    return out


def _record_state(record_id: str, decisions: list[dict[str, Any]]) -> dict[str, Any]:
    relevant = [d for d in decisions if d.get("record_id") == record_id]
    state = "discovered"
    current: dict[str, Any] | None = None
    for decision in relevant:
        current = decision
        stage = decision["stage"]
        value = decision["decision"]
        if value == "duplicate":
            state = "excluded-duplicate"
        elif value == "exclude":
            state = "excluded-title-abstract" if stage == "title-abstract" else "excluded-full-text"
        elif value == "uncertain":
            state = "review-required"
        elif value == "include" and stage == "title-abstract":
            state = "awaiting-full-text"
        elif value == "include" and stage in {"full-text", "included"}:
            state = "included"
        elif value == "include":
            state = "screening-required"
    return {"record_id": record_id, "state": state, "latest_decision": current, "decision_count": len(relevant)}


def _normalize_extractions(extractions: Iterable[dict[str, Any]], known_ids: set[str]) -> list[dict[str, Any]]:
    out = []
    for raw in extractions:
        if not isinstance(raw, dict):
            continue
        rid = _clean(raw.get("record_id"))
        if not rid or rid not in known_ids:
            continue
        fields = _dict(raw.get("fields"))
        item = {
            "schema": EXTRACTION_SCHEMA,
            "record_id": rid,
            "fields": fields,
            "extractor": _clean(raw.get("extractor") or raw.get("reviewer")) or None,
            "extracted_at": _clean(raw.get("extracted_at") or raw.get("timestamp")) or None,
            "source_spans": [dict(x) for x in _list(raw.get("source_spans")) if isinstance(x, dict)],
            "methodology_profile": _dict(raw.get("methodology_profile")) or None,
            "notes": _clean(raw.get("notes")) or None,
        }
        item["extraction_id"] = "review-extraction:" + _stable_hash(item)[:20]
        out.append(item)
    return out


def _flow(states: list[dict[str, Any]]) -> dict[str, int]:
    c = Counter(s["state"] for s in states)
    return {
        "identified_records": len(states),
        "duplicate_decisions": c["excluded-duplicate"],
        "title_abstract_excluded": c["excluded-title-abstract"],
        "awaiting_full_text": c["awaiting-full-text"],
        "full_text_excluded": c["excluded-full-text"],
        "included": c["included"],
        "review_required": c["review-required"],
        "unscreened_or_incomplete": c["discovered"] + c["screening-required"],
    }


def build_literature_review(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("review payload must be an object")
    protocol = normalize_protocol(_dict(payload.get("protocol")))
    records = [dict(r) for r in _list(payload.get("records")) if isinstance(r, dict) and _record_id(r)]
    by_id = {_record_id(r): r for r in records}
    decisions = normalize_decisions([d for d in _list(payload.get("decisions")) if isinstance(d, dict)])
    states = [_record_state(rid, decisions) for rid in sorted(by_id)]
    state_by_id = {s["record_id"]: s for s in states}
    extractions = _normalize_extractions([e for e in _list(payload.get("extractions")) if isinstance(e, dict)], set(by_id))
    included_ids = sorted(s["record_id"] for s in states if s["state"] == "included")
    excluded_ids = sorted(s["record_id"] for s in states if s["state"].startswith("excluded-"))
    pending_ids = sorted(s["record_id"] for s in states if s["state"] not in {"included", "excluded-duplicate", "excluded-title-abstract", "excluded-full-text"})
    extracted_ids = {e["record_id"] for e in extractions}

    search_lineage = {
        "search_strategy_count": len(protocol["search_strategies"]),
        "sources": sorted({s["source"] for s in protocol["search_strategies"] if s.get("source")}),
        "queries": [s["query"] for s in protocol["search_strategies"] if s.get("query")],
        "searches_have_execution_dates": all(bool(s.get("executed_at")) for s in protocol["search_strategies"]) if protocol["search_strategies"] else False,
    }
    extraction_coverage = round(len(extracted_ids.intersection(included_ids)) / len(included_ids), 6) if included_ids else 0.0
    flow = _flow(states)

    snapshot_basis = {
        "protocol_fingerprint": protocol["protocol_fingerprint_sha256"],
        "record_ids": sorted(by_id),
        "decisions": decisions,
        "extractions": extractions,
    }
    review_fingerprint = _stable_hash(snapshot_basis)
    review_id = "literature-review:" + review_fingerprint[:20]

    nodes = [{"id": protocol["protocol_id"], "kind": "review-protocol", "label": protocol["title"], "default_evidence_path": False}]
    edges = []
    for rid in sorted(by_id):
        state = state_by_id[rid]["state"]
        node_id = f"review-record:{rid}"
        nodes.append({"id": node_id, "kind": "review-record", "record_id": rid, "state": state, "default_evidence_path": False})
        relation = "review-includes-record" if state == "included" else "review-excludes-record" if state.startswith("excluded-") else "review-pending-record"
        edges.append({
            "source": protocol["protocol_id"], "target": node_id, "relationship_basis": relation,
            "directed": True, "analytical": True, "review_required": state not in {"included", "excluded-duplicate", "excluded-title-abstract", "excluded-full-text"},
            "default_evidence_path": False,
        })

    warnings = []
    if not protocol.get("research_question"):
        warnings.append("Research question is not explicitly recorded in the review protocol.")
    if not protocol["inclusion_criteria"]:
        warnings.append("No explicit inclusion criteria are recorded.")
    if not protocol["exclusion_criteria"]:
        warnings.append("No explicit exclusion criteria are recorded.")
    if not protocol["search_strategies"]:
        warnings.append("No reproducible search strategy is recorded.")
    if any(not d.get("reviewer") for d in decisions):
        warnings.append("One or more screening decisions do not identify a reviewer.")

    return {
        "schema": REVIEW_SCHEMA,
        "review_id": review_id,
        "protocol": protocol,
        "records": [{"record_id": rid, "state": state_by_id[rid]["state"], "source": by_id[rid]} for rid in sorted(by_id)],
        "decisions": decisions,
        "extractions": extractions,
        "flow": flow,
        "sets": {"included_record_ids": included_ids, "excluded_record_ids": excluded_ids, "pending_record_ids": pending_ids},
        "search_lineage": search_lineage,
        "metrics": {
            "record_count": len(by_id),
            "decision_count": len(decisions),
            "included_count": len(included_ids),
            "excluded_count": len(excluded_ids),
            "pending_count": len(pending_ids),
            "extraction_count": len(extractions),
            "included_extraction_coverage": extraction_coverage,
        },
        "snapshot": {
            "schema": SNAPSHOT_SCHEMA,
            "review_id": review_id,
            "review_fingerprint_sha256": review_fingerprint,
            "protocol_fingerprint_sha256": protocol["protocol_fingerprint_sha256"],
            "record_ids": sorted(by_id),
            "included_record_ids": included_ids,
            "excluded_record_ids": excluded_ids,
            "pending_record_ids": pending_ids,
        },
        "graph_overlay": {"nodes": nodes, "edges": edges},
        "core_handoff": {
            "ready": bool(included_ids and protocol.get("research_question")),
            "automatic": False,
            "authority": "platform-core",
            "package_kind": "reproducible-literature-review",
            "included_record_ids": included_ids,
            "review_fingerprint_sha256": review_fingerprint,
        },
        "warnings": warnings,
        "guardrails": {
            "automatic_screening_decisions": False,
            "automatic_inclusion_exclusion": False,
            "duplicate_candidate_is_automatic_exclusion": False,
            "methodology_profile_is_quality_verdict": False,
            "included_record_is_true": False,
            "excluded_record_is_false": False,
            "review_flow_implies_prisma_compliance": False,
            "review_is_global_literature_completeness_claim": False,
            "automatic_meta_analysis": False,
            "automatic_consensus_inference": False,
            "platform_core_governance_changed": False,
            "human_review_required": True,
        },
    }


def compare_literature_reviews(left: dict[str, Any], right: dict[str, Any]) -> dict[str, Any]:
    l = left if left.get("schema") == REVIEW_SCHEMA else build_literature_review(left)
    r = right if right.get("schema") == REVIEW_SCHEMA else build_literature_review(right)
    li, ri = set(l.get("sets", {}).get("included_record_ids") or []), set(r.get("sets", {}).get("included_record_ids") or [])
    le, re = set(l.get("sets", {}).get("excluded_record_ids") or []), set(r.get("sets", {}).get("excluded_record_ids") or [])
    return {
        "schema": CHANGESET_SCHEMA,
        "from_review_id": l.get("review_id"),
        "to_review_id": r.get("review_id"),
        "protocol_changed": l.get("protocol", {}).get("protocol_fingerprint_sha256") != r.get("protocol", {}).get("protocol_fingerprint_sha256"),
        "added_included_record_ids": sorted(ri - li),
        "removed_included_record_ids": sorted(li - ri),
        "newly_excluded_record_ids": sorted(re - le),
        "no_longer_excluded_record_ids": sorted(le - re),
        "from_fingerprint": l.get("snapshot", {}).get("review_fingerprint_sha256"),
        "to_fingerprint": r.get("snapshot", {}).get("review_fingerprint_sha256"),
        "guardrails": {
            "change_set_implies_evidence_direction": False,
            "added_record_implies_support": False,
            "removed_record_implies_falsehood": False,
            "protocol_change_invalidates_prior_review_automatically": False,
        },
    }


def literature_review_request(payload: dict[str, Any]) -> dict[str, Any]:
    mode = _clean(payload.get("mode") or "build").lower()
    if mode == "compare":
        left = _dict(payload.get("left"))
        right = _dict(payload.get("right"))
        if not left or not right:
            raise ValueError("compare mode requires left and right review payloads")
        return compare_literature_reviews(left, right)
    return build_literature_review(payload)
