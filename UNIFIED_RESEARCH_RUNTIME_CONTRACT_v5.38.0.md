# Knowledge Library v5.38.0 — Unified Research Runtime Contract

## Release pairing

- WordPress plugin: **5.38.0**
- Python research backend: **2.49.0**
- Go ingestion runtime: **0.1.0**
- Rust graph runtime: **0.2.0**

## Purpose

v5.38.0 gives the Knowledge Library one explicit contract for discovering, routing, and invoking research workloads across its Python, Go, and Rust runtimes. It does not erase runtime boundaries. Instead, it makes those boundaries machine-readable and reviewable.

Python remains the public API, research-semantics, provenance-policy, and routing authority. Go remains the durable ingestion/job-fabric execution layer. Rust remains the structural graph-compute layer. Platform Core remains the durable authority for governed research objects.

## Contracts

- `sc-library-research-runtime-contract/1.0`
- `sc-library-runtime-descriptor/1.0`
- `sc-library-runtime-routing-decision/1.0`
- `sc-library-runtime-execution-envelope/1.0`

## Governed workload classes

| Workload | Primary runtime | Compatible runtime(s) | Execution mode |
|---|---|---|---|
| Research corpus build | Python | Python | synchronous |
| Dataset export | Python | Python | synchronous |
| Ingestion job submit | Go | Go | asynchronous dispatch |
| Native graph query | Rust | Rust, Python fallback | synchronous |

The contract deliberately starts with bounded, already-existing capabilities. It is not a generic arbitrary-code execution API.

## Runtime discovery

`GET /v1/runtime/research/status` returns three runtime descriptors with runtime IDs, engine/version, availability, transport, capabilities, execution modes, and authority boundaries. The contract fingerprint excludes transient availability/queue state so health changes do not redefine the contract itself.

## Routing

`POST /v1/runtime/research/resolve` accepts a workload, runtime preference (`auto`, `python`, `go`, or `rust`), and `allow_fallback` policy. Incompatible explicit runtime requests are rejected. Fallback is surfaced in the routing object and is never silent.

For v5.38.0, the only compatible fallback is native graph work from Rust to the deterministic Python graph implementation.

## Unified execution envelope

`POST /v1/runtime/research/execute` is a signed write endpoint. It returns one envelope containing:

- execution identity;
- request fingerprint;
- selected runtime and routing fingerprint;
- execution state (`completed` or `accepted`);
- result payload and result fingerprint;
- explicit authority declarations;
- research guardrails.

Go job submission returns `accepted` because the actual ingestion work is asynchronous. Python and Rust workloads return `completed` when the invoked operation completes.

## Guardrails

- Runtime selection is not an evidence-quality judgment.
- Successful execution does not establish truth or scientific quality.
- Faster execution does not mean better research.
- Cross-runtime equivalence is not asserted in v5.38.0.
- Runtime execution does not automatically create/promote Platform Core objects.
- Python retains research semantics and provenance policy.
- Platform Core remains durable governance authority.

Cross-runtime reproducibility, richer execution lineage, environment capture, and equivalence verification are intentionally deferred to v5.39.0.
