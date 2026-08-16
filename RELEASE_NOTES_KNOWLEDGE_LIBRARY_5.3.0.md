# Sustainable Catalyst Library 5.3.0

## Public Evidence & Claim Navigation

- Adds `[sc_public_evidence_claim_navigation]`.
- Adds the public GET-only `/wp-json/sc-library/v1/public-evidence` facade with index, claim, evidence, publication, and source contexts.
- Reuses canonical v2.7 public Research Claims/Evidence Notes and v4.3.37 explicit Publication ↔ Research Graph links.
- Preserves supports, qualifies, contradicts, contextualizes, illustrates, and unresolved relations without converting them into truth scores.
- Exposes bounded public evidence excerpts and provenance while excluding private analysis/context, review notes, relation notes, Evidence Matrix bodies, notebooks, projects, credentials, memberships, and Workspace state.
- Adds public-evidence handoffs to eligible v4.9 publication and research-source payloads.
- Adds `/public-evidence` to the explicit safe public GET cache allowlist and reuses explicit-origin CORS with credentials disabled.
- Adds v5.3.0 production-readiness certification for the public evidence contract, assets, module registration, and cache/private-data boundaries.
- Performs no automatic claim creation, evidence promotion, status/confidence mutation, publication, federation acceptance, or Workspace write.
