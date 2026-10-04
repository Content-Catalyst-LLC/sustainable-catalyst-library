from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.16.0"
BACKEND_VERSION = "3.16.0"
WEB_VERSION = "2.16.0"
SDK_VERSION = "1.16.0"

CONTRACT = "sc-library-research-annotation-scholarly-notes-workspace/1.0"
READINESS_CONTRACT = "sc-library-research-annotation-scholarly-notes-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-annotation-scholarly-notes-bootstrap/1.0"
ANNOTATION_CONTRACT = "sc-library-research-annotation/1.0"
NOTEBOOK_CONTRACT = "sc-library-scholarly-notebook/1.0"
RELATION_CONTRACT = "sc-library-annotation-relation/1.0"
EXPORT_CONTRACT = "sc-library-scholarly-notes-export/1.0"

MAX_ANNOTATIONS = 2000
MAX_RELATIONS = 5000
MAX_TAGS = 100
MAX_BODY = 50000

NOTE_TYPES = (
    "observation",
    "summary",
    "question",
    "critique",
    "interpretation",
    "method",
    "citation-note",
    "evidence-note",
    "contradiction",
    "context",
    "follow-up",
    "marginalia",
)
TARGET_KINDS = (
    "library-record",
    "primary-source",
    "historical-event",
    "timeline",
    "research-project",
    "dataset",
    "publication",
    "external-source",
    "other",
)
RELATION_TYPES = {
    "supports",
    "contradicts",
    "contextualizes",
    "responds-to",
    "extends",
    "cites",
    "derived-from",
    "duplicates",
    "related",
    "unknown",
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


def _bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value
    return str(value or "").strip().lower() in {"1", "true", "yes", "on"}


def guardrails() -> dict[str, bool]:
    return {
        "python_is_annotation_workspace_authority": True,
        "annotation_is_source_content": False,
        "annotation_is_evidence_truth": False,
        "annotation_is_authoritative_source_metadata": False,
        "selected_quote_is_verified_against_target_automatically": False,
        "locator_presence_proves_quote_accuracy": False,
        "note_type_implies_evidence_strength": False,
        "tag_implies_claim_status": False,
        "annotation_relationships_are_inferred_automatically": False,
        "annotation_relationships_remain_human_asserted": True,
        "scholarly_notes_are_auto_promoted_to_claims": False,
        "scholarly_notes_are_auto_promoted_to_evidence": False,
        "scholarly_notes_are_auto_promoted_to_citations": False,
        "browser_local_storage_is_authoritative": False,
        "server_side_note_persistence_enabled": False,
        "export_package_is_automatic_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "source-and-research-object-anchored-annotations",
        "page-section-character-timecode-and-quote-locators",
        "scholarly-note-types-and-tags",
        "human-asserted-note-relationships",
        "target-grouped-scholarly-notebooks",
        "unresolved-question-preservation",
        "browser-local-continuity-with-explicit-non-authority",
        "reproducible-json-note-export",
        "standalone-web-route-/research/notes",
    ]
    basis = {
        "resources": resources,
        "note_types": NOTE_TYPES,
        "target_kinds": TARGET_KINDS,
        "relation_types": sorted(RELATION_TYPES),
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "workspace_id": "research-annotation-scholarly-notes:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "state": "authoritative-composition",
        "authority": "python-backend",
        "route": "/research/notes",
        "resources": resources,
        "persistence": {
            "backend": False,
            "browser_local_continuity": True,
            "browser_local_is_authoritative": False,
            "exportable": True,
        },
        "database_migration_required": False,
        "wordpress_required": False,
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
        "database_migration_required": False,
        "wordpress_required": False,
        "server_side_note_persistence": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "route": "/research/notes",
        "readiness": readiness(),
        "note_types": list(NOTE_TYPES),
        "target_kinds": list(TARGET_KINDS),
        "relation_types": sorted(RELATION_TYPES),
        "locator_fields": [
            "page", "page_label", "section", "paragraph", "figure", "table",
            "start_char", "end_char", "start_time", "end_time", "fragment", "selector",
        ],
        "browser_storage_key": "sc-library-research-notes-v1",
        "guardrails": guardrails(),
    }


def _normalize_target(raw: Any) -> dict[str, Any]:
    target = _dict(raw)
    kind = _clean(target.get("kind") or target.get("target_kind"), 100).lower() or "other"
    if kind not in TARGET_KINDS:
        kind = "other"
    target_id = _clean(target.get("id") or target.get("target_id"), 1000)
    uri = _clean(target.get("uri") or target.get("url"), 4000)
    if not target_id and not uri:
        raise ValueError("annotation target requires target.id or target.uri")
    return {
        "kind": kind,
        "id": target_id or None,
        "title": _clean(target.get("title"), 2000) or None,
        "uri": uri or None,
        "version": _clean(target.get("version"), 500) or None,
        "content_sha256": _clean(target.get("content_sha256"), 64).lower() or None,
    }


def _normalize_anchor(raw: Any) -> dict[str, Any]:
    anchor = _dict(raw)
    out: dict[str, Any] = {
        "page": _clean(anchor.get("page"), 100) or None,
        "page_label": _clean(anchor.get("page_label"), 200) or None,
        "section": _clean(anchor.get("section"), 1000) or None,
        "paragraph": _clean(anchor.get("paragraph"), 200) or None,
        "figure": _clean(anchor.get("figure"), 200) or None,
        "table": _clean(anchor.get("table"), 200) or None,
        "start_char": anchor.get("start_char") if isinstance(anchor.get("start_char"), int) else None,
        "end_char": anchor.get("end_char") if isinstance(anchor.get("end_char"), int) else None,
        "start_time": _clean(anchor.get("start_time"), 100) or None,
        "end_time": _clean(anchor.get("end_time"), 100) or None,
        "fragment": _clean(anchor.get("fragment"), 1000) or None,
        "selector": _clean(anchor.get("selector"), 2000) or None,
        "selected_quote": _clean(anchor.get("selected_quote") or anchor.get("quote"), 16000) or None,
        "quote_verified": False,
    }
    if out["start_char"] is not None and out["start_char"] < 0:
        raise ValueError("anchor.start_char cannot be negative")
    if out["end_char"] is not None and out["end_char"] < 0:
        raise ValueError("anchor.end_char cannot be negative")
    if out["start_char"] is not None and out["end_char"] is not None and out["end_char"] < out["start_char"]:
        raise ValueError("anchor.end_char cannot precede anchor.start_char")
    return out


def normalize_annotation(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("annotation") if isinstance(payload, dict) and "annotation" in payload else payload)
    target = _normalize_target(raw.get("target"))
    anchor = _normalize_anchor(raw.get("anchor"))
    note_type = _clean(raw.get("note_type") or raw.get("type"), 100).lower() or "observation"
    if note_type not in NOTE_TYPES:
        note_type = "observation"
    body = _clean(raw.get("body") or raw.get("note") or raw.get("text"), MAX_BODY)
    if not body:
        raise ValueError("annotation body is required")
    tags = sorted({_clean(x, 100) for x in _list(raw.get("tags"))[:MAX_TAGS] if _clean(x, 100)})
    references = []
    for item in _list(raw.get("references"))[:100]:
        row = _dict(item)
        rid = _clean(row.get("id") or row.get("record_id") or row.get("citation_id"), 1000)
        uri = _clean(row.get("uri") or row.get("url"), 4000)
        if rid or uri:
            references.append({
                "id": rid or None,
                "uri": uri or None,
                "label": _clean(row.get("label") or row.get("title"), 2000) or None,
                "relationship": _clean(row.get("relationship"), 100) or "reference",
            })
    basis = {
        "target": target,
        "anchor": anchor,
        "note_type": note_type,
        "title": _clean(raw.get("title"), 1000),
        "body": body,
        "tags": tags,
        "references": references,
    }
    annotation_id = _clean(raw.get("annotation_id"), 500) or "research-annotation:" + _fp(basis)[:32]
    return {
        "schema": ANNOTATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "annotation_id": annotation_id,
        "annotation_fingerprint_sha256": _fp(basis),
        "note_type": note_type,
        "title": _clean(raw.get("title"), 1000) or None,
        "body": body,
        "target": target,
        "anchor": anchor,
        "tags": tags,
        "references": references,
        "author_label": _clean(raw.get("author_label"), 500) or None,
        "visibility": _clean(raw.get("visibility"), 50).lower() or "private",
        "reviewed": _bool(raw.get("reviewed")),
        "interpretive": note_type in {"critique", "interpretation", "context", "evidence-note", "contradiction"},
        "truth_status": None,
        "evidence_status": None,
        "citation_status": None,
        "persisted": False,
        "guardrails": guardrails(),
    }


def normalize_relation(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("relation") if isinstance(payload, dict) and isinstance(payload.get("relation"), dict) else payload)
    source_id = _clean(raw.get("source_annotation_id") or raw.get("source_id"), 500)
    target_id = _clean(raw.get("target_annotation_id") or raw.get("target_id"), 500)
    if not source_id or not target_id:
        raise ValueError("annotation relation requires source_annotation_id and target_annotation_id")
    relation = _clean(raw.get("relation") or raw.get("type"), 100).lower() or "unknown"
    if relation not in RELATION_TYPES:
        relation = "unknown"
    basis = {"source": source_id, "target": target_id, "relation": relation, "basis": _clean(raw.get("basis"), 6000)}
    return {
        "schema": RELATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "relation_id": "annotation-relation:" + _fp(basis)[:32],
        "source_annotation_id": source_id,
        "target_annotation_id": target_id,
        "relation": relation,
        "basis": _clean(raw.get("basis"), 6000) or None,
        "human_asserted": True,
        "inferred": False,
        "guardrails": guardrails(),
    }


def build_notebook(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload)
    annotations = [normalize_annotation(_dict(x)) for x in _list(raw.get("annotations"))[:MAX_ANNOTATIONS] if isinstance(x, dict)]
    relations = [normalize_relation(_dict(x)) for x in _list(raw.get("relations"))[:MAX_RELATIONS] if isinstance(x, dict)]
    by_target: dict[str, dict[str, Any]] = {}
    type_counts: dict[str, int] = {x: 0 for x in NOTE_TYPES}
    tag_counts: dict[str, int] = {}
    unresolved_questions = []
    for note in annotations:
        target = note["target"]
        target_key = target.get("id") or target.get("uri") or "unknown"
        bucket = by_target.setdefault(target_key, {"target": target, "annotation_ids": [], "note_count": 0})
        bucket["annotation_ids"].append(note["annotation_id"])
        bucket["note_count"] += 1
        type_counts[note["note_type"]] = type_counts.get(note["note_type"], 0) + 1
        for tag in note["tags"]:
            tag_counts[tag] = tag_counts.get(tag, 0) + 1
        if note["note_type"] == "question" and not note["reviewed"]:
            unresolved_questions.append({"annotation_id": note["annotation_id"], "target": target, "question": note["body"]})
    basis = {
        "title": _clean(raw.get("title"), 1000),
        "annotations": [x["annotation_fingerprint_sha256"] for x in annotations],
        "relations": [x["relation_id"] for x in relations],
    }
    return {
        "schema": NOTEBOOK_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "notebook_id": "scholarly-notebook:" + _fp(basis)[:32],
        "notebook_fingerprint_sha256": _fp(basis),
        "title": _clean(raw.get("title"), 1000) or "Scholarly notes",
        "research_question": _clean(raw.get("research_question"), 6000) or None,
        "annotation_count": len(annotations),
        "relation_count": len(relations),
        "annotations": annotations,
        "relations": relations,
        "targets": sorted(by_target.values(), key=lambda x: str(x["target"].get("title") or x["target"].get("id") or x["target"].get("uri") or "")),
        "note_type_counts": {k: v for k, v in type_counts.items() if v},
        "tag_counts": dict(sorted(tag_counts.items())),
        "unresolved_questions": unresolved_questions,
        "truth_determination": None,
        "automatic_synthesis": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def export_notes(payload: dict[str, Any]) -> dict[str, Any]:
    notebook = build_notebook(payload)
    manifest_basis = {
        "notebook_fingerprint_sha256": notebook["notebook_fingerprint_sha256"],
        "annotation_count": notebook["annotation_count"],
        "relation_count": notebook["relation_count"],
    }
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "export_id": "scholarly-notes-export:" + _fp(manifest_basis)[:32],
        "export_fingerprint_sha256": _fp(manifest_basis),
        "format": "application/json",
        "notebook": notebook,
        "manifest": manifest_basis,
        "persisted": False,
        "automatic_import": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
