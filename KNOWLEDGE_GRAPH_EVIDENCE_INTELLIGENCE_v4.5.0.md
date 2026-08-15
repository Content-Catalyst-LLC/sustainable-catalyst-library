# Sustainable Catalyst Library v4.5.0 — Knowledge Graph & Evidence Intelligence

## Purpose

v4.5.0 adds a private, account-scoped graph projection above the canonical research environment. It is deliberately not a second knowledge-graph database and does not migrate records from the 4.3/4.4 research stores.

The projection reads one owned Research Project and composes only relationships already declared in canonical records:

- Research Project references
- Source Bundle membership
- project-attached Reading Notebooks
- Reading Notes and Source Annotations
- project-attached Evidence Matrices
- Matrix Claims and explicit claim/evidence links
- referenced evidence/source identities
- project-linked Open Learning II routes

## Schemas

- `sc-library-knowledge-graph-evidence-intelligence/1.0`
- `sc-library-private-research-graph/1.0`
- `sc-library-project-evidence-intelligence/1.0`

## REST

Authenticated current-user endpoints:

- `GET /wp-json/sc-library/v1/knowledge-graph-evidence/catalog`
- `GET /wp-json/sc-library/v1/knowledge-graph-evidence?project_id={id}`

The selected project must belong to the current WordPress user.

## Shortcode

`[sc_knowledge_graph_evidence_intelligence title="Knowledge Graph & Evidence Intelligence"]`

## Evidence Intelligence

Evidence Intelligence aggregates the deterministic diagnostics already emitted by v4.3.32 Evidence Matrix & Claim Intelligence. It reports descriptive totals for:

- claims with evidence
- claims with counterevidence
- support / qualification / contradiction / context / unresolved relationships
- unique evidence sources
- unresolved references
- quote/locator verification coverage
- matrix-recorded gap indicators
- claim-pattern counts such as support-only, mixed-record, contradiction-heavy, context/unresolved-only, and no-evidence

These diagnostics are **not conclusions**. v4.5.0 does not score truth, alter user-declared confidence, change claim status, infer missing relationships, generate claims, or promote notebook content into evidence.

## Public/private boundary

The existing public `SC_Library_Knowledge_Graph` and v4.3.37 Publications ↔ Research Graph remain separate public projections. v4.5.0 does not publish private project graph nodes or edges into either public graph.

Private graph content is not forwarded to optional Research Librarian remote synthesis.

## Storage contract

v4.5.0 introduces no new private graph record store. The graph is rebuilt from canonical records on request. Existing storage keys, post types, UUIDs, URNs, ownership, provenance, and permissions remain authoritative.

## Safety contract

- explicit relationships only
- no machine-inferred relationships
- no automatic entity resolution
- no truth scoring
- no automatic confidence scoring
- no automatic claim status changes
- no automatic publication
- no automatic Workspace writes
- no automatic project/notebook/evidence mutation
