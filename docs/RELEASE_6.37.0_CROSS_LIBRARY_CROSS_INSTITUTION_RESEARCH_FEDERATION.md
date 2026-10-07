# Library v6.37.0 — Cross-Library / Cross-Institution Research Federation

Library v6.37.0 adds a research-facing federation layer above the existing institutional repository and global knowledge federation foundations.

## Scope

- Institution and library capability manifests
- Explicit cross-institution research query plans
- Authority-preserving ingestion of declared source responses
- Exact source-payload fingerprints and lineage
- Exact DOI, content-hash, and normalized-URL identity candidates
- Federated provenance auditing
- Per-source failure containment
- Deterministic federation export

## Authority model

The federation layer is orchestration and composition only. Each institution remains authoritative for its own records, schemas, policies, access rules, and source identity. The federation does not automatically fetch external data, import records, merge or deduplicate records, normalize semantics, adjudicate truth, persist authoritative research state, or promote claims/evidence to Platform Core.

## Routes

- Web: `/research/federation`
- API: `/api/library/v1/research-federation`

## Generations

- Library 6.37.0
- Backend 3.37.0
- Web 2.37.0
- SDK 1.37.0
- API v1 stable

## Next

Library v6.38.0 — Research Reproducibility & Audit Console
