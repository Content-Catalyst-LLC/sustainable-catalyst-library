# Sustainable Catalyst Library v5.9.1
## Biomedical Evidence Graph Reliability & Provenance Repair

v5.9.1 hardens the v5.9.0 biomedical evidence graph without changing its research-only evidence boundaries.

### Library release
- WordPress plugin: **5.9.1**
- Library backend: **1.9.0**
- No PostgreSQL migration
- No new credentials

### Reliability repairs
- Canonical node identity contract with explicit namespace and merge basis.
- Exact-identifier consolidation for PMID/NCT/source identifiers; title-only node merging is disabled.
- Duplicate observations are consolidated while retaining multiple provenance records.
- Logical graph edges aggregate source provenance rather than multiplying identical endpoint/type edges.
- Every node and edge exposes a provenance ledger.
- Deterministic node/edge ordering.
- SHA-256 graph-content and provenance fingerprints that exclude volatile retrieval timestamps.
- Reproducibility capsule records normalized query, bounded retrieval parameters, algorithm version, and graph fingerprints.
- Source status and source freshness are reported separately.
- No staleness is inferred when an upstream source does not provide a freshness policy or source-update timestamp.
- Partial upstream failures are contained and surfaced without discarding successful source families.
- Integrity diagnostics report missing provenance, dangling edges, duplicate consolidation, and partial-source failures.

### New backend route
`GET /v1/biomedical-evidence-graph/reproducibility?q=...`

### WordPress
The existing `[sc_biomedical_evidence_graph]` surface now displays graph integrity, duplicate consolidation, provenance completeness, source-family status, and a short graph fingerprint.

### Preserved boundaries
v5.9.1 still does not infer semantic equivalence, causality, pooled effects, comparative effectiveness, formal certainty grades, or clinical recommendations.
