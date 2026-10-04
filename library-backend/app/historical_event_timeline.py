from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .historical_archive_primary_source import normalize_date_assertion, normalize_primary_source
from .historical_archive_workspace import readiness as historical_archives_workspace_readiness
from .primary_source_comparison_criticism import readiness as source_criticism_readiness

LIBRARY_VERSION = "6.15.0"
BACKEND_VERSION = "3.15.0"
WEB_VERSION = "2.15.0"
SDK_VERSION = "1.15.0"

CONTRACT = "sc-library-research-timeline-historical-event-workspace/1.0"
READINESS_CONTRACT = "sc-library-research-timeline-historical-event-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-timeline-historical-event-bootstrap/1.0"
EVENT_CONTRACT = "sc-library-historical-event-object/1.0"
TIMELINE_CONTRACT = "sc-library-research-timeline/1.0"
COVERAGE_CONTRACT = "sc-library-historical-event-source-coverage/1.0"
CHRONOLOGY_COMPARISON_CONTRACT = "sc-library-competing-chronology-comparison/1.0"

MAX_EVENTS = 500
MAX_SOURCES = 100
MAX_RELATIONSHIPS = 2000
MAX_ASSERTIONS = 5000

EVENT_TYPES = (
    "occurrence", "decision", "communication", "publication", "observation",
    "policy", "legal", "organizational", "scientific", "economic",
    "infrastructure", "environmental", "other",
)
EVENT_RELATIONS = {
    "precedes", "follows", "overlaps", "contains", "part-of",
    "contemporaneous-with", "responds-to", "asserted-causal", "unknown",
}
SOURCE_ASSERTION_RELATIONS = {
    "attests", "dates", "mentions", "contextualizes", "disputes",
    "derives-from", "not-assessed",
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 6000) -> str:
    return " ".join(str(value or "").split())[:limit]


def guardrails() -> dict[str, bool]:
    return {
        "python_is_historical_event_timeline_workspace_authority": True,
        "historical_archive_v612_remains_primary_source_intelligence_authority": True,
        "historical_archives_v613_remains_archive_workspace_foundation": True,
        "source_criticism_v614_remains_comparison_criticism_authority": True,
        "uncertain_dates_are_preserved": True,
        "unknown_dates_are_preserved": True,
        "date_ranges_are_not_collapsed_to_midpoints": True,
        "display_sort_order_is_asserted_chronology": False,
        "temporal_overlap_implies_simultaneity": False,
        "event_adjacency_implies_causation": False,
        "causal_relationships_are_inferred_automatically": False,
        "event_identity_is_auto_merged": False,
        "source_count_is_confidence_probability": False,
        "source_assertion_count_is_truth_probability": False,
        "competing_chronologies_are_auto_reconciled": False,
        "chronology_comparison_selects_winner": False,
        "human_asserted_relationships_remain_explicit": True,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "historical-event-objects",
        "uncertainty-preserving-event-dates",
        "source-linked-event-assertions",
        "human-asserted-event-relationships",
        "timeline-construction-without-false-precision",
        "event-source-coverage-matrix",
        "competing-chronology-comparison",
        "explicit-event-key-cross-chronology-alignment",
        "standalone-web-route-/research/archives/timeline",
    ]
    basis = {"resources": resources, "event_types": EVENT_TYPES, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "workspace_id": "research-timeline-historical-event:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "state": "authoritative-composition",
        "authority": "python-backend",
        "route": "/research/archives/timeline",
        "resources": resources,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    archives = historical_archives_workspace_readiness()
    criticism = source_criticism_readiness()
    blocking: list[str] = []
    degraded: list[str] = []
    for label, dep in (("historical-archives-workspace", archives), ("source-criticism-workspace", criticism)):
        state = str(dep.get("state") or "unknown")
        if state in {"blocked", "failed", "unavailable"}:
            blocking.append(f"{label}:{state}")
        elif state != "ready":
            degraded.append(f"{label}:{state}")
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
        "dependencies": {
            "historical_archives_workspace": {
                "state": archives.get("state"),
                "library_version": archives.get("library_version"),
                "backend_version": archives.get("backend_version"),
            },
            "source_criticism_workspace": {
                "state": criticism.get("state"),
                "library_version": criticism.get("library_version"),
                "backend_version": criticism.get("backend_version"),
            },
        },
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "route": "/research/archives/timeline",
        "readiness": readiness(),
        "event_types": list(EVENT_TYPES),
        "event_relations": sorted(EVENT_RELATIONS),
        "source_assertion_relations": sorted(SOURCE_ASSERTION_RELATIONS),
        "date_qualifiers": ["exact", "circa", "before", "after", "between", "range", "decade", "century", "unknown"],
        "comparison_alignment_rule": "cross-chronology event alignment requires an explicit shared event_key; fuzzy identity merging is not performed",
        "guardrails": guardrails(),
    }


def _source_assertions(raw: Any) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for item in _list(raw)[:MAX_ASSERTIONS]:
        row = _dict(item)
        source_id = _clean(row.get("primary_source_id") or row.get("source_id"), 500)
        if not source_id:
            continue
        relation = _clean(row.get("relation"), 100).lower() or "not-assessed"
        if relation not in SOURCE_ASSERTION_RELATIONS:
            relation = "not-assessed"
        out.append({
            "primary_source_id": source_id,
            "relation": relation,
            "locator": _clean(row.get("locator"), 1000) or None,
            "basis": _clean(row.get("basis"), 6000) or None,
            "human_asserted": True,
        })
    return out


def normalize_event(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("event") if isinstance(payload, dict) and "event" in payload else payload)
    title = _clean(raw.get("title"), 1000)
    if not title:
        raise ValueError("title is required for a historical event")
    event_type = _clean(raw.get("event_type") or raw.get("type"), 100).lower() or "other"
    if event_type not in EVENT_TYPES:
        event_type = "other"
    date_value = raw.get("date")
    if date_value is None or date_value == "":
        date_value = {
            "start_year": raw.get("start_year"),
            "end_year": raw.get("end_year"),
            "qualifier": raw.get("date_qualifier") or "unknown",
        }
    date_assertion = normalize_date_assertion(date_value)
    actors = sorted({_clean(x, 500) for x in _list(raw.get("actors")) if _clean(x, 500)})
    places = sorted({_clean(x, 500) for x in _list(raw.get("places") or raw.get("place")) if _clean(x, 500)})
    if not places and _clean(raw.get("place"), 500):
        places = [_clean(raw.get("place"), 500)]
    tags = sorted({_clean(x, 100) for x in _list(raw.get("tags")) if _clean(x, 100)})
    source_assertions = _source_assertions(raw.get("source_assertions") or raw.get("sources"))
    if "event_key_explicit" in raw:
        explicit_event_key = (_clean(raw.get("event_key"), 500) or None) if bool(raw.get("event_key_explicit")) else None
    else:
        explicit_event_key = _clean(raw.get("event_key"), 500) or None
    basis = {
        "title": title,
        "event_type": event_type,
        "date": date_assertion,
        "actors": actors,
        "places": places,
        "source_assertions": source_assertions,
        "description": _clean(raw.get("description"), 12000),
    }
    event_id = _clean(raw.get("event_id"), 500) or "historical-event:" + _fp(basis)[:32]
    return {
        "schema": EVENT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "event_id": event_id,
        "event_key": explicit_event_key or event_id,
        "event_key_explicit": explicit_event_key is not None,
        "title": title,
        "event_type": event_type,
        "date": date_assertion,
        "description": _clean(raw.get("description"), 12000) or None,
        "actors": actors,
        "places": places,
        "source_assertions": source_assertions,
        "source_assertion_count": len(source_assertions),
        "tags": tags,
        "notes": _clean(raw.get("notes"), 12000) or None,
        "truth_status": None,
        "causal_status": None,
        "event_fingerprint_sha256": _fp(basis),
        "guardrails": guardrails(),
    }


def _normalize_relationships(raw: Any, event_ids: set[str]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for item in _list(raw)[:MAX_RELATIONSHIPS]:
        row = _dict(item)
        source_event = _clean(row.get("event_a") or row.get("source_event_id"), 500)
        target_event = _clean(row.get("event_b") or row.get("target_event_id"), 500)
        relation = _clean(row.get("relation"), 100).lower() or "unknown"
        if relation not in EVENT_RELATIONS:
            relation = "unknown"
        out.append({
            "source_event_id": source_event,
            "target_event_id": target_event,
            "source_known": source_event in event_ids,
            "target_known": target_event in event_ids,
            "relation": relation,
            "basis": _clean(row.get("basis"), 6000) or None,
            "human_asserted": True,
            "causal_inference": False,
        })
    return out


def _sort_key(event: dict[str, Any]) -> tuple[int, int, int, str]:
    date = _dict(event.get("date"))
    start = date.get("start_year")
    end = date.get("end_year")
    unknown = 1 if start is None else 0
    return (unknown, int(start or 0), int(end if end is not None else start or 0), str(event.get("title") or ""))


def _overlap(a: dict[str, Any], b: dict[str, Any]) -> bool:
    da = _dict(a.get("date")); db = _dict(b.get("date"))
    a0, a1 = da.get("start_year"), da.get("end_year")
    b0, b1 = db.get("start_year"), db.get("end_year")
    if a0 is None or b0 is None:
        return False
    a1 = a0 if a1 is None else a1
    b1 = b0 if b1 is None else b1
    return max(a0, b0) <= min(a1, b1)


def build_timeline(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    events = [normalize_event(_dict(x)) for x in _list(raw.get("events"))[:MAX_EVENTS] if isinstance(x, dict)]
    if not events:
        raise ValueError("at least one historical event is required")
    event_ids = {e["event_id"] for e in events}
    relationships = _normalize_relationships(raw.get("relationships"), event_ids)
    ordered = sorted(events, key=_sort_key)
    display_events = [{**event, "display_position": idx + 1} for idx, event in enumerate(ordered)]
    overlaps: list[dict[str, Any]] = []
    for i, event_a in enumerate(ordered):
        for event_b in ordered[i + 1:]:
            if _overlap(event_a, event_b):
                overlaps.append({
                    "event_a": event_a["event_id"],
                    "event_b": event_b["event_id"],
                    "overlap_candidate": True,
                    "simultaneity_asserted": False,
                })
    dated = [e for e in ordered if _dict(e.get("date")).get("start_year") is not None]
    unknown = [e["event_id"] for e in ordered if _dict(e.get("date")).get("start_year") is None]
    earliest = min((_dict(e["date"]).get("start_year") for e in dated), default=None)
    latest = max((_dict(e["date"]).get("end_year") if _dict(e["date"]).get("end_year") is not None else _dict(e["date"]).get("start_year") for e in dated), default=None)
    basis = {
        "events": [e["event_fingerprint_sha256"] for e in ordered],
        "relationships": relationships,
        "title": _clean(raw.get("title"), 1000),
    }
    return {
        "schema": TIMELINE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "timeline_id": "research-timeline:" + _fp(basis)[:32],
        "timeline_fingerprint_sha256": _fp(basis),
        "title": _clean(raw.get("title"), 1000) or "Research timeline",
        "research_question": _clean(raw.get("research_question"), 6000) or None,
        "event_count": len(display_events),
        "events": display_events,
        "relationships": relationships,
        "temporal_overlap_candidates": overlaps,
        "unknown_date_event_ids": unknown,
        "date_extent": {"earliest_start_year": earliest, "latest_end_year": latest},
        "display_order_basis": "start-year/end-year/title; unknown dates last",
        "display_order_is_asserted_chronology": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }


def source_coverage(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    events = [normalize_event(_dict(x)) for x in _list(raw.get("events"))[:MAX_EVENTS] if isinstance(x, dict)]
    sources = [normalize_primary_source(_dict(x)) for x in _list(raw.get("sources"))[:MAX_SOURCES] if isinstance(x, dict)]
    known_source_ids = {s["primary_source_id"] for s in sources}
    rows: list[dict[str, Any]] = []
    unknown_source_ids: set[str] = set()
    for event in events:
        by_source: dict[str, list[dict[str, Any]]] = {sid: [] for sid in known_source_ids}
        for assertion in event["source_assertions"]:
            sid = assertion["primary_source_id"]
            if sid in by_source:
                by_source[sid].append(assertion)
            else:
                unknown_source_ids.add(sid)
        rows.append({
            "event_id": event["event_id"],
            "event_key": event["event_key"],
            "title": event["title"],
            "date": event["date"],
            "coverage": [
                {"primary_source_id": sid, "assertions": by_source[sid], "assertion_count": len(by_source[sid])}
                for sid in sorted(by_source)
            ],
        })
    basis = {"events": [e["event_fingerprint_sha256"] for e in events], "sources": sorted(known_source_ids)}
    return {
        "schema": COVERAGE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "coverage_id": "historical-event-source-coverage:" + _fp(basis)[:32],
        "coverage_fingerprint_sha256": _fp(basis),
        "event_count": len(events),
        "source_count": len(sources),
        "sources": [{"primary_source_id": s["primary_source_id"], "title": s["title"]} for s in sources],
        "rows": rows,
        "unknown_source_ids": sorted(unknown_source_ids),
        "coverage_is_confidence_score": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }


def compare_chronologies(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    chronology_inputs = [_dict(x) for x in _list(raw.get("chronologies")) if isinstance(x, dict)]
    if len(chronology_inputs) < 2:
        raise ValueError("at least two chronologies are required for comparison")
    chronologies: list[dict[str, Any]] = []
    for idx, item in enumerate(chronology_inputs[:20]):
        timeline = build_timeline({
            "title": item.get("title") or f"Chronology {idx + 1}",
            "research_question": item.get("research_question"),
            "events": item.get("events") or [],
            "relationships": item.get("relationships") or [],
        })
        chronologies.append({
            "chronology_id": _clean(item.get("chronology_id"), 500) or timeline["timeline_id"],
            "title": timeline["title"],
            "timeline": timeline,
        })
    event_keys: dict[str, dict[str, dict[str, Any]]] = {}
    for chronology in chronologies:
        cid = chronology["chronology_id"]
        for event in chronology["timeline"]["events"]:
            if not event.get("event_key_explicit"):
                continue
            event_keys.setdefault(event["event_key"], {})[cid] = event
    rows: list[dict[str, Any]] = []
    chronology_ids = [c["chronology_id"] for c in chronologies]
    for event_key in sorted(event_keys):
        present = event_keys[event_key]
        dates = {cid: _dict(event.get("date")) for cid, event in present.items()}
        date_signatures = {_canon({"qualifier": d.get("qualifier"), "start_year": d.get("start_year"), "end_year": d.get("end_year"), "display": d.get("display")}) for d in dates.values()}
        rows.append({
            "event_key": event_key,
            "present_in": sorted(present),
            "missing_from": [cid for cid in chronology_ids if cid not in present],
            "dates": dates,
            "date_disagreement": len(date_signatures) > 1,
            "winner": None,
            "auto_reconciled": False,
        })
    basis = {"chronologies": [(c["chronology_id"], c["timeline"]["timeline_fingerprint_sha256"]) for c in chronologies], "rows": rows}
    return {
        "schema": CHRONOLOGY_COMPARISON_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "comparison_id": "competing-chronology-comparison:" + _fp(basis)[:32],
        "comparison_fingerprint_sha256": _fp(basis),
        "chronology_count": len(chronologies),
        "chronologies": chronologies,
        "aligned_event_rows": rows,
        "alignment_rule": "only explicit shared event_key values are aligned across chronologies",
        "winner": None,
        "automatic_reconciliation": False,
        "truth_determination": None,
        "guardrails": guardrails(),
    }
