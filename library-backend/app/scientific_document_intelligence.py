from __future__ import annotations

from collections import Counter, defaultdict
import hashlib
import json
import re
from typing import Any, Iterable

SCIENTIFIC_DOCUMENT_CONTRACT = "sc-library-scientific-document-intelligence/1.0"
SCIENTIFIC_OBJECT_CONTRACT = "sc-library-scientific-object/1.0"
SCIENTIFIC_GRAPH_OVERLAY_CONTRACT = "sc-library-scientific-object-graph-overlay/1.0"

SCIENTIFIC_OBJECT_KINDS = {
    "figure",
    "chart",
    "table",
    "equation",
    "caption",
    "appendix",
    "supplement",
    "dataset",
}

KIND_ALIASES = {
    "fig": "figure",
    "image": "figure",
    "illustration": "figure",
    "plot": "chart",
    "graph": "chart",
    "data-table": "table",
    "formula": "equation",
    "math": "equation",
    "supplementary": "supplement",
    "supplementary-material": "supplement",
    "data": "dataset",
}

REFERENCE_PREFIXES = {
    "figure": ("figure", "fig."),
    "chart": ("chart",),
    "table": ("table",),
    "equation": ("equation", "eq.", "eq"),
    "appendix": ("appendix",),
    "supplement": ("supplement", "supplementary"),
    "dataset": ("dataset", "data set"),
}


def _stable_hash(value: Any, *, length: int = 24) -> str:
    payload = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:length]


def _clean_text(value: Any, *, limit: int = 20000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _clean_multiline(value: Any, *, limit: int = 50000) -> str:
    return str(value or "").strip()[:limit]


def _as_dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _as_list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, tuple):
        return list(value)
    return []


def _clean_bbox(value: Any) -> list[float] | None:
    vals = _as_list(value)
    if len(vals) != 4:
        return None
    try:
        nums = [float(x) for x in vals]
    except (TypeError, ValueError):
        return None
    return nums


def _clean_page(value: Any) -> int | None:
    try:
        page = int(value)
    except (TypeError, ValueError):
        return None
    return page if 0 <= page <= 100000 else None


def _normalize_kind(value: Any) -> str | None:
    kind = str(value or "").strip().lower().replace("_", "-")
    kind = KIND_ALIASES.get(kind, kind)
    return kind if kind in SCIENTIFIC_OBJECT_KINDS else None


def _object_label(kind: str, raw: dict[str, Any], ordinal: int) -> str:
    for key in ("label", "title", "name", "number", "reference_label"):
        text = _clean_text(raw.get(key), limit=500)
        if text:
            return text
    return f"{kind.title()} {ordinal + 1}"


def _table_payload(raw: dict[str, Any]) -> dict[str, Any] | None:
    columns = [_clean_text(x, limit=500) for x in _as_list(raw.get("columns") or raw.get("headers"))]
    columns = [x for x in columns if x][:100]
    rows = _as_list(raw.get("rows"))[:250]
    clean_rows: list[list[Any]] = []
    for row in rows:
        if isinstance(row, dict):
            clean_rows.append([row.get(col) for col in columns] if columns else list(row.values())[:100])
        elif isinstance(row, (list, tuple)):
            clean_rows.append(list(row)[:100])
    declared_row_count = raw.get("row_count")
    try:
        row_count = int(declared_row_count) if declared_row_count is not None else len(clean_rows)
    except (TypeError, ValueError):
        row_count = len(clean_rows)
    declared_col_count = raw.get("column_count")
    try:
        column_count = int(declared_col_count) if declared_col_count is not None else (len(columns) or max([len(x) for x in clean_rows] or [0]))
    except (TypeError, ValueError):
        column_count = len(columns) or max([len(x) for x in clean_rows] or [0])
    if not columns and not clean_rows and row_count == 0 and column_count == 0:
        return None
    return {
        "columns": columns,
        "rows": clean_rows,
        "row_count": max(0, row_count),
        "column_count": max(0, column_count),
        "values_source": "supplied-structured-extraction",
        "values_inferred_from_pixels": False,
    }


def _equation_payload(raw: dict[str, Any]) -> dict[str, Any] | None:
    latex = _clean_multiline(raw.get("latex"), limit=20000)
    mathml = _clean_multiline(raw.get("mathml"), limit=40000)
    expression = _clean_multiline(raw.get("expression") or raw.get("text"), limit=20000)
    if not any((latex, mathml, expression)):
        return None
    return {
        "latex": latex or None,
        "mathml": mathml or None,
        "expression": expression or None,
        "equation_solved": False,
        "symbol_semantics_inferred": False,
    }


def _data_series(raw: dict[str, Any]) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for series in _as_list(raw.get("data_series") or raw.get("series"))[:100]:
        if not isinstance(series, dict):
            continue
        points = _as_list(series.get("points"))[:2000]
        out.append({
            "name": _clean_text(series.get("name"), limit=300),
            "x_label": _clean_text(series.get("x_label"), limit=200),
            "y_label": _clean_text(series.get("y_label"), limit=200),
            "points": points,
            "source": _clean_text(series.get("source") or "supplied-structured-extraction", limit=300),
            "values_inferred_from_pixels": False,
        })
    return out


def normalize_scientific_object(
    record_id: str,
    raw: dict[str, Any],
    *,
    ordinal: int,
    source_content_hash: str = "",
    chunk_ordinal: int | None = None,
) -> dict[str, Any] | None:
    kind = _normalize_kind(raw.get("kind") or raw.get("type") or raw.get("object_type"))
    if not kind:
        return None
    label = _object_label(kind, raw, ordinal)
    page = _clean_page(raw.get("page") or raw.get("page_number"))
    bbox = _clean_bbox(raw.get("bbox") or raw.get("bounding_box"))
    source_locator = _clean_text(raw.get("source_locator"), limit=1000)
    if not source_locator:
        parts: list[str] = []
        if page is not None:
            parts.append(f"page:{page}")
        if chunk_ordinal is not None:
            parts.append(f"chunk:{chunk_ordinal}")
        source_locator = ";".join(parts)
    explicit_id = _clean_text(raw.get("id") or raw.get("object_id"), limit=500)
    object_id = explicit_id or (
        "scientific-object:"
        + _stable_hash({
            "record_id": record_id,
            "kind": kind,
            "label": label,
            "page": page,
            "bbox": bbox,
            "ordinal": ordinal,
            "chunk_ordinal": chunk_ordinal,
            "source_content_hash": source_content_hash,
        })
    )
    caption = _clean_text(raw.get("caption"), limit=10000)
    extracted_text = _clean_multiline(raw.get("extracted_text") or raw.get("ocr_text"), limit=50000)
    alt_text = _clean_text(raw.get("alt_text"), limit=10000)
    human_reviewed = bool(raw.get("human_reviewed") or raw.get("reviewed"))
    ocr_verified = bool(raw.get("ocr_verified") or raw.get("text_verified"))
    structured_extraction = bool(raw.get("structured_extraction", True))
    object_payload: dict[str, Any] = {
        "schema": SCIENTIFIC_OBJECT_CONTRACT,
        "id": object_id,
        "record_id": record_id,
        "kind": kind,
        "label": label,
        "caption": caption or None,
        "source_locator": source_locator or None,
        "page": page,
        "bbox": bbox,
        "source_chunk_ordinal": chunk_ordinal,
        "source_content_hash": source_content_hash or _clean_text(raw.get("source_content_hash"), limit=128) or None,
        "asset_url": _clean_text(raw.get("asset_url") or raw.get("url"), limit=4000) or None,
        "mime_type": _clean_text(raw.get("mime_type"), limit=200) or None,
        "extracted_text": extracted_text or None,
        "alt_text": alt_text or None,
        "table": _table_payload(raw) if kind == "table" else None,
        "equation": _equation_payload(raw) if kind == "equation" else None,
        "data_series": _data_series(raw) if kind in {"figure", "chart"} else [],
        "explicit_links": [x for x in _as_list(raw.get("links") or raw.get("explicit_links")) if isinstance(x, dict)][:100],
        "verification": {
            "human_reviewed": human_reviewed,
            "ocr_text_verified": ocr_verified,
            "structured_extraction": structured_extraction,
        },
        "interpretation": {
            "values_inferred_from_pixels": False,
            "claims_inferred_from_object": False,
            "causality_inferred": False,
            "equation_solved": False,
            "ocr_text_is_verified": ocr_verified,
        },
    }
    return object_payload


def _scientific_objects_from_document(document: dict[str, Any]) -> list[dict[str, Any]]:
    record_id = _clean_text(document.get("record_id"), limit=500) or "unbound-document"
    source_content_hash = _clean_text(document.get("source_content_hash") or document.get("content_hash"), limit=128)
    candidates: list[tuple[dict[str, Any], int | None]] = []

    for raw in _as_list(document.get("scientific_objects") or document.get("objects")):
        if isinstance(raw, dict):
            candidates.append((raw, None))

    metadata = _as_dict(document.get("metadata"))
    for key in ("scientific_objects", "multimodal_objects", "document_objects"):
        for raw in _as_list(metadata.get(key)):
            if isinstance(raw, dict):
                candidates.append((raw, None))

    for chunk in _as_list(document.get("chunks")):
        if not isinstance(chunk, dict):
            continue
        chunk_ordinal = chunk.get("ordinal")
        try:
            chunk_ordinal = int(chunk_ordinal) if chunk_ordinal is not None else None
        except (TypeError, ValueError):
            chunk_ordinal = None
        chunk_meta = _as_dict(chunk.get("metadata"))
        for key in ("scientific_objects", "multimodal_objects", "document_objects"):
            for raw in _as_list(chunk_meta.get(key)):
                if isinstance(raw, dict):
                    candidates.append((raw, chunk_ordinal))

    objects: list[dict[str, Any]] = []
    seen: set[str] = set()
    for ordinal, (raw, chunk_ordinal) in enumerate(candidates[:5000]):
        obj = normalize_scientific_object(
            record_id,
            raw,
            ordinal=ordinal,
            source_content_hash=source_content_hash,
            chunk_ordinal=chunk_ordinal,
        )
        if not obj or obj["id"] in seen:
            continue
        seen.add(str(obj["id"]))
        objects.append(obj)
    return objects


def _reference_terms(obj: dict[str, Any]) -> list[str]:
    terms: list[str] = []
    label = _clean_text(obj.get("label"), limit=500)
    kind = str(obj.get("kind") or "")
    if label:
        terms.append(label)
        # Preserve exact numbered shorthand when the supplied label ends in a compact identifier.
        m = re.search(r"(?:^|\s)([A-Za-z]?\d+[A-Za-z]?)\s*$", label)
        if m:
            suffix = m.group(1)
            for prefix in REFERENCE_PREFIXES.get(kind, (kind,)):
                terms.append(f"{prefix} {suffix}")
    return list(dict.fromkeys(x.casefold() for x in terms if len(x) >= 3))


def _source_span_node(record_id: str, chunk: dict[str, Any], matched: str) -> dict[str, Any]:
    ordinal = chunk.get("ordinal")
    try:
        ordinal = int(ordinal) if ordinal is not None else None
    except (TypeError, ValueError):
        ordinal = None
    heading = _clean_text(chunk.get("heading"), limit=500)
    text = _clean_multiline(chunk.get("text"), limit=12000)
    lower = text.casefold()
    index = lower.find(matched.casefold())
    start = max(0, index - 140) if index >= 0 else 0
    end = min(len(text), (index + len(matched) + 180) if index >= 0 else 320)
    excerpt = " ".join(text[start:end].split())
    span_id = "scientific-span:" + _stable_hash({"record_id": record_id, "ordinal": ordinal, "matched": matched, "excerpt": excerpt})
    return {
        "id": span_id,
        "record_id": record_id,
        "kind": "source-span",
        "label": heading or (f"Source span {ordinal}" if ordinal is not None else "Source span"),
        "source_chunk_ordinal": ordinal,
        "source_locator": f"chunk:{ordinal}" if ordinal is not None else None,
        "matched_reference": matched,
        "excerpt": excerpt,
        "truth_determined": False,
    }


def build_scientific_document_intelligence(document: dict[str, Any]) -> dict[str, Any]:
    record_id = _clean_text(document.get("record_id"), limit=500) or "unbound-document"
    title = _clean_text(document.get("title"), limit=1000) or record_id
    source_content_hash = _clean_text(document.get("source_content_hash") or document.get("content_hash"), limit=128)
    objects = _scientific_objects_from_document(document)
    by_id = {str(obj["id"]): obj for obj in objects}
    nodes: dict[str, dict[str, Any]] = {
        record_id: {
            "id": record_id,
            "kind": "publication",
            "label": title,
            "source_content_hash": source_content_hash or None,
            "truth_determined": False,
        }
    }
    edges: list[dict[str, Any]] = []

    for obj in objects:
        node = {
            "id": obj["id"],
            "record_id": record_id,
            "kind": obj["kind"],
            "label": obj["label"],
            "caption": obj.get("caption"),
            "source_locator": obj.get("source_locator"),
            "source_chunk_ordinal": obj.get("source_chunk_ordinal"),
            "page": obj.get("page"),
            "bbox": obj.get("bbox"),
            "asset_url": obj.get("asset_url"),
            "mime_type": obj.get("mime_type"),
            "table_row_count": (_as_dict(obj.get("table")).get("row_count") if obj.get("table") else None),
            "table_column_count": (_as_dict(obj.get("table")).get("column_count") if obj.get("table") else None),
            "equation_latex": (_as_dict(obj.get("equation")).get("latex") if obj.get("equation") else None),
            "data_series_count": len(_as_list(obj.get("data_series"))),
            "source_content_hash": obj.get("source_content_hash"),
            "human_reviewed": bool(_as_dict(obj.get("verification")).get("human_reviewed")),
            "truth_determined": False,
        }
        nodes[str(obj["id"])] = node
        edges.append({
            "source": record_id,
            "target": str(obj["id"]),
            "relationship_basis": "contains-scientific-object",
            "directed": True,
            "weight": 1.0,
            "analytical": False,
            "truth_assertion": False,
            "provenance": {
                "source_locator": obj.get("source_locator"),
                "source_content_hash": obj.get("source_content_hash"),
                "object_kind": obj.get("kind"),
            },
        })

    references: list[dict[str, Any]] = []
    chunks = [x for x in _as_list(document.get("chunks")) if isinstance(x, dict)]
    for chunk in chunks[:5000]:
        text = _clean_multiline(chunk.get("text"), limit=200000)
        if not text:
            continue
        lower = text.casefold()
        for obj in objects:
            for term in _reference_terms(obj):
                if term not in lower:
                    continue
                span = _source_span_node(record_id, chunk, term)
                nodes.setdefault(str(span["id"]), span)
                edges.append({
                    "source": record_id,
                    "target": str(span["id"]),
                    "relationship_basis": "contains-source-span",
                    "directed": True,
                    "weight": 1.0,
                    "analytical": False,
                    "truth_assertion": False,
                })
                edge = {
                    "source": str(span["id"]),
                    "target": str(obj["id"]),
                    "relationship_basis": "explicit-scientific-cross-reference",
                    "directed": True,
                    "weight": 1.0,
                    "analytical": False,
                    "truth_assertion": False,
                    "provenance": {
                        "matched_reference": term,
                        "source_chunk_ordinal": span.get("source_chunk_ordinal"),
                        "source_locator": span.get("source_locator"),
                    },
                }
                edges.append(edge)
                references.append({
                    "source_span_id": span["id"],
                    "scientific_object_id": obj["id"],
                    "matched_reference": term,
                    "source_locator": span.get("source_locator"),
                })
                break

    for obj in objects:
        for link in _as_list(obj.get("explicit_links")):
            if not isinstance(link, dict):
                continue
            target = _clean_text(link.get("target_id") or link.get("target"), limit=500)
            if not target or target not in by_id:
                continue
            relation = _clean_text(link.get("relationship_basis") or link.get("relation"), limit=100).lower().replace("_", "-")
            if relation not in {"caption-describes", "dataset-link", "supplementary-material-link", "explicit-scientific-cross-reference"}:
                continue
            edges.append({
                "source": str(obj["id"]),
                "target": target,
                "relationship_basis": relation,
                "directed": True,
                "weight": 1.0,
                "analytical": False,
                "truth_assertion": False,
                "provenance": {"explicit_link": True, "source_content_hash": obj.get("source_content_hash")},
            })

    # Deduplicate exact graph edges while preserving deterministic order.
    edge_map: dict[tuple[str, str, str], dict[str, Any]] = {}
    for edge in edges:
        key = (str(edge.get("source")), str(edge.get("target")), str(edge.get("relationship_basis")))
        edge_map.setdefault(key, edge)
    edge_items = list(edge_map.values())

    kind_counts = Counter(str(x.get("kind")) for x in objects)
    located = sum(1 for x in objects if x.get("source_locator") or x.get("page") is not None or x.get("bbox"))
    captioned = sum(1 for x in objects if x.get("caption"))
    reviewed = sum(1 for x in objects if _as_dict(x.get("verification")).get("human_reviewed"))
    supplied_data_series = sum(len(_as_list(x.get("data_series"))) for x in objects)
    tables_with_structure = sum(1 for x in objects if x.get("kind") == "table" and x.get("table"))
    equations_with_source_form = sum(1 for x in objects if x.get("kind") == "equation" and x.get("equation"))

    fingerprint = _stable_hash({
        "record_id": record_id,
        "source_content_hash": source_content_hash,
        "objects": objects,
        "references": references,
    }, length=64)

    return {
        "schema": SCIENTIFIC_DOCUMENT_CONTRACT,
        "record_id": record_id,
        "title": title,
        "objects": objects,
        "references": references,
        "metrics": {
            "scientific_object_count": len(objects),
            "reference_count": len(references),
            "kind_counts": dict(sorted(kind_counts.items())),
            "located_object_count": located,
            "captioned_object_count": captioned,
            "human_reviewed_object_count": reviewed,
            "supplied_data_series_count": supplied_data_series,
            "tables_with_structured_cells": tables_with_structure,
            "equations_with_source_form": equations_with_source_form,
        },
        "coverage": {
            "source_locator_fraction": round(located / len(objects), 6) if objects else 0.0,
            "caption_fraction": round(captioned / len(objects), 6) if objects else 0.0,
            "human_reviewed_fraction": round(reviewed / len(objects), 6) if objects else 0.0,
        },
        "graph_overlay": {
            "schema": SCIENTIFIC_GRAPH_OVERLAY_CONTRACT,
            "nodes": list(nodes.values()),
            "edges": edge_items,
        },
        "reproducibility": {
            "fingerprint_sha256": fingerprint,
            "source_content_hash": source_content_hash or None,
            "deterministic_normalization": True,
            "structured_source_objects_only": True,
        },
        "platform_core": {
            "durable_research_object_authority": "platform-core",
            "automatic_core_write": False,
            "scientific_objects_require_explicit_handoff_for_core_governance": True,
        },
        "boundaries": {
            "values_inferred_from_pixels": False,
            "chart_trends_inferred": False,
            "claims_inferred_from_figures": False,
            "causality_inferred": False,
            "equations_solved": False,
            "ocr_text_automatically_treated_as_verified": False,
            "missing_units_invented": False,
            "missing_labels_invented": False,
            "scientific_object_presence_is_evidence_of_claim_truth": False,
        },
    }


def load_scientific_document_intelligence(record_id: str) -> dict[str, Any]:
    from .db import get_pool
    record_id = _clean_text(record_id, limit=500)
    if not record_id:
        raise ValueError("record_id is required")
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT record_id,title,content_hash,metadata,canonical_url,object_type
              FROM library_records
             WHERE record_id=%s
            """,
            (record_id,),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("record_id does not exist")
        record = dict(row)
        cur.execute(
            """
            SELECT ordinal,heading,text,metadata
              FROM library_record_chunks
             WHERE record_id=%s
             ORDER BY ordinal ASC
             LIMIT 5000
            """,
            (record_id,),
        )
        chunks = [dict(x) for x in cur.fetchall()]
    return build_scientific_document_intelligence({
        "record_id": record_id,
        "title": record.get("title"),
        "source_content_hash": record.get("content_hash"),
        "metadata": record.get("metadata") or {},
        "chunks": chunks,
    })


def build_scientific_corpus_overlay(
    records: dict[str, dict[str, Any]],
    chunks_by_record: dict[str, list[dict[str, Any]]] | None = None,
) -> dict[str, Any]:
    chunks_by_record = chunks_by_record or {}
    nodes: dict[str, dict[str, Any]] = {}
    edges: dict[tuple[str, str, str], dict[str, Any]] = {}
    per_record: list[dict[str, Any]] = []
    totals: Counter[str] = Counter()
    object_count = 0
    reference_count = 0

    for rid, record in records.items():
        result = build_scientific_document_intelligence({
            "record_id": rid,
            "title": record.get("title"),
            "source_content_hash": record.get("content_hash"),
            "metadata": record.get("metadata") or {},
            "chunks": chunks_by_record.get(rid, []),
        })
        metrics = _as_dict(result.get("metrics"))
        object_count += int(metrics.get("scientific_object_count") or 0)
        reference_count += int(metrics.get("reference_count") or 0)
        for kind, count in _as_dict(metrics.get("kind_counts")).items():
            try:
                totals[str(kind)] += int(count)
            except (TypeError, ValueError):
                pass
        if int(metrics.get("scientific_object_count") or 0) or int(metrics.get("reference_count") or 0):
            per_record.append({
                "record_id": rid,
                "scientific_object_count": int(metrics.get("scientific_object_count") or 0),
                "reference_count": int(metrics.get("reference_count") or 0),
                "kind_counts": metrics.get("kind_counts") or {},
                "fingerprint_sha256": _as_dict(result.get("reproducibility")).get("fingerprint_sha256"),
            })
        overlay = _as_dict(result.get("graph_overlay"))
        for node in _as_list(overlay.get("nodes")):
            if isinstance(node, dict) and node.get("id") and str(node.get("id")) != rid:
                nodes[str(node["id"])] = node
        for edge in _as_list(overlay.get("edges")):
            if isinstance(edge, dict) and edge.get("source") and edge.get("target"):
                key = (str(edge["source"]), str(edge["target"]), str(edge.get("relationship_basis") or "relationship"))
                edges.setdefault(key, edge)

    return {
        "schema": "sc-library-scientific-document-corpus/1.0",
        "metrics": {
            "scientific_object_count": object_count,
            "reference_count": reference_count,
            "records_with_scientific_objects": len(per_record),
            "kind_counts": dict(sorted(totals.items())),
        },
        "records": sorted(per_record, key=lambda x: str(x["record_id"])),
        "graph_overlay": {
            "schema": SCIENTIFIC_GRAPH_OVERLAY_CONTRACT,
            "nodes": list(nodes.values()),
            "edges": list(edges.values()),
        },
        "boundaries": {
            "structured_objects_only": True,
            "values_inferred_from_pixels": False,
            "claims_inferred_from_scientific_objects": False,
            "automatic_truth_promotion": False,
        },
    }
