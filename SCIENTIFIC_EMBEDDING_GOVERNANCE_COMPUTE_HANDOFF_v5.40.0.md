# Scientific Embedding Governance & Compute Handoff — v5.40.0

## Architecture

The v5.40 embedding path is:

`Library record snapshot -> deterministic embedding input -> EmbeddingSpecification -> compute route -> vector -> EmbeddingRepresentation provenance -> Library operational index -> semantic retrieval / visualization`

Compute can be performed by the Library backend or Workspace. The representation identity is bound to source content, exact embedding input and specification fingerprint; changing execution location alone does not change representation identity.

## EmbeddingSpecification

The specification fingerprint covers:
- provider
- model
- dimensions
- deterministic Library input profile
- maximum input size
- source fields used by the input profile
- L2 normalization
- cosine similarity metric

Provider credentials are never serialized into the specification or handoff.

## EmbeddingRepresentation

Every newly computed vector records:
- Library record ID
- source content SHA-256
- deterministic embedding-input SHA-256
- embedding specification fingerprint
- provider/model/dimensions
- execution target
- execution ID
- representation ID
- research-authority boundary and guardrails

## Workspace handoff

A Workspace handoff is a durable, idempotent compute request. It contains the exact bounded embedding input and public model specification but no provider secret. Workspace is defined as a compute executor, not an evidence or semantic authority.

The return path validates vector dimensions, L2-normalizes the returned vector again, writes the operational vector and immutable provenance, then closes both the Library embedding job and handoff.

## Backfill

`/v1/admin/embeddings/backfill` is dry-run by default. Candidates are selected when:
- no representation exists;
- the stored representation points to stale source content; or
- the stored representation lacks/mismatches the current specification fingerprint.

This means upgrading does not automatically spend embedding quota or rewrite existing vectors. Operators can review the plan first and then queue it explicitly.

## Guardrails

The release permanently preserves these distinctions:
- embedding != evidence
- semantic similarity != truth
- semantic similarity != causality
- model execution success != research quality
- Workspace compute != Platform Core authority
- operational vector storage != governed cross-product representation authority
- no automatic evidence/claim/truth/Core promotion
