from __future__ import annotations

import csv
import io
import json
from datetime import datetime, timezone
from hashlib import sha256
from typing import Any, Iterable

CORPUS_SCHEMA = "sc-library-research-corpus/1.0"
MANIFEST_SCHEMA = "sc-library-corpus-manifest/1.0"
SELECTION_SCHEMA = "sc-library-corpus-selection/1.0"
DATASET_SCHEMA = "sc-library-dataset-export/1.0"
ROW_PROVENANCE_SCHEMA = "sc-library-dataset-row-provenance/1.0"
EXPORT_PACKAGE_SCHEMA = "sc-library-dataset-export-package/1.0"

DEFAULT_FIELDS = [
    "record_id", "title", "doi", "url", "published_at", "source_type",
    "authors", "topics", "abstract", "status",
]
ALLOWED_FORMATS = {"json", "jsonl", "csv", "bundle"}


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


def _record_id(record: dict[str, Any]) -> str:
    return _clean(record.get("record_id") or record.get("id") or record.get("source_id"))


def _string_list(value: Any) -> list[str]:
    out: list[str] = []
    seen: set[str] = set()
    for raw in _list(value):
        text = _clean(raw.get("label") if isinstance(raw, dict) else raw)
        key = text.casefold()
        if text and key not in seen:
            seen.add(key)
            out.append(text)
    return out


def _dateish(value: Any) -> str | None:
    text = _clean(value)
    return text or None


def _normalize_record(record: dict[str, Any]) -> dict[str, Any]:
    rid = _record_id(record)
    if not rid:
        raise ValueError("every corpus record requires record_id or id")
    authors = record.get("authors")
    if isinstance(authors, list):
        authors_out = [dict(a) if isinstance(a, dict) else _clean(a) for a in authors]
    elif authors is None:
        authors_out = []
    else:
        authors_out = [_clean(authors)]
    topics = _string_list(record.get("topics") or record.get("topic_labels"))
    normalized = {
        "record_id": rid,
        "title": _clean(record.get("title")) or None,
        "doi": _clean(record.get("doi")) or None,
        "url": _clean(record.get("url") or record.get("canonical_url")) or None,
        "published_at": _dateish(record.get("published_at") or record.get("published") or record.get("date")),
        "source_type": _clean(record.get("source_type") or record.get("object_type")) or None,
        "authors": authors_out,
        "topics": topics,
        "abstract": _clean(record.get("abstract") or record.get("description")) or None,
        "status": _clean(record.get("status") or record.get("publication_status")) or None,
        "source_key": _clean(record.get("source_key")) or None,
        "source_version": _clean(record.get("source_version") or record.get("version")) or None,
        "source_hash": _clean(record.get("source_hash") or record.get("content_hash") or record.get("sha256")) or None,
        "source_locator": _dict(record.get("source_locator")) or None,
        "original_language": _clean(record.get("original_language") or record.get("language_bcp47") or record.get("language")) or None,
        "script_iso15924": _clean(record.get("script_iso15924")) or None,
        "language_variant": _clean(record.get("language_variant")) or None,
        "orthography_variant": _clean(record.get("orthography_variant")) or None,
        "original_language_capture_id": _clean(record.get("original_language_capture_id")) or None,
        "original_language_representation_id": _clean(record.get("original_language_representation_id")) or None,
        "text_derivation_kind": _clean(record.get("text_derivation_kind") or record.get("derivation_kind")) or None,
        "text_derivation_run_id": _clean(record.get("text_derivation_run_id") or record.get("derivation_run_id")) or None,
        "text_derivation_source_asset_id": _clean(record.get("text_derivation_source_asset_id") or record.get("source_asset_id")) or None,
        "text_derivation_engine_fingerprint": _clean(record.get("text_derivation_engine_fingerprint") or record.get("engine_spec_fingerprint")) or None,
        "text_derivation_review_state": _clean(record.get("text_derivation_review_state") or record.get("review_state")) or None,
        "linguistic_corpus_id": _clean(record.get("linguistic_corpus_id")) or None,
        "linguistic_document_id": _clean(record.get("linguistic_document_id")) or None,
        "linguistic_tokenizer_fingerprint": _clean(record.get("linguistic_tokenizer_fingerprint") or record.get("tokenizer_spec_fingerprint")) or None,
        "linguistic_token_count": record.get("linguistic_token_count") if record.get("linguistic_token_count") is not None else None,
        "metadata": _dict(record.get("metadata")) or None,
    }
    normalized["record_fingerprint_sha256"] = _stable_hash({k: v for k, v in normalized.items() if k != "record_fingerprint_sha256"})
    return normalized


def _normalize_selection(selection: dict[str, Any] | None) -> dict[str, Any]:
    s = _dict(selection)
    include_ids = sorted(set(_string_list(s.get("include_record_ids") or s.get("include_ids"))))
    exclude_ids = sorted(set(_string_list(s.get("exclude_record_ids") or s.get("exclude_ids"))))
    criteria = _dict(s.get("criteria"))
    normalized = {
        "schema": SELECTION_SCHEMA,
        "mode": _clean(s.get("mode")) or ("explicit-record-ids" if include_ids else "all-provided-records"),
        "include_record_ids": include_ids,
        "exclude_record_ids": exclude_ids,
        "criteria": {
            "source_types": _string_list(criteria.get("source_types")),
            "topics": _string_list(criteria.get("topics")),
            "date_from": _dateish(criteria.get("date_from")),
            "date_to": _dateish(criteria.get("date_to")),
            "status": _string_list(criteria.get("status")),
        },
        "selection_note": _clean(s.get("selection_note") or s.get("notes")) or None,
        "human_reviewed": bool(s.get("human_reviewed", False)),
    }
    normalized["selection_fingerprint_sha256"] = _stable_hash({k: v for k, v in normalized.items() if k not in {"schema", "selection_fingerprint_sha256"}})
    return normalized


def _matches_criteria(record: dict[str, Any], criteria: dict[str, Any]) -> bool:
    source_types = {x.casefold() for x in criteria.get("source_types", [])}
    if source_types and _clean(record.get("source_type")).casefold() not in source_types:
        return False
    statuses = {x.casefold() for x in criteria.get("status", [])}
    if statuses and _clean(record.get("status")).casefold() not in statuses:
        return False
    topics = {x.casefold() for x in criteria.get("topics", [])}
    if topics:
        record_topics = {_clean(x).casefold() for x in _list(record.get("topics"))}
        if not topics.intersection(record_topics):
            return False
    published = _clean(record.get("published_at"))
    date_from = _clean(criteria.get("date_from"))
    date_to = _clean(criteria.get("date_to"))
    # ISO-like lexical comparison only; no inference from ambiguous dates.
    if (date_from or date_to) and not published:
        return False
    if date_from and published < date_from:
        return False
    if date_to and published > date_to:
        return False
    return True


def _select_records(records: list[dict[str, Any]], selection: dict[str, Any]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    include = set(selection["include_record_ids"])
    exclude = set(selection["exclude_record_ids"])
    decisions: list[dict[str, Any]] = []
    selected: list[dict[str, Any]] = []
    for record in records:
        rid = record["record_id"]
        explicit = rid in include if include else True
        criteria_match = _matches_criteria(record, selection["criteria"])
        excluded = rid in exclude
        chosen = explicit and criteria_match and not excluded
        reasons = []
        if include:
            reasons.append("explicit-include-id" if rid in include else "not-in-explicit-include-set")
        else:
            reasons.append("all-provided-records")
        if selection["criteria"] and any(selection["criteria"].values()):
            reasons.append("criteria-match" if criteria_match else "criteria-no-match")
        if excluded:
            reasons.append("explicit-exclude-id")
        decision = {
            "record_id": rid,
            "selected": chosen,
            "reasons": reasons,
            "selection_is_quality_judgment": False,
            "selection_is_truth_judgment": False,
        }
        decision["decision_fingerprint_sha256"] = _stable_hash(decision)
        decisions.append(decision)
        if chosen:
            selected.append(record)
    return selected, decisions


def _resolve_fields(fields: Any) -> list[str]:
    requested = _string_list(fields)
    if not requested:
        return list(DEFAULT_FIELDS)
    allowed = set(DEFAULT_FIELDS + [
        "source_key", "source_version", "source_hash", "source_locator", "metadata", "record_fingerprint_sha256",
        "original_language", "script_iso15924", "language_variant", "orthography_variant",
        "original_language_capture_id", "original_language_representation_id",
        "text_derivation_kind", "text_derivation_run_id", "text_derivation_source_asset_id",
        "text_derivation_engine_fingerprint", "text_derivation_review_state",
        "linguistic_corpus_id", "linguistic_document_id", "linguistic_tokenizer_fingerprint", "linguistic_token_count",
        "entity_resolution_case_ids", "resolved_entity_ids", "entity_resolution_decision_ids",
    ])
    unknown = [f for f in requested if f not in allowed]
    if unknown:
        raise ValueError("unsupported dataset fields: " + ", ".join(unknown))
    return requested


def _row_value(value: Any) -> Any:
    if isinstance(value, (dict, list)):
        return value
    return value


def _csv_value(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, (dict, list)):
        return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    return str(value)


def _build_rows(records: list[dict[str, Any]], fields: list[str], corpus_id: str) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    rows: list[dict[str, Any]] = []
    provenance: list[dict[str, Any]] = []
    for index, record in enumerate(records):
        row = {field: _row_value(record.get(field)) for field in fields}
        row_fingerprint = _stable_hash(row)
        rows.append(row)
        provenance.append({
            "schema": ROW_PROVENANCE_SCHEMA,
            "row_index": index,
            "corpus_id": corpus_id,
            "source_record_id": record["record_id"],
            "source_record_fingerprint_sha256": record["record_fingerprint_sha256"],
            "row_fingerprint_sha256": row_fingerprint,
            "source_key": record.get("source_key"),
            "source_version": record.get("source_version"),
            "source_hash": record.get("source_hash"),
            "source_locator": record.get("source_locator"),
        })
    return rows, provenance


def build_research_corpus(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("corpus payload must be an object")
    raw_records = [dict(r) for r in _list(payload.get("records")) if isinstance(r, dict)]
    records = [_normalize_record(r) for r in raw_records]
    grouped: dict[str, list[dict[str, Any]]] = {}
    for record in records:
        grouped.setdefault(record["record_id"], []).append(record)
    duplicate_ids = [rid for rid, variants in grouped.items() if len(variants) > 1]
    # Explicit record IDs define identity; if multiple payload variants share an ID,
    # choose the lexicographically smallest content fingerprint so input order cannot
    # change corpus identity. The duplicate ID is still surfaced for human review.
    by_id = {rid: sorted(variants, key=lambda x: x["record_fingerprint_sha256"])[0] for rid, variants in grouped.items()}
    records = [by_id[rid] for rid in sorted(by_id)]
    selection = _normalize_selection(_dict(payload.get("selection")))
    selected, selection_decisions = _select_records(records, selection)
    fields = _resolve_fields(payload.get("fields") or _dict(payload.get("export")).get("fields"))
    title = _clean(payload.get("title") or _dict(payload.get("manifest")).get("title")) or "Research corpus"
    description = _clean(payload.get("description") or _dict(payload.get("manifest")).get("description")) or None
    source_context = _dict(payload.get("source_context"))
    source_snapshot = {
        "record_count": len(records),
        "record_ids": [r["record_id"] for r in records],
        "record_fingerprints": {r["record_id"]: r["record_fingerprint_sha256"] for r in records},
        "source_context": source_context,
    }
    corpus_basis = {
        "title": title,
        "description": description,
        "selection_fingerprint": selection["selection_fingerprint_sha256"],
        "selected_record_ids": [r["record_id"] for r in selected],
        "selected_record_fingerprints": [r["record_fingerprint_sha256"] for r in selected],
        "fields": fields,
        "source_snapshot_fingerprint": _stable_hash(source_snapshot),
    }
    corpus_fingerprint = _stable_hash(corpus_basis)
    corpus_id = "research-corpus:" + corpus_fingerprint[:20]
    rows, row_provenance = _build_rows(selected, fields, corpus_id)
    dataset_fingerprint = _stable_hash({"fields": fields, "rows": rows, "provenance": row_provenance})
    manifest = {
        "schema": MANIFEST_SCHEMA,
        "corpus_id": corpus_id,
        "title": title,
        "description": description,
        "corpus_fingerprint_sha256": corpus_fingerprint,
        "dataset_fingerprint_sha256": dataset_fingerprint,
        "source_snapshot_fingerprint_sha256": _stable_hash(source_snapshot),
        "record_count_available": len(records),
        "record_count_selected": len(selected),
        "duplicate_record_ids_ignored": sorted(set(duplicate_ids)),
        "requested_include_ids_not_present": sorted(set(selection["include_record_ids"]) - set(by_id)),
        "requested_exclude_ids_not_present": sorted(set(selection["exclude_record_ids"]) - set(by_id)),
        "fields": fields,
        "selection": selection,
        "source_context": source_context,
        "schema_version": "1.0",
    }
    return {
        "schema": CORPUS_SCHEMA,
        "corpus_id": corpus_id,
        "manifest": manifest,
        "records": selected,
        "rows": rows,
        "row_provenance": row_provenance,
        "selection_decisions": selection_decisions,
        "metrics": {
            "provided_record_count": len(raw_records),
            "unique_record_count": len(records),
            "selected_record_count": len(selected),
            "excluded_record_count": len(records) - len(selected),
            "duplicate_record_id_count": len(duplicate_ids),
            "row_count": len(rows),
            "field_count": len(fields),
        },
        "guardrails": {
            "corpus_membership_implies_evidence_quality": False,
            "corpus_membership_implies_truth": False,
            "corpus_membership_implies_consensus": False,
            "corpus_exclusion_implies_falsehood": False,
            "dataset_row_is_governed_core_object": False,
            "automatic_platform_core_promotion": False,
            "human_review_required_for_research_interpretation": True,
        },
        "core_handoff": {
            "requested": bool(payload.get("core_handoff_requested", False)),
            "automatic": False,
            "durable_authority": "platform-core",
            "candidate_object_type": "research-corpus-package",
            "corpus_id": corpus_id,
            "corpus_fingerprint_sha256": corpus_fingerprint,
        },
    }


def _export_content(rows: list[dict[str, Any]], fields: list[str], format_name: str) -> tuple[str, str]:
    if format_name == "json":
        return json.dumps(rows, sort_keys=True, ensure_ascii=False, indent=2), "application/json"
    if format_name == "jsonl":
        return "\n".join(json.dumps(row, sort_keys=True, ensure_ascii=False, separators=(",", ":")) for row in rows) + ("\n" if rows else ""), "application/x-ndjson"
    if format_name == "csv":
        buf = io.StringIO(newline="")
        writer = csv.DictWriter(buf, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: _csv_value(row.get(field)) for field in fields})
        return buf.getvalue(), "text/csv"
    raise ValueError(f"unsupported export format: {format_name}")


def export_research_corpus(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("export payload must be an object")
    corpus = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else build_research_corpus(payload)
    if corpus.get("schema") != CORPUS_SCHEMA:
        raise ValueError("corpus payload does not use sc-library-research-corpus/1.0")
    export_request = _dict(payload.get("export"))
    format_name = _clean(export_request.get("format") or payload.get("format") or "bundle").lower()
    if format_name not in ALLOWED_FORMATS:
        raise ValueError("unsupported export format: " + format_name)
    manifest = _dict(corpus.get("manifest"))
    fields = list(manifest.get("fields") or DEFAULT_FIELDS)
    rows = [dict(r) for r in _list(corpus.get("rows")) if isinstance(r, dict)]
    provenance = [dict(p) for p in _list(corpus.get("row_provenance")) if isinstance(p, dict)]
    base_name = _clean(export_request.get("filename") or manifest.get("title") or "research-corpus").lower().replace(" ", "-")
    safe_name = "".join(ch if ch.isalnum() or ch in {"-", "_"} else "-" for ch in base_name).strip("-") or "research-corpus"
    generated_at = datetime.now(timezone.utc).isoformat()
    if format_name == "bundle":
        exports = {}
        for fmt in ("json", "jsonl", "csv"):
            content, media_type = _export_content(rows, fields, fmt)
            ext = "jsonl" if fmt == "jsonl" else fmt
            exports[fmt] = {
                "format": fmt,
                "filename": f"{safe_name}.{ext}",
                "media_type": media_type,
                "content": content,
                "sha256": sha256(content.encode("utf-8")).hexdigest(),
            }
        return {
            "schema": EXPORT_PACKAGE_SCHEMA,
            "corpus_id": corpus.get("corpus_id"),
            "manifest": manifest,
            "row_provenance": provenance,
            "exports": exports,
            "generated_at": generated_at,
            "guardrails": corpus.get("guardrails") or {},
        }
    content, media_type = _export_content(rows, fields, format_name)
    ext = "jsonl" if format_name == "jsonl" else format_name
    return {
        "schema": DATASET_SCHEMA,
        "corpus_id": corpus.get("corpus_id"),
        "manifest": manifest,
        "format": format_name,
        "filename": f"{safe_name}.{ext}",
        "media_type": media_type,
        "content": content,
        "content_sha256": sha256(content.encode("utf-8")).hexdigest(),
        "row_provenance": provenance,
        "generated_at": generated_at,
        "guardrails": corpus.get("guardrails") or {},
    }


def research_corpus_request(payload: dict[str, Any]) -> dict[str, Any]:
    mode = _clean(payload.get("mode") if isinstance(payload, dict) else "").lower()
    if mode == "export" or (isinstance(payload, dict) and payload.get("export_only")):
        return export_research_corpus(payload)
    return build_research_corpus(payload)
