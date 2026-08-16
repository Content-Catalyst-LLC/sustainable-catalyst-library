# Research Identity, Authority & Persistent Identifier Network — v5.2.0

## Purpose

v5.2.0 adds a deterministic authority-resolution layer over identifiers already declared by canonical public Sustainable Catalyst records and explicitly published federation metadata. It is not a replacement identity registry.

## Supported schemes

- DOI
- ORCID
- ROR
- ISBN-10 / ISBN-13
- ISSN
- Wikidata Q identifiers
- PMID

## Canonical authorities reused

- v4.9 public Library object profiles and normalized public-object API
- Citation Studio / Research Source DOI, ISBN, PMID, standard-number and author ORCID fields
- v4.3.34 metadata quality and entity-resolution non-destructive governance
- v3.2 Named Entity and Concept canonical/external URI fields
- v4.8 explicitly published federation manifests

## Resolution contract

Resolution is bounded and local-first. It performs deterministic normalization and local syntax/checksum validation, scans only public canonical records and published federation metadata already present on the node, and returns every explicit match with provenance. More than one match is reported as ambiguity.

No external registry request is performed during resolution. A syntactically valid DOI/ORCID/ROR/etc. is not claimed to be live, current, owned by a particular person or institution, or authoritative beyond the explicit metadata declaration.

## Non-actions

- no parallel identity store
- no automatic entity or record merge
- no automatic identifier assignment
- no authorship or affiliation assertion
- no truth, prestige or quality scoring
- no access-entitlement inference
- no private-research exposure
- no automatic publication or federation acceptance
- no Workspace write

## REST

- `GET /wp-json/sc-library/v1/research-identity`
- `GET /research-identity/schemes`
- `GET /research-identity/resolve?scheme=doi&value=...`
- `GET /research-identity/record/{type}/{id}`
- `GET /research-identity/network/{scheme}/{value}`

## Public object interoperability

The v4.9 normalized public-object payload is extended through a filter with bounded `persistent_identifiers`, an `identity_url`, and an explicit authority boundary. Raw post meta is never exposed.
