# Library v6.38.0 — Research Reproducibility & Audit Console

Library v6.38.0 adds a research-facing reproducibility and audit console over the existing project, package, lineage, review, exchange, federation, execution-lineage, and reproducibility foundations.

## Scope

- Authority-preserving reproducibility audit manifests
- Deterministic fingerprints for declared research components
- Supplied-payload and supplied-artifact hash verification
- Reproducibility observation checklists for provenance, dependencies, parameters, environment, runtime, executions, artifacts, and review lineage
- Dependency / provenance / execution lineage auditing without inferred relationships
- Baseline-versus-current drift auditing
- Deterministic audit export

## Authority model

The audit console is composition and inspection only. It does not fetch missing artifacts, download external sources, install dependencies, execute or re-execute code, recreate environments, mutate research objects, persist audit state, certify reproducibility, adjudicate scientific validity, infer truth, or promote claims/evidence to Platform Core.

A complete observation set means only that the declared audit fields were present. It does not mean the research has been reproduced independently.

## Routes

- Web: `/research/audit`
- API: `/api/library/v1/research-audit`

## Generations

- Library 6.38.0
- Backend 3.38.0
- Web 2.38.0
- SDK 1.38.0
- API v1 stable

## Next

Library v6.39.0 — Library 7 Production Consolidation & Certification
