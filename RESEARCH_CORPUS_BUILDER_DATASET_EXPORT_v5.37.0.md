# Knowledge Library v5.37.0 — Research Corpus Builder & Dataset Export

## Purpose
v5.37.0 turns selected Library records into deterministic research corpora and portable datasets without moving research interpretation or durable scientific truth into the Library.

## Responsibilities
The Knowledge Library owns corpus assembly, selection lineage, source identity, dataset projection, export formatting and row-level provenance. Platform Core remains the durable authority for governed research/evidence objects and any explicit research-package promotion.

## Contracts
- `sc-library-research-corpus/1.0`
- `sc-library-corpus-manifest/1.0`
- `sc-library-corpus-selection/1.0`
- `sc-library-dataset-export/1.0`
- `sc-library-dataset-export-package/1.0`
- `sc-library-dataset-row-provenance/1.0`

## Build flow
1. Normalize source records without scientific interpretation.
2. Apply explicit include/exclude identifiers and descriptive filters.
3. Record a selection decision for every candidate record.
4. Compute deterministic source-snapshot, selection and corpus fingerprints.
5. Project selected records into an explicit dataset field schema.
6. Emit row-level provenance binding every exported row back to the source record and source fingerprint.
7. Export JSON, JSONL, CSV, or a bundle containing all three.
8. Offer an explicit Core handoff candidate only when requested; no automatic promotion occurs.

## Guardrails
- Corpus membership does not imply evidence quality, truth, consensus, support, contradiction or causality.
- Corpus exclusion does not imply falsehood or irrelevance.
- A dataset row is not a governed Platform Core research object.
- Duplicate record IDs are surfaced and deduplicated deterministically; duplicate identity is not inferred from title similarity.
- Date filters operate only on explicit ISO-like date text and do not infer ambiguous dates.
- Unknown dataset fields are rejected rather than silently invented.

## Polyglot continuity
Python remains the research semantics and export orchestration layer. Go v0.1.0 remains the ingestion/job fabric. Rust v0.2.0 remains the native graph-query engine. Platform Core remains the durable governed-object authority.
