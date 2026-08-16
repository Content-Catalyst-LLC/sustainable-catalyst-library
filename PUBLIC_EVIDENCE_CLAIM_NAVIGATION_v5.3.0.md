# Sustainable Catalyst Library v5.3.0 — Public Evidence & Claim Navigation

## Purpose

v5.3.0 turns the Library's existing public claim/evidence records into a navigable public research surface without creating a second claim database, copying private Evidence Matrix material, or inferring evidentiary relationships from text.

## Canonical authorities reused

- `SC_Library_Evidence_Claim_Linking` v2.7 remains authoritative for Research Claims, Evidence Notes, public visibility, evidence-source links, and claim/evidence relations.
- `SC_Library_Publications_Research_Graph` v4.3.37 remains authoritative for explicit Publication → public Claim and public Source links.
- `SC_Library_Citation_Source_Manager` remains authoritative for public Research Source metadata and citations.
- v4.9 public API/CORS and v5 public-cache infrastructure remain the integration boundary.

No new claim, evidence, matrix, project, source, graph, or user-meta store is introduced.

## Public REST surface

Base: `/wp-json/sc-library/v1/public-evidence`

- `GET /public-evidence`
- `GET /public-evidence/index`
- `GET /public-evidence/claim/{id}`
- `GET /public-evidence/evidence/{id}`
- `GET /public-evidence/publication/{id}`
- `GET /public-evidence/source/{id}`

The surface is read-only and public. Only records that already satisfy the canonical v2.7 public-visibility rules are returned.

## Relation semantics

The facade preserves the canonical relation vocabulary:

- supports
- qualifies
- contradicts
- contextualizes
- illustrates
- unresolved

These are explicit research relationships. They are not truth, certainty, consensus, authority, popularity, or access-entitlement scores. v5.3.0 never changes a claim status or confidence field.

## Evidence minimization

A public evidence response includes a bounded excerpt, evidence type, public source/citation, public document link where applicable, locator, verification flags, public review status, declared confidence, modified timestamp, and navigation links.

It does **not** expose private Evidence Matrix bodies, Reading Notebook bodies, private project context, private review notes, relation notes, private analysis/context fields, credentials, memberships, or Workspace state.

## Publication and source navigation

Publication contexts are derived only from claims explicitly linked through Publication ↔ Research Graph. Source contexts are derived only from canonical public Evidence Notes linked to that public Research Source.

The v4.9 public object payload filter adds bounded `public_evidence` navigation metadata for publications and research sources when qualifying public evidence exists.

## Production boundaries

- public GET only
- explicit-origin CORS only
- credentials disabled
- `/sc-library/v1/public-evidence` explicitly allowlisted in the bounded v5 public cache
- no broad `/sc-library/v1` cache rule
- no automatic claim generation
- no automatic evidence promotion
- no automatic claim-status/confidence mutation
- no automatic publication
- no automatic federation acceptance
- no automatic Workspace write
