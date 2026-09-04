# Institutional Research Network II — v5.10.0

## Purpose

Institutional Research Network II provides one governed metadata layer over heterogeneous university repositories without pretending that the repositories share one license, one access policy, one object model, or one institutional relationship with Sustainable Catalyst.

## Connector families

### Dataverse

The Dataverse adapter is used for:

- Johns Hopkins Research Data Repository
- Harvard Dataverse

The adapter normalizes datasets, persistent identifiers, authors, subjects, descriptions, dates, citations, file metadata, license metadata, and repository provenance.

### DSpace REST

The DSpace REST adapter is used for DSpace@MIT. It reads the DSpace discovery REST surface and normalizes public item metadata.

### OAI-PMH

Research Repository UCD is connected through its public OAI-PMH interface. OAI-PMH is a harvesting protocol rather than an arbitrary full-text search API, so v5.10.0 exposes this limitation rather than masking it. The connector performs a bounded metadata harvest and local metadata filter.

## Common institutional research object

Normalized records include, when available:

- source key
- institution
- repository
- source family
- record type
- title
- persistent identifier
- DOI
- authors
- description
- subjects / keywords
- publication and update dates
- source URL
- citation
- license observation
- access state
- provenance

## Cross-source identity

Identity priority:

1. normalized exact DOI
2. source key + persistent identifier
3. source key + source URL
4. source-scoped observation identity

A shared title is never sufficient to establish that two records are the same object.

## Network graph

Node classes:

- research question
- institution
- repository
- research record
- license

Edge classes:

- retrieved-for-question
- held-by-repository
- repository-belongs-to-institution
- licensed-under

The graph does not infer author identity across repositories.

## Reproducibility

Each search result includes a deterministic SHA-256 content fingerprint. Volatile retrieval timestamps are excluded from the fingerprint. Graph responses include a deterministic graph fingerprint.

## Failure containment

Each source reports its own state. A timeout or upstream error at one institution is recorded as a source-local failure while successful source results remain available.

## Cross-product handoffs

The response declares bounded handoff contracts for:

- Source Bundles
- Research Projects
- Research Librarian
- Lab

All writes remain explicit user actions. Dataset metadata does not imply that underlying files are accessible, licensed for reuse, or appropriate for analysis.
