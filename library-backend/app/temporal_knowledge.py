from __future__ import annotations

from collections import defaultdict
from datetime import datetime, timezone
from hashlib import sha256
import json
from typing import Any, Iterable

TEMPORAL_SCHEMA = "sc-library-temporal-knowledge-evolution/1.0"
SNAPSHOT_SCHEMA = "sc-library-temporal-knowledge-snapshot/1.0"
CHANGESET_SCHEMA = "sc-library-temporal-knowledge-change-set/1.0"

EVENT_TYPES = {
    "published", "indexed", "version-recorded", "corrected", "retracted", "withdrawn",
    "superseded", "dataset-updated", "supplement-added", "evidence-reviewed", "status-changed",
}
STATUS_PRECEDENCE = {"active": 0, "corrected": 1, "superseded": 2, "withdrawn": 3, "retracted": 4}


def _dt(value: Any) -> datetime | None:
    if isinstance(value, datetime):
        return value if value.tzinfo else value.replace(tzinfo=timezone.utc)
    text = str(value or "").strip()
    if not text:
        return None
    if len(text) == 4 and text.isdigit():
        text += "-01-01T00:00:00+00:00"
    elif len(text) == 10:
        text += "T00:00:00+00:00"
    text = text.replace("Z", "+00:00")
    try:
        out = datetime.fromisoformat(text)
    except ValueError:
        return None
    return out if out.tzinfo else out.replace(tzinfo=timezone.utc)


def _iso(value: Any) -> str | None:
    d = _dt(value)
    return d.astimezone(timezone.utc).isoformat().replace("+00:00", "Z") if d else None


def _date_key(value: Any) -> tuple[int, str]:
    iso = _iso(value)
    return (0, iso) if iso else (1, "")


def _as_list(value: Any) -> list[Any]:
    if value is None:
        return []
    return value if isinstance(value, list) else [value]


def _record_id(record: dict[str, Any]) -> str:
    return str(record.get("record_id") or record.get("id") or "").strip()


def _metadata(record: dict[str, Any]) -> dict[str, Any]:
    raw = record.get("metadata")
    return dict(raw) if isinstance(raw, dict) else {}


def _event_id(event: dict[str, Any]) -> str:
    payload = json.dumps({k: event.get(k) for k in sorted(event) if k != "event_id"}, sort_keys=True, default=str, separators=(",", ":"))
    return "te:" + sha256(payload.encode("utf-8")).hexdigest()[:20]


def _append_event(events: list[dict[str, Any]], *, record_id: str, event_type: str, occurred_at: Any,
                  source: str, status: str | None = None, label: str | None = None,
                  provenance: dict[str, Any] | None = None, details: dict[str, Any] | None = None) -> None:
    event_type = str(event_type or "").strip().lower()
    occurred = _iso(occurred_at)
    if not record_id or event_type not in EVENT_TYPES or not occurred:
        return
    event = {
        "record_id": record_id,
        "event_type": event_type,
        "occurred_at": occurred,
        "source": source,
        "status": status or None,
        "label": label or None,
        "details": details or {},
        "provenance": provenance or {},
    }
    event["event_id"] = _event_id(event)
    events.append(event)


def extract_record_events(record: dict[str, Any]) -> list[dict[str, Any]]:
    rid = _record_id(record)
    meta = _metadata(record)
    events: list[dict[str, Any]] = []
    _append_event(events, record_id=rid, event_type="published", occurred_at=record.get("published_at") or meta.get("published_at"),
                  source="record.published_at", status="active", label="Publication available",
                  provenance={"record_id": rid, "field": "published_at"})
    _append_event(events, record_id=rid, event_type="indexed", occurred_at=record.get("indexed_at") or meta.get("indexed_at"),
                  source="record.indexed_at", label="Indexed by Knowledge Library",
                  provenance={"record_id": rid, "field": "indexed_at"})

    status_fields = {
        "corrected": ("corrected_at", "correction_date", "correction_at"),
        "retracted": ("retracted_at", "retraction_date", "retraction_at"),
        "withdrawn": ("withdrawn_at", "withdrawal_date", "withdrawal_at"),
        "superseded": ("superseded_at", "superseded_date"),
    }
    for event_type, keys in status_fields.items():
        date = next((meta.get(k) for k in keys if meta.get(k)), None)
        _append_event(events, record_id=rid, event_type=event_type, occurred_at=date, source="metadata.status-event",
                      status=event_type, label=event_type.replace("-", " ").title(),
                      provenance={"record_id": rid, "metadata_fields": [k for k in keys if meta.get(k)]})

    for raw in _as_list(meta.get("temporal_events")) + _as_list(meta.get("research_events")):
        if not isinstance(raw, dict):
            continue
        typ = str(raw.get("event_type") or raw.get("type") or "").strip().lower()
        aliases = {"correction": "corrected", "retraction": "retracted", "withdrawal": "withdrawn", "update": "version-recorded"}
        typ = aliases.get(typ, typ)
        _append_event(events, record_id=rid, event_type=typ,
                      occurred_at=raw.get("occurred_at") or raw.get("date") or raw.get("timestamp"),
                      source="metadata.temporal_events", status=raw.get("status"), label=raw.get("label") or raw.get("title"),
                      details={k: v for k, v in raw.items() if k not in {"event_type", "type", "occurred_at", "date", "timestamp", "status", "label", "title"}},
                      provenance={"record_id": rid, "metadata_event": True})

    versions = _as_list(meta.get("versions")) + _as_list(meta.get("version_history"))
    for raw in versions:
        if not isinstance(raw, dict):
            continue
        _append_event(events, record_id=rid, event_type="version-recorded",
                      occurred_at=raw.get("published_at") or raw.get("date") or raw.get("created_at"),
                      source="metadata.version_history", status=raw.get("status"),
                      label=str(raw.get("label") or raw.get("version") or "Version recorded"),
                      details={"version": raw.get("version"), "content_hash": raw.get("content_hash"), "identifier": raw.get("identifier")},
                      provenance={"record_id": rid, "metadata_version": True})

    dataset_events = _as_list(meta.get("dataset_updates"))
    for raw in dataset_events:
        if not isinstance(raw, dict):
            continue
        _append_event(events, record_id=rid, event_type="dataset-updated", occurred_at=raw.get("date") or raw.get("occurred_at"),
                      source="metadata.dataset_updates", label=raw.get("label") or "Dataset updated", details=dict(raw),
                      provenance={"record_id": rid, "metadata_dataset_update": True})

    uniq = {e["event_id"]: e for e in events}
    return sorted(uniq.values(), key=lambda e: (_date_key(e.get("occurred_at")), e.get("event_type", ""), e.get("event_id", "")))


def _status_at(events: list[dict[str, Any]], cutoff: datetime, *, retrospective: bool = False) -> tuple[str, list[str]]:
    applicable = events if retrospective else [e for e in events if (_dt(e.get("occurred_at")) or cutoff) <= cutoff]
    statuses = [str(e.get("status") or e.get("event_type") or "active") for e in applicable]
    normalized = [s for s in statuses if s in STATUS_PRECEDENCE]
    status = max(normalized, key=lambda s: STATUS_PRECEDENCE[s]) if normalized else "active"
    flags = sorted({str(e.get("event_type")) for e in applicable if str(e.get("event_type")) in {"corrected", "retracted", "withdrawn", "superseded"}})
    return status, flags


def knowledge_snapshot(analysis: dict[str, Any], as_of: Any, *, lens: str = "historical-availability") -> dict[str, Any]:
    cutoff = _dt(as_of)
    if cutoff is None:
        raise ValueError("as_of must be an ISO date or datetime")
    lens = "retrospective-status" if lens == "retrospective-status" else "historical-availability"
    retrospective = lens == "retrospective-status"
    records = analysis.get("records") or []
    events = analysis.get("events") or []
    by_record: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for e in events:
        if isinstance(e, dict):
            by_record[str(e.get("record_id") or "")].append(e)
    visible = []
    for rec in records:
        if not isinstance(rec, dict):
            continue
        rid = str(rec.get("record_id") or "")
        available_at = _dt(rec.get("available_at"))
        if available_at is None or available_at > cutoff:
            continue
        status, flags = _status_at(by_record.get(rid, []), cutoff, retrospective=retrospective)
        visible.append({**rec, "status_as_of": status, "status_flags": flags})
    visible.sort(key=lambda x: (_date_key(x.get("available_at")), x.get("record_id", "")))
    return {
        "schema": SNAPSHOT_SCHEMA,
        "as_of": cutoff.astimezone(timezone.utc).isoformat().replace("+00:00", "Z"),
        "lens": lens,
        "record_count": len(visible),
        "records": visible,
        "guardrails": {
            "snapshot_is_historical_truth_claim": False,
            "absence_after_cutoff_means_false": False,
            "historical_availability_uses_only_events_known_by_cutoff": not retrospective,
            "retrospective_status_may_surface_later_corrections": retrospective,
        },
    }


def compare_snapshots(analysis: dict[str, Any], from_date: Any, to_date: Any, *, lens: str = "historical-availability") -> dict[str, Any]:
    a = knowledge_snapshot(analysis, from_date, lens=lens)
    b = knowledge_snapshot(analysis, to_date, lens=lens)
    amap = {x["record_id"]: x for x in a["records"]}
    bmap = {x["record_id"]: x for x in b["records"]}
    added = sorted(set(bmap) - set(amap))
    removed = sorted(set(amap) - set(bmap))
    status_changed = [
        {"record_id": rid, "from": amap[rid].get("status_as_of"), "to": bmap[rid].get("status_as_of")}
        for rid in sorted(set(amap) & set(bmap))
        if amap[rid].get("status_as_of") != bmap[rid].get("status_as_of")
    ]
    return {
        "schema": CHANGESET_SCHEMA,
        "from": a["as_of"], "to": b["as_of"], "lens": lens,
        "added_record_ids": added,
        "removed_record_ids": removed,
        "status_changes": status_changed,
        "metrics": {"added": len(added), "removed": len(removed), "status_changed": len(status_changed)},
        "interpretation": {"change_is_causal_explanation": False, "change_is_consensus_shift": False},
    }


def build_temporal_knowledge_evolution(records: Iterable[dict[str, Any]], *, nodes: Iterable[dict[str, Any]] | None = None,
                                       edges: Iterable[dict[str, Any]] | None = None) -> dict[str, Any]:
    record_items = [dict(r) for r in records if isinstance(r, dict) and _record_id(r)]
    event_items: list[dict[str, Any]] = []
    records_out: list[dict[str, Any]] = []
    for rec in record_items:
        rid = _record_id(rec)
        ev = extract_record_events(rec)
        event_items.extend(ev)
        available = _iso(rec.get("published_at")) or _iso(rec.get("indexed_at"))
        records_out.append({
            "record_id": rid,
            "title": str(rec.get("title") or rid),
            "available_at": available,
            "published_at": _iso(rec.get("published_at")),
            "indexed_at": _iso(rec.get("indexed_at")),
            "content_hash": str(rec.get("content_hash") or "") or None,
            "canonical_url": str(rec.get("canonical_url") or "") or None,
            "object_type": str(rec.get("object_type") or "") or None,
            "source_key": str(rec.get("source_key") or "") or None,
        })

    # Optional research-object events are included only when the upstream object itself
    # carries an explicit date. Their presence does not validate the object or its claim.
    object_events = []
    for node in nodes or []:
        if not isinstance(node, dict):
            continue
        kind = str(node.get("kind") or "")
        if kind not in {"claim", "finding", "hypothesis", "evidence", "dataset", "scientific-object"}:
            continue
        occurred = node.get("reviewed_at") or node.get("accepted_at") or node.get("created_at") or node.get("published_at")
        occurred_iso = _iso(occurred)
        if not occurred_iso:
            continue
        object_events.append({
            "object_id": str(node.get("id") or ""), "object_kind": kind,
            "occurred_at": occurred_iso, "event_type": "evidence-reviewed" if kind in {"claim", "finding", "evidence"} else "version-recorded",
            "label": str(node.get("label") or node.get("id") or ""),
            "provenance": node.get("provenance") or {},
        })

    event_items.sort(key=lambda e: (_date_key(e.get("occurred_at")), e.get("record_id", ""), e.get("event_type", "")))
    years: dict[int, dict[str, int]] = defaultdict(lambda: defaultdict(int))
    for e in event_items:
        d = _dt(e.get("occurred_at"))
        if d:
            years[d.year][str(e.get("event_type"))] += 1
    bins = [{"year": y, "event_count": sum(counts.values()), "event_types": dict(sorted(counts.items()))} for y, counts in sorted(years.items())]
    status_counts: dict[str, int] = defaultdict(int)
    now = datetime.now(timezone.utc)
    by_record: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for e in event_items:
        by_record[str(e.get("record_id") or "")].append(e)
    for rec in records_out:
        status, _ = _status_at(by_record.get(rec["record_id"], []), now, retrospective=False)
        status_counts[status] += 1

    output = {
        "schema": TEMPORAL_SCHEMA,
        "records": records_out,
        "events": event_items,
        "research_object_events": sorted(object_events, key=lambda x: (_date_key(x.get("occurred_at")), x.get("object_id", ""))),
        "timeline_bins": bins,
        "metrics": {
            "record_count": len(records_out), "event_count": len(event_items),
            "research_object_event_count": len(object_events), "year_count": len(bins),
            "current_status_counts": dict(sorted(status_counts.items())),
        },
        "supported_event_types": sorted(EVENT_TYPES),
        "lenses": [
            {"key": "historical-availability", "description": "Only records and status events available by the selected cutoff."},
            {"key": "retrospective-status", "description": "Records available by the cutoff annotated with later known corrections/retractions/withdrawals."},
        ],
        "guardrails": {
            "later_event_projected_backward_by_default": False,
            "absence_of_later_evidence_implies_earlier_consensus": False,
            "correction_or_retraction_invalidates_all_related_claims_automatically": False,
            "temporal_coincidence_implies_causality": False,
            "record_availability_equals_researcher_awareness": False,
            "platform_core_governance_changed": False,
        },
    }
    output["fingerprint_sha256"] = sha256(json.dumps({"records": records_out, "events": event_items}, sort_keys=True, default=str, separators=(",", ":")).encode("utf-8")).hexdigest()
    return output


def temporal_request(payload: dict[str, Any]) -> dict[str, Any]:
    records = payload.get("records") or []
    if not isinstance(records, list):
        raise ValueError("records must be a list")
    analysis = build_temporal_knowledge_evolution(records, nodes=payload.get("nodes") or [], edges=payload.get("edges") or [])
    if payload.get("as_of"):
        analysis["snapshot"] = knowledge_snapshot(analysis, payload.get("as_of"), lens=str(payload.get("lens") or "historical-availability"))
    if payload.get("from_date") and payload.get("to_date"):
        analysis["change_set"] = compare_snapshots(analysis, payload.get("from_date"), payload.get("to_date"), lens=str(payload.get("lens") or "historical-availability"))
    return analysis
