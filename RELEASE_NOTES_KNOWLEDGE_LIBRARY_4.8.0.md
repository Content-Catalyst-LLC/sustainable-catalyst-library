# Sustainable Catalyst Library v4.8.0 — Global Research Federation

v4.8.0 introduces governed research federation across Sustainable Catalyst-compatible nodes while preserving the Library's private-research and entitlement boundaries.

## What changed

- Added `[sc_global_research_federation]`.
- Added a public safe federation-node compatibility document.
- Added public, checksummed references-only federation manifests.
- Added private Team Library federation governance for owners and stewards.
- Added explicit publish/draft/revoke states for outgoing metadata manifests.
- Reused the v3.9 federation peer/trust/quarantine engine instead of creating another peer/import system.
- Added compatibility and SHA-256 validation for inbound v4.8 manifests.
- Added two-stage inbound handling: admin metadata approval first, explicit Team Library owner/steward acceptance second.
- Accepted metadata is contributed through the existing Team Library reference model with preserved remote provenance and conservative duplicate skipping.
- Added Research Federation to the Unified Personal Research Environment navigation/count composition.
- Added the v4.8 module, private governance route, and assets to production-readiness certification.

## Privacy and governance

Global Research Federation never automatically exports personal/private research. My Library, private Research Projects, Research Room membership, notebook/matrix bodies, private source files, credentials, and Workspace state are excluded.

Federation peer trust is transport/review governance, not a truth score. Remote node identity and institutional context do not prove membership, subscription entitlement, legal authority, or resource access.

No automatic remote polling, automatic metadata acceptance, evidence promotion, private publication, or Workspace write is introduced.
