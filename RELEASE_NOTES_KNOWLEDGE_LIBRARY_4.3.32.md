# Sustainable Catalyst Library v4.3.32 — Evidence Matrix & Claim Intelligence

## Added

- Private account-owned Evidence Matrices attachable to canonical v4.3.30 Research Projects and Source Bundles.
- Working claims with stable UUID/URN identity, explicit status, user-declared confidence, scope, assumptions, limitations, counterclaim, tags, and ordering.
- Explicit evidence relationships: supports, qualifies, contradicts, contextualizes, and unresolved/follow-up.
- Evidence selection from v4.3.31 Reading Notes/Annotations, canonical v2.7 Evidence Notes, v4.3.30 project references, and external sources.
- Deterministic Claim Intelligence diagnostics for relation totals, source diversity, unresolved references, quote/locator checks, and visible coverage gaps.
- Authenticated `/wp-json/sc-library/v1/evidence-matrices` REST surface and checksummed matrix manifests.
- `[sc_evidence_matrix_workspace]` on the canonical Research Library page.

## Preserved boundaries

- v2.7 Evidence Notes and Research Claims remain canonical evidence records.
- v4.3.31 Reading Notes and Source Annotations remain private account records and are not automatically promoted to evidence.
- Matrix source links are references; private source binaries are not copied.
- Diagnostics are descriptive only and never infer truth, generate claims, change claim status, or change confidence.
- No automatic publication or Workspace write occurs.
