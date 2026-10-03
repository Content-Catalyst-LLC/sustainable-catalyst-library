from __future__ import annotations

from collections import Counter
from hashlib import sha256
import json
from typing import Any

from .research_corpus_builder import build_research_corpus

LIBRARY_VERSION = "6.8.0"
BACKEND_VERSION = "3.8.0"
CONTRACT = "sc-library-dataset-table-structured-evidence/1.0"
READINESS_CONTRACT = "sc-library-dataset-table-structured-evidence-readiness/1.0"
DATASET_CONTRACT = "sc-library-dataset-object/1.0"
TABLE_CONTRACT = "sc-library-table-object/1.0"
COLUMN_CONTRACT = "sc-library-table-column/1.0"
CELL_CONTRACT = "sc-library-table-cell/1.0"
STRUCTURED_EVIDENCE_CONTRACT = "sc-library-structured-evidence-object/1.0"
VALIDATION_CONTRACT = "sc-library-structured-evidence-validation/1.0"
SCHEMA_REGISTRY_CONTRACT = "sc-library-structured-evidence-schema-registry/1.0"

MAX_ROWS = 10000
MAX_COLUMNS = 256
MAX_EVIDENCE_ITEMS = 5000


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any, limit: int = 500) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, (tuple, set)):
        return list(value)
    return [value]


def guardrails() -> dict[str, bool]:
    return {
        "research_corpus_builder_remains_dataset_lineage_source": True,
        "dataset_object_is_composition_not_new_persistence_authority": True,
        "table_object_is_composition_not_new_persistence_authority": True,
        "structured_evidence_is_explicit_annotation_not_inference": True,
        "row_provenance_is_preserved": True,
        "cell_provenance_is_derived_from_row_and_column_identity": True,
        "data_shape_implies_truth": False,
        "table_position_implies_importance": False,
        "numeric_precision_implies_certainty": False,
        "missing_value_implies_falsehood": False,
        "unit_label_performs_conversion": False,
        "column_type_implies_semantic_equivalence": False,
        "evidence_annotation_implies_evidence_strength": False,
        "validation_implies_scientific_validity": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "dataset-objects",
        "table-objects",
        "typed-columns",
        "stable-row-identities",
        "cell-provenance",
        "structured-evidence-annotations",
        "schema-validation",
        "corpus-dataset-lineage",
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "service_id": "library-structured-evidence:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "lineage_authority": "research-corpus-builder",
        "persistence_authority": "existing-library-services",
        "database_migration_required": False,
        "resources": resources,
        "limits": {"rows": MAX_ROWS, "columns": MAX_COLUMNS, "evidence_items": MAX_EVIDENCE_ITEMS},
        "wordpress": {"role": "optional-adapter", "required": False, "authoritative": False},
        "guardrails": guardrails(),
    }


def schema_registry() -> dict[str, Any]:
    return {
        "schema": SCHEMA_REGISTRY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "schemas": {
            "dataset": DATASET_CONTRACT,
            "table": TABLE_CONTRACT,
            "column": COLUMN_CONTRACT,
            "cell": CELL_CONTRACT,
            "structured_evidence": STRUCTURED_EVIDENCE_CONTRACT,
            "validation": VALIDATION_CONTRACT,
            "upstream_research_corpus": "sc-library-research-corpus/1.0",
            "upstream_dataset_export": "sc-library-dataset-export/1.0",
            "upstream_row_provenance": "sc-library-dataset-row-provenance/1.0",
        },
        "guardrails": guardrails(),
    }


def _value_type(value: Any) -> str:
    if value is None:
        return "null"
    if isinstance(value, bool):
        return "boolean"
    if isinstance(value, int) and not isinstance(value, bool):
        return "integer"
    if isinstance(value, float):
        return "number"
    if isinstance(value, dict):
        return "object"
    if isinstance(value, (list, tuple)):
        return "array"
    return "string"


def _column_specs(rows: list[dict[str, Any]], requested: Any = None) -> list[dict[str, Any]]:
    requested_specs = []
    if isinstance(requested, list):
        for item in requested[:MAX_COLUMNS]:
            if isinstance(item, str):
                requested_specs.append({"name": _clean(item, 160)})
            elif isinstance(item, dict):
                name = _clean(item.get("name") or item.get("key"), 160)
                if name:
                    requested_specs.append({**dict(item), "name": name})

    if requested_specs:
        names = []
        seen = set()
        for spec in requested_specs:
            name = spec["name"]
            if name not in seen:
                seen.add(name)
                names.append(name)
    else:
        names = []
        seen = set()
        for row in rows:
            for key in row:
                name = _clean(key, 160)
                if name and name not in seen:
                    seen.add(name)
                    names.append(name)
                    if len(names) >= MAX_COLUMNS:
                        break
            if len(names) >= MAX_COLUMNS:
                break
        requested_specs = [{"name": name} for name in names]

    out = []
    for position, spec in enumerate(requested_specs):
        name = spec["name"]
        observed = Counter(_value_type(row.get(name)) for row in rows)
        non_null = [kind for kind, n in observed.items() if kind != "null" and n]
        inferred = non_null[0] if len(non_null) == 1 else ("mixed" if non_null else "null")
        declared = _clean(spec.get("data_type") or spec.get("type"), 80).lower()
        data_type = declared or inferred
        role = _clean(spec.get("role") or "value", 80).lower()
        unit = _clean(spec.get("unit") or spec.get("unit_label"), 120) or None
        column = {
            "schema": COLUMN_CONTRACT,
            "column_id": "column:" + _fp({"name": name, "position": position, "type": data_type, "unit": unit, "role": role})[:24],
            "name": name,
            "position": position,
            "data_type": data_type,
            "inferred_data_type": inferred,
            "role": role,
            "unit": unit,
            "nullable": observed.get("null", 0) > 0,
            "observed_types": dict(sorted(observed.items())),
            "semantic_type": _clean(spec.get("semantic_type"), 240) or None,
            "description": _clean(spec.get("description"), 1000) or None,
            "unit_conversion_performed": False,
            "data_type_is_semantic_equivalence": False,
        }
        column["column_fingerprint_sha256"] = _fp(column)
        out.append(column)
    return out


def dataset_object(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("dataset payload must be an object")
    corpus = payload.get("corpus") if isinstance(payload.get("corpus"), dict) else None
    if corpus is None:
        corpus_payload = dict(payload)
        corpus_payload["records"] = list(_list(payload.get("records")))[:MAX_ROWS]
        corpus = build_research_corpus(corpus_payload)
    if corpus.get("schema") != "sc-library-research-corpus/1.0":
        raise ValueError("corpus payload must use sc-library-research-corpus/1.0")
    rows = [dict(row) for row in _list(corpus.get("rows")) if isinstance(row, dict)][:MAX_ROWS]
    row_provenance = [dict(row) for row in _list(corpus.get("row_provenance")) if isinstance(row, dict)][:MAX_ROWS]
    manifest = _dict(corpus.get("manifest"))
    columns = _column_specs(rows, payload.get("columns"))
    basis = {
        "corpus_id": corpus.get("corpus_id"),
        "dataset_fingerprint": manifest.get("dataset_fingerprint_sha256"),
        "columns": [x["column_fingerprint_sha256"] for x in columns],
        "rows": rows,
        "row_provenance": row_provenance,
    }
    dataset_id = "dataset:" + _fp(basis)[:32]
    return {
        "schema": DATASET_CONTRACT,
        "dataset_id": dataset_id,
        "dataset_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "title": _clean(payload.get("title") or manifest.get("title") or "Structured dataset", 240),
        "description": _clean(payload.get("description") or manifest.get("description"), 2000) or None,
        "corpus_id": corpus.get("corpus_id"),
        "corpus_fingerprint_sha256": manifest.get("corpus_fingerprint_sha256"),
        "upstream_dataset_fingerprint_sha256": manifest.get("dataset_fingerprint_sha256"),
        "columns": columns,
        "rows": rows,
        "row_provenance": row_provenance,
        "row_count": len(rows),
        "column_count": len(columns),
        "database_persisted": False,
        "authority": "python-backend-composition",
        "guardrails": guardrails(),
    }


def _dataset_from_payload(payload: dict[str, Any]) -> dict[str, Any]:
    dataset = payload.get("dataset") if isinstance(payload.get("dataset"), dict) else None
    if dataset is not None:
        if dataset.get("schema") != DATASET_CONTRACT:
            raise ValueError("dataset payload does not use sc-library-dataset-object/1.0")
        return dict(dataset)
    return dataset_object(payload)


def table_object(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("table payload must be an object")
    dataset = _dataset_from_payload(payload)
    rows = [dict(row) for row in _list(dataset.get("rows")) if isinstance(row, dict)][:MAX_ROWS]
    columns = _column_specs(rows, payload.get("columns") or dataset.get("columns"))
    names = [c["name"] for c in columns]
    row_prov = [dict(row) for row in _list(dataset.get("row_provenance")) if isinstance(row, dict)]
    provenance_by_index = {int(p.get("row_index")): p for p in row_prov if isinstance(p.get("row_index"), int)}
    table_rows = []
    cells = []
    for index, row in enumerate(rows):
        normalized = {name: row.get(name) for name in names}
        row_id = "table-row:" + _fp({"dataset_id": dataset.get("dataset_id"), "index": index, "row": normalized})[:28]
        row_fp = _fp(normalized)
        table_rows.append({"row_id": row_id, "row_index": index, "row_fingerprint_sha256": row_fp, "values": normalized})
        row_source = provenance_by_index.get(index, {})
        for column in columns:
            name = column["name"]
            value = normalized.get(name)
            cell = {
                "schema": CELL_CONTRACT,
                "cell_id": "cell:" + _fp({"row_id": row_id, "column_id": column["column_id"]})[:30],
                "row_id": row_id,
                "row_index": index,
                "column_id": column["column_id"],
                "column_name": name,
                "value": value,
                "value_type": _value_type(value),
                "source_record_id": row_source.get("source_record_id"),
                "source_record_fingerprint_sha256": row_source.get("source_record_fingerprint_sha256"),
                "source_row_fingerprint_sha256": row_source.get("row_fingerprint_sha256"),
                "unit": column.get("unit"),
                "derived_from_row_and_column_identity": True,
            }
            cell["cell_fingerprint_sha256"] = _fp(cell)
            cells.append(cell)
    basis = {
        "dataset_id": dataset.get("dataset_id"),
        "columns": [c["column_fingerprint_sha256"] for c in columns],
        "rows": [r["row_fingerprint_sha256"] for r in table_rows],
        "cells": [c["cell_fingerprint_sha256"] for c in cells],
    }
    return {
        "schema": TABLE_CONTRACT,
        "table_id": "table:" + _fp(basis)[:32],
        "table_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "dataset_id": dataset.get("dataset_id"),
        "title": _clean(payload.get("title") or dataset.get("title") or "Structured evidence table", 240),
        "description": _clean(payload.get("description") or dataset.get("description"), 2000) or None,
        "columns": columns,
        "rows": table_rows,
        "cells": cells,
        "row_count": len(table_rows),
        "column_count": len(columns),
        "cell_count": len(cells),
        "database_persisted": False,
        "authority": "python-backend-composition",
        "guardrails": guardrails(),
    }


def structured_evidence_object(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("structured evidence payload must be an object")
    table = payload.get("table") if isinstance(payload.get("table"), dict) else None
    if table is None:
        table = table_object(payload)
    if table.get("schema") != TABLE_CONTRACT:
        raise ValueError("table payload does not use sc-library-table-object/1.0")
    row_ids = {str(r.get("row_id")) for r in _list(table.get("rows")) if isinstance(r, dict)}
    column_names = {str(c.get("name")) for c in _list(table.get("columns")) if isinstance(c, dict)}
    cell_ids = {str(c.get("cell_id")) for c in _list(table.get("cells")) if isinstance(c, dict)}
    items = []
    for raw in _list(payload.get("evidence_items"))[:MAX_EVIDENCE_ITEMS]:
        if not isinstance(raw, dict):
            continue
        row_id = _clean(raw.get("row_id"), 200)
        column_name = _clean(raw.get("column_name") or raw.get("column"), 160)
        cell_id = _clean(raw.get("cell_id"), 200)
        if row_id and row_id not in row_ids:
            raise ValueError(f"evidence item references unknown row_id: {row_id}")
        if column_name and column_name not in column_names:
            raise ValueError(f"evidence item references unknown column: {column_name}")
        if cell_id and cell_id not in cell_ids:
            raise ValueError(f"evidence item references unknown cell_id: {cell_id}")
        if not (row_id or cell_id or _clean(raw.get("source_record_id"), 512)):
            raise ValueError("evidence item requires row_id, cell_id, or source_record_id")
        item = {
            "evidence_item_id": "structured-evidence-item:" + _fp(raw)[:28],
            "row_id": row_id or None,
            "column_name": column_name or None,
            "cell_id": cell_id or None,
            "source_record_id": _clean(raw.get("source_record_id"), 512) or None,
            "claim_id": _clean(raw.get("claim_id"), 512) or None,
            "role": _clean(raw.get("role") or "observation", 80).lower(),
            "observation": raw.get("observation"),
            "note": _clean(raw.get("note"), 4000) or None,
            "provenance": _dict(raw.get("provenance")),
            "evidence_strength": None,
            "truth_probability": None,
            "explicit_user_or_source_annotation": True,
        }
        item["evidence_item_fingerprint_sha256"] = _fp(item)
        items.append(item)
    basis = {"table_id": table.get("table_id"), "items": [x["evidence_item_fingerprint_sha256"] for x in items]}
    return {
        "schema": STRUCTURED_EVIDENCE_CONTRACT,
        "structured_evidence_id": "structured-evidence:" + _fp(basis)[:32],
        "structured_evidence_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "table": table,
        "evidence_items": items,
        "evidence_item_count": len(items),
        "interpretation": {
            "evidence_strength_computed": False,
            "truth_probability_computed": False,
            "causal_inference_performed": False,
            "scientific_validity_certified": False,
        },
        "database_persisted": False,
        "authority": "python-backend-composition",
        "guardrails": guardrails(),
    }


def validate_object(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("validation payload must be an object")
    obj = payload.get("object") if isinstance(payload.get("object"), dict) else payload
    schema = str(obj.get("schema") or "")
    errors: list[str] = []
    warnings: list[str] = []
    metrics: dict[str, Any] = {}
    if schema == DATASET_CONTRACT:
        rows = _list(obj.get("rows")); cols = _list(obj.get("columns")); prov = _list(obj.get("row_provenance"))
        metrics = {"row_count": len(rows), "column_count": len(cols), "row_provenance_count": len(prov)}
        if len(rows) != len(prov): errors.append("row-provenance-count-mismatch")
        names = [str(c.get("name") or "") for c in cols if isinstance(c, dict)]
        if len(names) != len(set(names)): errors.append("duplicate-column-name")
    elif schema == TABLE_CONTRACT:
        rows = _list(obj.get("rows")); cols = _list(obj.get("columns")); cells = _list(obj.get("cells"))
        metrics = {"row_count": len(rows), "column_count": len(cols), "cell_count": len(cells)}
        if len(cells) != len(rows) * len(cols): errors.append("cell-grid-cardinality-mismatch")
    elif schema == STRUCTURED_EVIDENCE_CONTRACT:
        table = obj.get("table") if isinstance(obj.get("table"), dict) else {}
        if table.get("schema") != TABLE_CONTRACT: errors.append("structured-evidence-table-schema-invalid")
        items = _list(obj.get("evidence_items"))
        metrics = {"evidence_item_count": len(items), "table_row_count": table.get("row_count"), "table_column_count": table.get("column_count")}
        if any(isinstance(x, dict) and (x.get("evidence_strength") is not None or x.get("truth_probability") is not None) for x in items):
            warnings.append("non-null-evidence-strength-or-truth-probability-is-external-to-this-contract")
    else:
        errors.append("unsupported-structured-evidence-schema")
    return {
        "schema": VALIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "metrics": metrics,
        "validation_scope": "structural-contract-and-provenance-integrity",
        "scientific_validity_certified": False,
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    probe = dataset_object({
        "title": "readiness-probe",
        "records": [{"record_id": "probe:1", "title": "Probe", "source_type": "synthetic-readiness"}],
        "fields": ["record_id", "title", "source_type"],
    })
    check = validate_object({"object": probe})
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if check.get("valid") else "degraded",
        "authority": "python-backend-composition",
        "database_migration_required": False,
        "database_write_required": False,
        "research_corpus_builder_reused": True,
        "synthetic_probe_persisted": False,
        "resources": contract()["resources"],
        "guardrails": guardrails(),
    }
