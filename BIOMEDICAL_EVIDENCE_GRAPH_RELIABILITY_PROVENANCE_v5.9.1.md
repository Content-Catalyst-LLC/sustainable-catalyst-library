# Biomedical Evidence Graph Reliability & Provenance — v5.9.1

## Identity policy
Graph consolidation requires an exact canonical identifier or an explicitly source-scoped structural label. Titles alone never trigger a merge.

Examples:
- publication: PMID when present; DOI may be used when explicitly supplied
- clinical trial: NCT identifier
- terminology concept: source key + source identifier
- regulatory record: FDA source family + source identifier
- registry structural nodes: ClinicalTrials.gov-scoped condition/intervention labels; trial-scoped outcomes

Every node exposes an `identity` object with canonical key, namespace, merge basis, observation count, and `title_only_merge_used=false`.

## Provenance policy
Every graph edge contains `provenance_records`. When the same logical source/type/target relationship is observed more than once, the edge is consolidated and provenance observations are retained rather than overwritten.

The response also exposes a `provenance_ledger` keyed by node and edge IDs.

## Reproducibility
The graph is deterministically ordered by stable IDs. A SHA-256 content fingerprint is generated from the normalized graph after volatile retrieval timestamps are removed. A separate provenance fingerprint covers the provenance ledger under the same timestamp rule.

A matching fingerprint means the same bounded normalized graph content was produced under the same algorithm version. It does not prove that upstream sources outside the retrieved window were unchanged.

## Freshness
`source_freshness` reports available retrieval and source-update timestamps. The system does not label a source stale merely because a source-update timestamp is absent.

## Partial-source failures
PubMed/evidence-body, medical terminology, and FDA regulatory retrieval remain independently contained. Successful source families remain usable when another source family fails. `source_status`, `errors`, and `partial_source_failure_count` make incomplete coverage explicit.

## Integrity diagnostics
`reliability` reports:
- duplicate observation consolidation count
- nodes missing provenance
- edges missing provenance
- dangling edges
- partial source failures
- deterministic ordering state
- title-only merge state

## Evidence boundary
This release improves graph reliability; it does not add medical inference. Semantic equivalence, causality, treatment ranking, pooled effect estimation, formal GRADE certainty, and clinical recommendations remain out of scope.
