# Knowledge Library v5.15.0 — Entity, Finding & Claim Extraction

## Purpose

v5.15.0 adds publication-level extraction candidates without duplicating Platform Core governance. The Library identifies candidate entities, findings, and claims from source metadata and source text; every candidate remains explicitly non-governed until human review.

## Library responsibilities

- ingest and parse publications
- preserve source chunks and identifiers
- extract candidate entities/findings/claims
- attach source locator, chunk ordinal, character span, source hash, extraction method, and confidence
- maintain review state
- supersede source-bound candidates when the underlying publication changes

## Platform Core responsibilities

Accepted finding/claim candidates may be queued through the existing durable outbox to Platform Core v3.3+ Finding, Claim & Evidence Intelligence. Core remains authoritative for governed findings, claims, evidence links, derivations, provenance, contradiction handling, synthesis, visual reasoning, and cross-product exchange.

The bridge adds only two allowlisted operations:

- `research-finding.create` → `POST /v1/research/intelligence/projects/{project_id}/findings`
- `research-claim.create` → `POST /v1/research/intelligence/projects/{project_id}/claims`

No caller can supply an arbitrary Core URL.

## Candidate states

`pending` → `accepted` / `rejected` → optionally `superseded`

Only `accepted` finding/claim candidates can be queued for Core promotion. Entity candidates remain Library-owned in this release.

## Extraction behavior

The release uses deterministic metadata and rule-based extraction. It does not claim semantic truth, causal validity, scientific validity, consensus, or evidence strength. Empirical result cues are classified as finding candidates before claim cues to avoid duplicate classification of the same sentence.

## Public contracts

- `GET /v1/research-extraction/readiness`

## Signed administrative contracts

- `POST /v1/research-extraction/extract`
- `GET /v1/research-extraction/candidates`
- `POST /v1/research-extraction/candidates/{candidate_id}/review`
- `POST /v1/research-extraction/core-handoff`
