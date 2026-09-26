# Knowledge Library v5.27.0 — Source Identity, Deduplication & Entity Resolution

## Release pairing

- WordPress plugin: **5.27.0**
- Python backend: **2.38.0**
- Predecessor: Knowledge Library 5.26.0 / backend 2.37.0

## Purpose

v5.27.0 gives the Knowledge Library a deterministic, provenance-safe identity layer for publications and source-linked entities. It reduces duplicate research representations without erasing source history and makes identity structure available to Research Graph Query and the Knowledge Landscape.

The release deliberately separates **identity observations**, **version families**, and **review candidates**. A match signal is never silently promoted into an identity determination.

## Source identity model

The backend normalizes and compares the identifiers already supplied by Library ingestion/connectors. Strong source identity signals include:

1. exact normalized DOI and supported persistent identifiers;
2. exact content hash;
3. exact normalized canonical URL after removal of common tracking parameters;
4. source-scoped persistent/external/accession identifiers.

When two records share a strong work identifier but have different content hashes, the system represents them as a **same-work version family** rather than choosing a supposedly superior version.

The `preferred_display_record_id` in a cluster is a deterministic display choice based on metadata completeness. It is not a truth, authority, quality, recency, or citation judgment.

## Duplicate candidates

Exact normalized `title + first author + publication year` creates a **review-required bibliographic duplicate candidate** only when the records have not already been joined by strong identity evidence.

Candidate matches:

- do not merge records;
- do not delete records;
- do not rewrite assignments;
- do not establish source identity;
- remain distinguishable from exact-identifier clusters in the graph.

## Entity resolution

v5.27.0 adds identifier-grounded cross-source resolution for:

- authors by exact **ORCID**;
- institutions by exact **ROR**;
- datasets by exact **DOI** or normalized URL.

Exact normalized names without identifiers remain review candidates and never trigger cross-source identity merging.

## Research graph integration

New graph node kinds:

- `source-identity`
- `author-identity`
- `institution-identity`
- `dataset-identity`

New relationship bases:

- `member-of-source-identity`
- `authored-by-identity`
- `affiliated-institution-identity`
- `references-dataset-identity`
- `duplicate-candidate`

`duplicate-candidate` is an opt-in analytical/review relationship for pathfinding. Strong identifier relationships are deterministic structural identity links. None of these relationships asserts truth, causality, agreement, or evidentiary quality.

## API

Backend 2.38.0 adds:

- `POST /v1/source-identity/analyze` — stateless identity analysis for supplied records;
- `POST /v1/publication-knowledge-maps/source-identity` — source identity analysis for the canonical publication corpus;
- source identity data embedded in `POST /v1/publication-knowledge-maps/corpus`.

WordPress adds:

- `POST /wp-json/sc-library/v1/backend/publication-source-identity`.

## Knowledge Landscape

The new **Source Identity** view displays publication records alongside strong source clusters and resolved author/institution/dataset identities. Duplicate candidates are visually distinct from confirmed strong-identifier clusters and remain review-required.

## Preservation boundaries

v5.27.0 performs no automatic source merge, record deletion, assignment rewrite, title-only identity merge, author-name-only cross-source merge, institution-name-only cross-source merge, or dataset-title-only cross-source merge.

The Knowledge Library owns source/bibliographic identity diagnostics. **Platform Core remains the durable authority for governed research objects, provenance/lineage, claims/findings, synthesis, uncertainty, and cross-product reasoning.**
