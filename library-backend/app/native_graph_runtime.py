from __future__ import annotations

import os
from pathlib import Path
import subprocess
import tempfile
from typing import Any

NATIVE_GRAPH_CONTRACT = "sc-library-native-graph-runtime/1.0"
NATIVE_GRAPH_VERSION = "0.2.0"
NATIVE_QUERY_CONTRACT = "sc-library-native-graph-query/1.0"
DEFAULT_NATIVE_BIN = "/usr/local/bin/sc-library-graph-runtime"


def _binary_path() -> str:
    return os.getenv("SC_LIBRARY_NATIVE_GRAPH_BIN", DEFAULT_NATIVE_BIN).strip() or DEFAULT_NATIVE_BIN


def _runtime_files(
    base: Path,
    nodes: list[dict[str, Any]],
    edges: list[dict[str, Any]],
    allowed_edge_indexes: list[int],
) -> tuple[Path, Path]:
    nodes_file = base / "nodes.tsv"
    edges_file = base / "edges.tsv"
    nodes_file.write_text(
        "".join(
            f"{str(n.get('id') or '').replace(chr(9),' ')}\t{str(n.get('kind') or '').replace(chr(9),' ')}\n"
            for n in nodes
            if n.get("id")
        ),
        encoding="utf-8",
    )
    allowed = set(allowed_edge_indexes)
    edge_lines = []
    for idx, edge in enumerate(edges):
        if idx not in allowed:
            continue
        source = str(edge.get("source") or "").replace("\t", " ")
        target = str(edge.get("target") or "").replace("\t", " ")
        basis = str(
            edge.get("relationship_basis")
            or edge.get("basis")
            or edge.get("type")
            or "relationship"
        ).replace("\t", " ")
        edge_lines.append(
            f"{idx}\t{source}\t{target}\t{basis}\t{1 if edge.get('directed') else 0}\n"
        )
    edges_file.write_text("".join(edge_lines), encoding="utf-8")
    return nodes_file, edges_file


def native_graph_runtime_status() -> dict[str, Any]:
    path = _binary_path()
    result: dict[str, Any] = {
        "schema": NATIVE_GRAPH_CONTRACT,
        "query_schema": NATIVE_QUERY_CONTRACT,
        "runtime_version": NATIVE_GRAPH_VERSION,
        "engine": "rust",
        "binary_path": path,
        "available": False,
        "capabilities": [
            "bounded-pathfinding",
            "filtered-neighborhood",
            "reachability",
            "connected-components",
            "induced-subgraph",
            "structural-stats",
        ],
        "research_semantics_authority": "python-library-backend",
        "platform_core_durable_authority": True,
        "native_runtime_changes_research_semantics": False,
        "analytical_relationships_require_explicit_opt_in": True,
    }
    try:
        completed = subprocess.run(
            [path, "status"], capture_output=True, text=True, timeout=3, check=True
        )
        line = (completed.stdout or "").strip().splitlines()[0]
        parts = line.split("\t")
        if len(parts) >= 3 and parts[0] == "STATUS" and parts[1] == NATIVE_GRAPH_CONTRACT:
            result["available"] = True
            result["reported_version"] = parts[2]
            result["status_line"] = line
            if len(parts) >= 6:
                result["reported_capabilities"] = [x for x in parts[5].split(",") if x]
        else:
            result["error"] = "native runtime returned an incompatible status contract"
    except Exception as exc:
        result["error"] = f"{type(exc).__name__}: {exc}"
    return result


def native_path_steps(
    nodes: list[dict[str, Any]],
    edges: list[dict[str, Any]],
    *,
    start_ids: list[str],
    target_ids: set[str],
    target_kinds: set[str],
    allowed_edge_indexes: list[int],
    max_hops: int,
    max_paths: int,
    direction: str,
) -> list[dict[str, Any]] | None:
    path = _binary_path()
    if not Path(path).is_file():
        return None
    with tempfile.TemporaryDirectory(prefix="sc-library-native-graph-") as tmp:
        base = Path(tmp)
        nodes_file, edges_file = _runtime_files(base, nodes, edges, allowed_edge_indexes)
        cmd = [
            path,
            "pathfind",
            "--nodes",
            str(nodes_file),
            "--edges",
            str(edges_file),
            "--starts",
            ",".join(start_ids),
            "--targets",
            ",".join(sorted(target_ids)),
            "--target-kinds",
            ",".join(sorted(target_kinds)),
            "--max-hops",
            str(max_hops),
            "--max-paths",
            str(max_paths),
            "--direction",
            direction,
        ]
        try:
            completed = subprocess.run(cmd, capture_output=True, text=True, timeout=15, check=True)
        except Exception:
            return None
        lines = [x for x in (completed.stdout or "").splitlines() if x.strip()]
        if not lines or not lines[0].startswith("META\t" + NATIVE_GRAPH_CONTRACT + "\t"):
            return None
        out: list[dict[str, Any]] = []
        for line in lines[1:]:
            parts = line.split("\t")
            if len(parts) != 4 or parts[0] != "PATH":
                continue
            source, target = parts[1], parts[2]
            steps = []
            node_ids = [source]
            current = source
            for token in [x for x in parts[3].split(",") if x]:
                seg = token.split(":", 1)
                if len(seg) != 2:
                    continue
                idx_s, traversed_direction = seg
                try:
                    edge_index = int(idx_s)
                except ValueError:
                    continue
                if edge_index < 0 or edge_index >= len(edges):
                    continue
                edge = edges[edge_index]
                edge_source = str(edge.get("source") or "")
                edge_target = str(edge.get("target") or "")
                if traversed_direction == "reverse":
                    from_id, to_id = edge_target, edge_source
                elif traversed_direction == "forward":
                    from_id, to_id = edge_source, edge_target
                else:
                    if current == edge_source:
                        from_id, to_id = edge_source, edge_target
                    elif current == edge_target:
                        from_id, to_id = edge_target, edge_source
                    else:
                        steps = []
                        break
                if from_id != current:
                    steps = []
                    break
                basis = str(
                    edge.get("relationship_basis")
                    or edge.get("basis")
                    or edge.get("type")
                    or "relationship"
                )
                steps.append(
                    {
                        "from_id": from_id,
                        "to_id": to_id,
                        "edge_index": edge_index,
                        "basis": basis,
                        "traversed_direction": traversed_direction,
                    }
                )
                node_ids.append(to_id)
                current = to_id
            if not steps or current != target:
                continue
            out.append(
                {
                    "source_node_id": source,
                    "target_node_id": target,
                    "node_ids": node_ids,
                    "steps": steps,
                }
            )
        return out


def native_graph_query_rows(
    nodes: list[dict[str, Any]],
    edges: list[dict[str, Any]],
    *,
    operation: str,
    start_ids: list[str],
    node_ids: list[str],
    allowed_edge_indexes: list[int],
    max_depth: int,
    max_nodes: int,
    max_edges: int,
    direction: str,
) -> dict[str, Any] | None:
    """Execute structural graph work in Rust and return protocol-level rows.

    Python chooses relationship policy before calling this function. The native
    runtime receives only the normalized nodes and policy-approved edge subset.
    """
    path = _binary_path()
    if not Path(path).is_file():
        return None
    with tempfile.TemporaryDirectory(prefix="sc-library-native-query-") as tmp:
        base = Path(tmp)
        nodes_file, edges_file = _runtime_files(base, nodes, edges, allowed_edge_indexes)
        cmd = [
            path,
            "query",
            "--operation",
            operation,
            "--nodes",
            str(nodes_file),
            "--edges",
            str(edges_file),
            "--starts",
            ",".join(start_ids),
            "--node-ids",
            ",".join(node_ids),
            "--max-depth",
            str(max_depth),
            "--max-nodes",
            str(max_nodes),
            "--max-edges",
            str(max_edges),
            "--direction",
            direction,
        ]
        try:
            completed = subprocess.run(cmd, capture_output=True, text=True, timeout=20, check=True)
        except Exception:
            return None
        lines = [x for x in (completed.stdout or "").splitlines() if x.strip()]
        if not lines:
            return None
        meta = lines[0].split("\t")
        if len(meta) < 4 or meta[0] != "META" or meta[1] != NATIVE_GRAPH_CONTRACT:
            return None
        if meta[3] != operation:
            return None
        result: dict[str, Any] = {
            "runtime_version": meta[2],
            "operation": operation,
            "nodes": [],
            "edges": [],
            "reachable": [],
            "components": [],
            "stats": {},
        }
        components: dict[int, list[str]] = {}
        for line in lines[1:]:
            parts = line.split("\t")
            if not parts:
                continue
            if parts[0] == "NODE" and len(parts) >= 3:
                try:
                    depth = int(parts[2])
                except ValueError:
                    depth = 0
                result["nodes"].append({"id": parts[1], "depth": depth})
            elif parts[0] == "EDGE" and len(parts) >= 2:
                try:
                    result["edges"].append(int(parts[1]))
                except ValueError:
                    continue
            elif parts[0] == "REACH" and len(parts) >= 3:
                try:
                    depth = int(parts[2])
                except ValueError:
                    depth = 0
                result["reachable"].append({"id": parts[1], "depth": depth})
            elif parts[0] == "COMPONENT" and len(parts) >= 3:
                try:
                    idx = int(parts[1])
                except ValueError:
                    continue
                components.setdefault(idx, []).append(parts[2])
            elif parts[0] == "STAT" and len(parts) >= 3:
                try:
                    result["stats"][parts[1]] = int(parts[2])
                except ValueError:
                    result["stats"][parts[1]] = parts[2]
        result["components"] = [
            {"component_index": idx, "node_ids": sorted(ids), "node_count": len(ids)}
            for idx, ids in sorted(components.items())
        ]
        return result
