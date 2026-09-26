# Knowledge Library v5.35.0 — Rust Evidence Graph Acceleration & Native Query Engine

WordPress: **5.35.0**  
Python backend: **2.46.0**  
Rust graph runtime: **0.2.0**

## Purpose
v5.35 expands the v5.32 native graph foundation from bounded evidence pathfinding into a reusable structural graph-query engine. Python remains the research-semantics and policy layer; Rust receives only normalized nodes plus the relationship edges Python has approved for the requested query.

## Native operations
- filtered neighborhood expansion
- bounded reachability
- connected-component analysis
- induced subgraph extraction
- structural graph statistics
- existing bounded multi-path evidence traversal

## Contracts
- `sc-library-native-graph-runtime/1.0`
- `sc-library-native-graph-query/1.0`

## Policy boundary
Structural graph computation is not evidentiary interpretation. Connectivity does not establish support, causality, consensus, identity, quality, importance, or truth. Analytical relationships such as similarity, co-occurrence, and duplicate-candidate edges remain opt-in. Platform Core remains the durable authority for governed research objects.

## Runtime selection
`runtime=auto` prefers Rust and falls back to Python. `runtime=rust` attempts Rust and records `python-fallback` if the native runtime is unavailable. `runtime=python` executes the same public structural query contract without native acceleration.

## WordPress
Shortcodes:
- `[sc_library_native_graph_runtime]`
- `[sc_library_native_graph_query]`

REST proxy:
- `/backend/publication-native-graph-query`
