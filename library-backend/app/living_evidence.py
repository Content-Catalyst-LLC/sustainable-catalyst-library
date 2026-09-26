from __future__ import annotations

from collections import Counter, defaultdict
from datetime import datetime, timezone
from hashlib import sha256
import json
from typing import Any, Iterable

from .literature_review import REVIEW_SCHEMA, build_literature_review, compare_literature_reviews

LIVING_EVIDENCE_SCHEMA = "sc-library-living-evidence/1.0"
UPDATE_CANDIDATE_SCHEMA = "sc-library-living-evidence-update-candidate/1.0"
EVOLUTION_SCHEMA = "sc-library-research-evolution/1.0"
SURVEILLANCE_SCHEMA = "sc-library-living-review-surveillance/1.0"

STATUS_EVENTS = {"corrected", "retracted", "withdrawn", "superseded", "status-changed", "expression-of-concern"}


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


def _stable_hash(value: Any) -> str:
    payload = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)
    return sha256(payload.encode("utf-8")).hexdigest()


def _ensure_review(value: dict[str, Any]) -> dict[str, Any]:
    return value if value.get("schema") == REVIEW_SCHEMA else build_literature_review(value)


def _review_sources(review: dict[str, Any]) -> dict[str, dict[str, Any]]:
    out: dict[str, dict[str, Any]] = {}
    for item in _list(review.get("records")):
        if not isinstance(item, dict):
            continue
        rid = _clean(item.get("record_id"))
        source = _dict(item.get("source"))
        if rid:
            out[rid] = source
    return out


def _latest_decisions(review: dict[str, Any]) -> dict[str, dict[str, Any]]:
    out: dict[str, dict[str, Any]] = {}
    for item in _list(review.get("decisions")):
        if not isinstance(item, dict):
            continue
        rid = _clean(item.get("record_id"))
        if rid:
            out[rid] = item
    return out


def _extractions(review: dict[str, Any]) -> dict[str, list[dict[str, Any]]]:
    out: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for item in _list(review.get("extractions")):
        if not isinstance(item, dict):
            continue
        rid = _clean(item.get("record_id"))
        if rid:
            out[rid].append(item)
    return dict(out)


def _record_fingerprint(source: dict[str, Any]) -> str:
    metadata = _dict(source.get("metadata"))
    basis = {
        "record_id": _clean(source.get("record_id") or source.get("id")),
        "content_hash": _clean(source.get("content_hash")),
        "title": _clean(source.get("title")),
        "canonical_url": _clean(source.get("canonical_url") or source.get("url")),
        "publication_status": _clean(source.get("publication_status") or source.get("status")),
        "published_at": _clean(source.get("published_at") or source.get("publication_date")),
        "version": _clean(source.get("version") or metadata.get("version") or metadata.get("version_id")),
        "doi": _clean(source.get("doi") or _dict(source.get("identifiers")).get("doi") or metadata.get("doi")),
        "metadata_status": _clean(metadata.get("publication_status") or metadata.get("status")),
    }
    return _stable_hash(basis)


def _extract_fingerprint(items: list[dict[str, Any]]) -> str:
    normalized = []
    for item in items:
        normalized.append({
            "record_id": item.get("record_id"),
            "fields": item.get("fields") or {},
            "source_spans": item.get("source_spans") or [],
            "methodology_profile": item.get("methodology_profile"),
            "notes": item.get("notes"),
        })
    return _stable_hash(normalized)


def _explicit_status(source: dict[str, Any]) -> str | None:
    metadata = _dict(source.get("metadata"))
    status = _clean(source.get("publication_status") or source.get("status") or metadata.get("publication_status") or metadata.get("status"))
    return status.lower() if status else None


def _candidate(kind: str, record_id: str | None, basis: dict[str, Any], action: str, *, attention: str = "review") -> dict[str, Any]:
    seed = {"kind": kind, "record_id": record_id, "basis": basis, "action": action}
    return {
        "schema": UPDATE_CANDIDATE_SCHEMA,
        "candidate_id": "living-update:" + _stable_hash(seed)[:20],
        "kind": kind,
        "record_id": record_id,
        "attention_class": attention,
        "basis": basis,
        "suggested_review_action": action,
        "automatic_state_change": False,
        "human_review_required": True,
    }


def _year(value: Any) -> int | None:
    text = _clean(value)
    if len(text) >= 4 and text[:4].isdigit():
        year = int(text[:4])
        if 1000 <= year <= 3000:
            return year
    return None


def _research_evolution(baseline_sources: dict[str, dict[str, Any]], current_sources: dict[str, dict[str, Any]], events: list[dict[str, Any]]) -> dict[str, Any]:
    base_years = Counter()
    current_years = Counter()
    for source in baseline_sources.values():
        y = _year(source.get("published_at") or source.get("publication_date"))
        if y:
            base_years[y] += 1
    for source in current_sources.values():
        y = _year(source.get("published_at") or source.get("publication_date"))
        if y:
            current_years[y] += 1
    event_years = Counter()
    event_types = Counter()
    for event in events:
        if not isinstance(event, dict):
            continue
        etype = _clean(event.get("event_type") or event.get("type")).lower()
        if etype:
            event_types[etype] += 1
        y = _year(event.get("occurred_at") or event.get("date"))
        if y:
            event_years[y] += 1
    all_years = sorted(set(base_years) | set(current_years) | set(event_years))
    return {
        "schema": EVOLUTION_SCHEMA,
        "yearly_counts": [
            {
                "year": year,
                "baseline_record_count": base_years[year],
                "current_record_count": current_years[year],
                "explicit_change_event_count": event_years[year],
            }
            for year in all_years
        ],
        "explicit_event_type_counts": dict(sorted(event_types.items())),
        "interpretation": "Descriptive chronology of records and explicit change events within the supplied review snapshots; it does not infer causality, consensus, quality, or field-wide trend direction.",
    }


def _surveillance(review: dict[str, Any], payload: dict[str, Any]) -> dict[str, Any]:
    protocol = _dict(review.get("protocol"))
    searches = [dict(x) for x in _list(protocol.get("search_strategies")) if isinstance(x, dict)]
    cadence_days = payload.get("cadence_days")
    cadence = int(cadence_days) if isinstance(cadence_days, int) and cadence_days > 0 else None
    as_of = _clean(payload.get("as_of")) or None
    return {
        "schema": SURVEILLANCE_SCHEMA,
        "search_strategies": searches,
        "strategy_count": len(searches),
        "cadence_days": cadence,
        "as_of": as_of,
        "automatic_search_execution": False,
        "automatic_screening": False,
        "requires_scheduler_or_researcher_execution": True,
    }


def build_living_evidence(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("living evidence payload must be an object")
    baseline_raw = _dict(payload.get("baseline_review") or payload.get("baseline"))
    current_raw = _dict(payload.get("current_review") or payload.get("current"))
    if not baseline_raw or not current_raw:
        raise ValueError("baseline_review and current_review are required")

    baseline = _ensure_review(baseline_raw)
    current = _ensure_review(current_raw)
    change_set = compare_literature_reviews(baseline, current)
    baseline_sources = _review_sources(baseline)
    current_sources = _review_sources(current)
    baseline_decisions = _latest_decisions(baseline)
    current_decisions = _latest_decisions(current)
    baseline_extractions = _extractions(baseline)
    current_extractions = _extractions(current)
    events = [dict(x) for x in _list(payload.get("events") or payload.get("change_events")) if isinstance(x, dict)]

    baseline_ids, current_ids = set(baseline_sources), set(current_sources)
    candidates: list[dict[str, Any]] = []

    for rid in sorted(current_ids - baseline_ids):
        candidates.append(_candidate(
            "new-record", rid,
            {"current_record_fingerprint_sha256": _record_fingerprint(current_sources[rid])},
            "Screen the newly observed record against the existing review protocol.",
        ))

    for rid in sorted(baseline_ids - current_ids):
        candidates.append(_candidate(
            "record-not-present-in-current-snapshot", rid,
            {"baseline_record_fingerprint_sha256": _record_fingerprint(baseline_sources[rid])},
            "Check source availability/version lineage before changing the prior review record.",
        ))

    for rid in sorted(baseline_ids & current_ids):
        old_fp = _record_fingerprint(baseline_sources[rid])
        new_fp = _record_fingerprint(current_sources[rid])
        if old_fp != new_fp:
            candidates.append(_candidate(
                "source-record-changed", rid,
                {"baseline_record_fingerprint_sha256": old_fp, "current_record_fingerprint_sha256": new_fp},
                "Inspect the changed source/version and decide whether screening or extraction must be repeated.",
            ))
        old_status, new_status = _explicit_status(baseline_sources[rid]), _explicit_status(current_sources[rid])
        if old_status != new_status and (old_status or new_status):
            attention = "source-status" if new_status in STATUS_EVENTS else "review"
            candidates.append(_candidate(
                "explicit-source-status-change", rid,
                {"from_status": old_status, "to_status": new_status},
                "Review the explicit source-status change and assess its effect on the review without automatically altering conclusions.",
                attention=attention,
            ))
        old_dec = baseline_decisions.get(rid)
        new_dec = current_decisions.get(rid)
        if old_dec and new_dec:
            old_state = (old_dec.get("stage"), old_dec.get("decision"), old_dec.get("reason"))
            new_state = (new_dec.get("stage"), new_dec.get("decision"), new_dec.get("reason"))
            if old_state != new_state:
                candidates.append(_candidate(
                    "screening-decision-changed", rid,
                    {"from": {"stage": old_state[0], "decision": old_state[1], "reason": old_state[2]}, "to": {"stage": new_state[0], "decision": new_state[1], "reason": new_state[2]}},
                    "Audit the explicit screening-decision lineage and preserve the prior decision in the historical snapshot.",
                ))
        old_ext = _extract_fingerprint(baseline_extractions.get(rid, []))
        new_ext = _extract_fingerprint(current_extractions.get(rid, []))
        if old_ext != new_ext and (baseline_extractions.get(rid) or current_extractions.get(rid)):
            candidates.append(_candidate(
                "extraction-changed", rid,
                {"baseline_extraction_fingerprint_sha256": old_ext, "current_extraction_fingerprint_sha256": new_ext},
                "Review the changed extraction fields/source spans and retain both snapshot states for reproducibility.",
            ))

    for event in events:
        etype = _clean(event.get("event_type") or event.get("type")).lower()
        rid = _clean(event.get("record_id") or event.get("id")) or None
        if not etype:
            continue
        attention = "source-status" if etype in STATUS_EVENTS else "review"
        candidates.append(_candidate(
            "explicit-change-event", rid,
            {"event_type": etype, "occurred_at": _clean(event.get("occurred_at") or event.get("date")) or None, "source": _clean(event.get("source")) or None},
            "Inspect the explicit change event in source context before revising screening, extraction, or synthesis state.",
            attention=attention,
        ))

    if change_set.get("protocol_changed"):
        candidates.append(_candidate(
            "review-protocol-changed", None,
            {"baseline_protocol_fingerprint_sha256": baseline.get("protocol", {}).get("protocol_fingerprint_sha256"), "current_protocol_fingerprint_sha256": current.get("protocol", {}).get("protocol_fingerprint_sha256")},
            "Audit protocol-version differences separately from evidence changes and document whether re-screening is required.",
        ))

    # De-duplicate exact candidate identities while retaining deterministic order.
    dedup: dict[str, dict[str, Any]] = {c["candidate_id"]: c for c in candidates}
    candidates = [dedup[k] for k in sorted(dedup)]
    counts = Counter(c["kind"] for c in candidates)
    attention_counts = Counter(c["attention_class"] for c in candidates)

    overlay_nodes = []
    overlay_edges = []
    for c in candidates:
        overlay_nodes.append({
            "id": c["candidate_id"], "kind": "living-evidence-update-candidate", "label": c["kind"],
            "record_id": c.get("record_id"), "attention_class": c.get("attention_class"), "default_evidence_path": False,
        })
        if c.get("record_id"):
            overlay_edges.append({
                "source": c["candidate_id"], "target": f"review-record:{c['record_id']}",
                "relationship_basis": "review-update-candidate-for-record", "directed": True,
                "analytical": True, "review_required": True, "default_evidence_path": False,
                "truth_assertion": False, "causal_assertion": False,
            })

    result_basis = {
        "baseline_fingerprint": baseline.get("snapshot", {}).get("review_fingerprint_sha256"),
        "current_fingerprint": current.get("snapshot", {}).get("review_fingerprint_sha256"),
        "candidate_ids": [c["candidate_id"] for c in candidates],
        "events": events,
    }
    living_id = "living-evidence:" + _stable_hash(result_basis)[:20]

    return {
        "schema": LIVING_EVIDENCE_SCHEMA,
        "living_evidence_id": living_id,
        "baseline_review_id": baseline.get("review_id"),
        "current_review_id": current.get("review_id"),
        "baseline_snapshot": baseline.get("snapshot"),
        "current_snapshot": current.get("snapshot"),
        "review_change_set": change_set,
        "update_candidates": candidates,
        "metrics": {
            "candidate_count": len(candidates),
            "candidate_kind_counts": dict(sorted(counts.items())),
            "attention_class_counts": dict(sorted(attention_counts.items())),
            "new_record_count": len(current_ids - baseline_ids),
            "records_not_present_in_current_snapshot": len(baseline_ids - current_ids),
            "shared_record_count": len(baseline_ids & current_ids),
            "explicit_change_event_count": len(events),
        },
        "research_evolution": _research_evolution(baseline_sources, current_sources, events),
        "surveillance": _surveillance(current, payload),
        "graph_overlay": {"nodes": overlay_nodes, "edges": overlay_edges},
        "core_handoff": {
            "ready": bool(candidates),
            "automatic": False,
            "authority": "platform-core",
            "package_kind": "living-evidence-review-update-candidates",
            "candidate_ids": [c["candidate_id"] for c in candidates],
            "baseline_review_fingerprint_sha256": baseline.get("snapshot", {}).get("review_fingerprint_sha256"),
            "current_review_fingerprint_sha256": current.get("snapshot", {}).get("review_fingerprint_sha256"),
        },
        "guardrails": {
            "new_record_is_automatically_included": False,
            "changed_record_invalidates_prior_review": False,
            "newer_evidence_is_better_or_truer": False,
            "source_status_change_automatically_changes_conclusion": False,
            "screening_decision_changes_are_silent": False,
            "extraction_changes_overwrite_prior_snapshot": False,
            "candidate_update_is_evidence_direction": False,
            "chronology_implies_causality": False,
            "living_review_implies_continuous_automatic_search": False,
            "automatic_meta_analysis": False,
            "automatic_consensus_inference": False,
            "platform_core_governance_changed": False,
            "human_review_required": True,
        },
    }


def living_evidence_request(payload: dict[str, Any]) -> dict[str, Any]:
    return build_living_evidence(payload)
