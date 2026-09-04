# Sustainable Catalyst Library v5.10.0 — Institutional Research Network II

## Summary

v5.10.0 generalizes the Library's provenance-first research-intelligence architecture beyond the biomedical evidence graph. It introduces a common institutional research object model and a bounded federated network across:

- DSpace@MIT
- Harvard Dataverse
- Johns Hopkins Research Data Repository
- Research Repository UCD

The Library backend advances from v1.9.0 to v2.0.0.

## New backend surfaces

- `GET /v1/institutional-research-network`
- `GET /v1/institutional-research-network/search`
- `GET /v1/institutional-research-network/graph`

Existing `/v1/institutional-sources` routes remain available.

## Identity and provenance policy

- Exact DOI identity may consolidate duplicate observations across repositories.
- Source-local persistent identifiers remain source-scoped.
- Title-only identity merging is disabled.
- Cross-source author identity inference is disabled.
- Every record preserves source provenance.
- Duplicate observations aggregate provenance instead of overwriting it.
- Retrieval timestamps are excluded from deterministic content fingerprints.
- One failed repository does not invalidate the bounded result from other repositories.

## Access and licensing policy

Repository discovery is not entitlement. Metadata visibility is not permission to reuse the underlying content.

Per-record license state is preserved when supplied by the source. Unknown or ambiguous rights remain review-required.

Source inclusion does not imply affiliation, partnership, endorsement, or institutional approval.

## WordPress

New shortcode:

`[sc_institutional_research_network]`

The browser continues to call WordPress only. WordPress proxies bounded read requests to the Library backend.

## Migration

No PostgreSQL migration.

No new API credentials are required for the four connectors in this release.

## Preserved systems

v5.9.1 biomedical graph reliability, v5.9.0 evidence synthesis, v5.8.x medical evidence systems, Library Explorer, Publications, Research Network Console, and existing institutional-source behavior remain intact.
