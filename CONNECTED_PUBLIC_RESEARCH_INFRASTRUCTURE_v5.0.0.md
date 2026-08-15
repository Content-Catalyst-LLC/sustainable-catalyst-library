# Connected Public Research Infrastructure — v5.0.0

## Purpose

v5.0.0 is the public-infrastructure capstone for the Sustainable Catalyst Library. It composes existing public authorities rather than creating another record or graph store.

## Canonical authorities reused

- v4.9.0 Library API, Embeds & Interoperability for normalized public objects.
- v4.3.37 Publications ↔ Research Graph for editor-declared publication context.
- v3.3 Knowledge Pathways for published pathway structure and public steps.
- v3.2 Topics, Concepts & Relationships for explicitly public knowledge edges.
- v4.8.0 Global Research Federation for explicitly published federation manifests.

## Public endpoints

`/wp-json/sc-library/v1/connected-public-research` is GET/read-only and exposes an index, one-hop object context, network projection, checksummed context manifest, and published federation-manifest summary.

## Relationship policy

Relationships are included only when they are already explicit in a canonical public subsystem. v5.0.0 performs no semantic similarity inference, private-project graph traversal, AI edge generation, or automatic relationship creation. Networks are capped at 120 connections and one hop.

## Privacy and governance

Private Projects, My Library, notebook/matrix bodies, Research Room membership, Team Library membership, private federation governance, credentials, and Workspace state are excluded. Federation trust is not a truth score, and institutional context is not access entitlement.

## Integrity and interoperability

Each public context has a SHA-256 digest. A separate context manifest records canonical identity, connection counts, the context digest, references-only status, and the absence of credentials/private content. Public singular records emit an alternate discovery link to the v5 context endpoint. CORS reuses the v4.9 explicit-origin allowlist and never permits credentials.

## Non-goals

No new graph database, automatic publication, federation acceptance, evidence promotion, private-to-public promotion, background crawling, or Workspace write is introduced.
