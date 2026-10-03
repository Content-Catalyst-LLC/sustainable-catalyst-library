#!/usr/bin/env python3
from __future__ import annotations

import ast
import importlib.util
from pathlib import Path
import sys
import types

ROOT = Path(__file__).resolve().parents[1]
MODULE_PATH = ROOT / "library-backend/app/structured_evidence_objects.py"


def load_module():
    package = types.ModuleType("app")
    package.__path__ = [str(MODULE_PATH.parent)]
    sys.modules["app"] = package

    builder = types.ModuleType("app.research_corpus_builder")
    def fake_build(payload):
        records = list(payload.get("records") or [])
        fields = list(payload.get("fields") or ["record_id", "title", "value"])
        rows=[]; prov=[]
        for idx, record in enumerate(records):
            row={field:record.get(field) for field in fields}
            rows.append(row)
            prov.append({
                "schema":"sc-library-dataset-row-provenance/1.0",
                "row_index":idx,
                "source_record_id":record.get("record_id"),
                "source_record_fingerprint_sha256":f"source-{idx}",
                "row_fingerprint_sha256":f"row-{idx}",
            })
        return {
            "schema":"sc-library-research-corpus/1.0",
            "corpus_id":"research-corpus:test",
            "manifest":{
                "title":payload.get("title") or "Test corpus",
                "description":payload.get("description"),
                "corpus_fingerprint_sha256":"corpus-fp",
                "dataset_fingerprint_sha256":"dataset-fp",
            },
            "rows":rows,
            "row_provenance":prov,
        }
    builder.build_research_corpus = fake_build
    sys.modules["app.research_corpus_builder"] = builder

    spec = importlib.util.spec_from_file_location("app.structured_evidence_objects", MODULE_PATH)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def main():
    source=MODULE_PATH.read_text(encoding="utf-8")
    ast.parse(source)
    for token in [
        'LIBRARY_VERSION = "6.8.0"',
        'BACKEND_VERSION = "3.8.0"',
        'DATASET_CONTRACT = "sc-library-dataset-object/1.0"',
        'TABLE_CONTRACT = "sc-library-table-object/1.0"',
        'CELL_CONTRACT = "sc-library-table-cell/1.0"',
        'STRUCTURED_EVIDENCE_CONTRACT = "sc-library-structured-evidence-object/1.0"',
        '"data_shape_implies_truth": False',
        '"numeric_precision_implies_certainty": False',
        '"validation_implies_scientific_validity": False',
        '"automatic_truth_promotion": False',
        'def dataset_object(',
        'def table_object(',
        'def structured_evidence_object(',
        'def validate_object(',
    ]:
        assert token in source, token

    m=load_module()
    dataset=m.dataset_object({
        "title":"Weathering observations",
        "records":[
            {"record_id":"r1","title":"A","value":1.2,"unit":"t/ha"},
            {"record_id":"r2","title":"B","value":2.0,"unit":"t/ha"},
        ],
        "fields":["record_id","title","value","unit"],
        "columns":[
            {"name":"record_id","role":"identifier"},
            {"name":"title"},
            {"name":"value","data_type":"number","unit":"t/ha","role":"measure"},
            {"name":"unit","role":"unit-label"},
        ],
    })
    assert dataset["schema"]==m.DATASET_CONTRACT
    assert dataset["row_count"]==2
    assert dataset["column_count"]==4
    assert len(dataset["row_provenance"])==2
    value_col=next(x for x in dataset["columns"] if x["name"]=="value")
    assert value_col["unit"]=="t/ha"
    assert value_col["unit_conversion_performed"] is False

    table=m.table_object({"dataset":dataset})
    assert table["schema"]==m.TABLE_CONTRACT
    assert table["row_count"]==2
    assert table["column_count"]==4
    assert table["cell_count"]==8
    assert all(cell["derived_from_row_and_column_identity"] for cell in table["cells"])

    first_row=table["rows"][0]["row_id"]
    first_value_cell=next(c for c in table["cells"] if c["row_id"]==first_row and c["column_name"]=="value")
    evidence=m.structured_evidence_object({
        "table":table,
        "evidence_items":[{
            "row_id":first_row,
            "cell_id":first_value_cell["cell_id"],
            "column_name":"value",
            "source_record_id":"r1",
            "role":"observation",
            "observation":"Explicitly recorded measurement",
        }],
    })
    assert evidence["schema"]==m.STRUCTURED_EVIDENCE_CONTRACT
    assert evidence["evidence_item_count"]==1
    assert evidence["evidence_items"][0]["evidence_strength"] is None
    assert evidence["interpretation"]["truth_probability_computed"] is False

    assert m.validate_object({"object":dataset})["valid"] is True
    assert m.validate_object({"object":table})["valid"] is True
    assert m.validate_object({"object":evidence})["valid"] is True
    ready=m.readiness()
    assert ready["state"]=="ready"
    assert ready["database_migration_required"] is False
    assert ready["synthetic_probe_persisted"] is False

    print("PASS: Library v6.8.0 Dataset, Table & Structured Evidence Objects behavior and guardrails")


if __name__ == "__main__":
    main()
