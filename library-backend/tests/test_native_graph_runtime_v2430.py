from app.native_graph_runtime import NATIVE_GRAPH_CONTRACT, NATIVE_GRAPH_VERSION, native_graph_runtime_status
from app.research_graph_pathfinding import find_research_paths


def sample():
    return {
        "nodes": [
            {"id":"pub:a","kind":"publication"}, {"id":"pub:b","kind":"publication"}, {"id":"finding:1","kind":"finding"}
        ],
        "edges": [
            {"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":False},
            {"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":True},
        ],
    }


def test_native_contract_status_is_safe_when_binary_absent():
    status = native_graph_runtime_status()
    assert status["schema"] == NATIVE_GRAPH_CONTRACT
    assert status["runtime_version"] == NATIVE_GRAPH_VERSION
    assert status["research_semantics_authority"] == "python-library-backend"
    assert status["native_runtime_changes_research_semantics"] is False


def test_auto_runtime_preserves_path_contract_with_python_fallback():
    result = find_research_paths(sample(), {"start_node_ids":["finding:1"],"target_node_ids":["pub:b"],"runtime":"auto"})
    assert result["paths"]
    assert result["runtime"]["used"] in {"python","rust"}
    assert result["interpretation"]["native_runtime_changes_research_semantics"] is False
    assert result["interpretation"]["python_fallback_preserves_contract"] is True


def test_forced_rust_fails_safe_to_python_if_native_missing():
    result = find_research_paths(sample(), {"start_node_ids":["finding:1"],"target_node_ids":["pub:b"],"runtime":"rust"})
    assert result["paths"]
    assert result["runtime"]["used"] in {"rust","python-fallback"}

def test_native_adapter_parses_runtime_path_output(tmp_path, monkeypatch):
    fake = tmp_path / "fake-native"
    fake.write_text("#!/bin/sh\nif [ \"$1\" = status ]; then printf 'STATUS\\tsc-library-native-graph-runtime/1.0\\t0.1.0\\trust\\tstd-only\\tpathfinding-foundation\\n'; else printf 'META\\tsc-library-native-graph-runtime/1.0\\t0.1.0\\nPATH\\tfinding:1\\tpub:b\\t0:undirected,1:forward\\n'; fi\n", encoding="utf-8")
    fake.chmod(0o755)
    monkeypatch.setenv("SC_LIBRARY_NATIVE_GRAPH_BIN", str(fake))
    result = find_research_paths(sample(), {"start_node_ids":["finding:1"],"target_node_ids":["pub:b"],"runtime":"rust"})
    assert result["runtime"]["used"] == "rust"
    assert result["paths"][0]["node_ids"] == ["finding:1","pub:a","pub:b"]
    assert result["paths"][0]["relationship_bases"] == ["reviewed-finding-evidence","explicit-citation"]
