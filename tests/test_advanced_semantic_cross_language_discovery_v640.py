from __future__ import annotations

import ast
import importlib.util
from pathlib import Path
import sys
import types

ROOT = Path(__file__).resolve().parents[1]
MODULE_PATH = ROOT / "library-backend/app/advanced_discovery.py"


def load_module():
    package = types.ModuleType("app")
    package.__path__ = [str(MODULE_PATH.parent)]
    sys.modules["app"] = package

    retrieval = types.ModuleType("app.retrieval_orchestration")
    def fake_execute(payload):
        q = payload.get("q")
        if q == "climate":
            rows = [
                {"record_id":"r1","title":"Climate","metadata":{"language":"en"}},
                {"record_id":"r2","title":"Klima","metadata":{"language":"de"}},
            ]
        elif q == "مناخ":
            rows = [
                {"record_id":"r3","title":"مناخ","metadata":{"language":"ar"}},
                {"record_id":"r1","title":"Climate","metadata":{"language":"en"}},
            ]
        else:
            rows = [{"record_id":"r4","title":q}]
        return {
            "results": rows,
            "total": len(rows),
            "retrieval": {"effective_mode": payload.get("mode")},
            "reranking": {"mode": payload.get("rerank")},
            "orchestration": {"fake": True},
        }
    retrieval.execute = fake_execute
    retrieval.readiness = lambda: {"state":"ready","semantic_search":{"available":True}}
    sys.modules["app.retrieval_orchestration"] = retrieval

    language = types.ModuleType("app.language_document_service")
    language.readiness = lambda: {"state":"ready"}
    sys.modules["app.language_document_service"] = language

    spec = importlib.util.spec_from_file_location("app.advanced_discovery", MODULE_PATH)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def main():
    source = MODULE_PATH.read_text(encoding="utf-8")
    ast.parse(source)
    for token in [
        'LIBRARY_VERSION = "6.4.0"',
        'BACKEND_VERSION = "3.4.0"',
        'CONTRACT = "sc-library-advanced-semantic-cross-language-discovery/1.0"',
        'def plan(',
        'def execute(',
        'def execute_project(',
        '"original_query_remains_canonical": True',
        '"translation_is_derived_representation": True',
        '"ranking_score_is_truth_probability": False',
        '"next_release": "6.5.0"',
    ]:
        assert token in source, token

    m = load_module()
    p = m.plan({
        "q": "climate",
        "mode": "hybrid",
        "query_representations": [
            {"text":"مناخ","language":"ar","representation_type":"translation","provenance":{"source":"reviewed-alignment"}},
        ],
    })
    reps = p["normalized"]["query_representations"]
    assert len(reps) == 2
    assert reps[0]["canonical"] is True and reps[0]["derived"] is False
    assert reps[1]["canonical"] is False and reps[1]["derived"] is True
    assert reps[1]["script"] == "Arabic"
    assert p["cross_language_expansion_state"] == "explicit-derived-representations"

    result = m.execute({
        "q": "climate",
        "mode": "hybrid",
        "rerank": "none",
        "query_representations": [{"text":"مناخ","language":"ar","representation_type":"translation"}],
        "limit": 10,
    })
    ids = [row["record_id"] for row in result["results"]]
    assert ids[0] == "r1", ids
    assert set(ids) == {"r1","r2","r3"}
    r1 = next(row for row in result["results"] if row["record_id"] == "r1")
    assert r1["discovery_signals"]["matched_representation_count"] == 2
    assert r1["discovery_signals"]["ranking_score_is_truth_probability"] is False

    project = {
        "context_id":"ctx:1",
        "project":{"project_id":"p1"},
        "references":[{"record_id":"r3"}],
    }
    project_result = m.execute_project({
        "q":"climate",
        "rerank":"none",
        "query_representations":[{"text":"مناخ","representation_type":"translation"}],
    }, project)
    r3 = next(row for row in project_result["results"] if row["record_id"] == "r3")
    assert r3["discovery_signals"]["project_reference_match"] is True
    assert project_result["project_context"]["ranking_boost_is_truth_signal"] is False

    ready = m.readiness()
    assert ready["state"] == "ready"
    assert ready["wordpress_required"] is False
    assert ready["automatic_machine_translation_required"] is False

    print("PASS: Library v6.4.0 Advanced Semantic & Cross-Language Discovery service contract and fusion behavior")


if __name__ == "__main__":
    main()
