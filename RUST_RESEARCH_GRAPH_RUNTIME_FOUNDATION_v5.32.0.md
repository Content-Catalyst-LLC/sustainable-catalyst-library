# Knowledge Library v5.32.0 — Rust Research Graph Runtime Foundation

## Purpose
Introduce a native Rust graph-runtime layer underneath the existing Python research backend without transferring research semantics, evidence interpretation, provenance policy, or Platform Core authority into native code.

## Architecture
- Python remains the public API and research-semantics layer.
- Rust receives a normalized, already-policy-filtered node/edge traversal workload.
- Rust v0.1.0 implements deterministic bounded path traversal only.
- Python reconstructs the public evidence-path response, provenance snapshots, reviewed/analytical classifications, and interpretation guardrails.
- If the Rust executable is missing or returns an incompatible contract, `runtime=auto` falls back to Python.
- `runtime=rust` also fails safely to Python and reports `python-fallback` rather than changing the public response contract.

## Runtime contract
`sc-library-native-graph-runtime/1.0`

## Integrity boundaries
- Native graph connectivity does not imply truth, causality, consensus, support, contradiction, novelty, or evidence quality.
- Allowed relationship bases are selected by Python before the native engine receives edges.
- Analytical edges remain opt-in.
- Methodology and identity relationships excluded from default evidence paths remain excluded before Rust execution.
- Platform Core remains durable authority for governed research objects and synthesis.

## Scope
v5.32 is the runtime foundation. Large-scale native graph query, indexing, structural analytics, incremental graph maintenance, and additional algorithms remain future work for v5.35 and later.
