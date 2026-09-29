# Changelog

This file summarizes the current Knowledge Library release line. Full historical release artifacts remain available through Git tags.

## v5.49.0 — Durable Research Job Queue & Execution State

- PostgreSQL-authoritative research jobs, attempts, leases, retries, progress, cancellation, and execution-event lineage.
- Redis Streams for rebuildable dispatch/wake-up coordination.
- Deterministic idempotent job creation and expired-lease recovery.
- Specialized worker pools intentionally deferred to v5.50.0.

## v5.48.0 — Cross-Language Entity, Name & Historical Toponym Resolution

- Multilingual entity authorities and language/script-aware name forms.
- Declared transliterations, endonyms/exonyms, and historical toponym validity windows.
- Ambiguity-preserving candidate generation and explicit signed adjudication.

## v5.47.0 — Linguistic Corpus Objects, Concordance & KWIC

- Reproducible corpus/document/token objects with source-representation lineage.
- Unicode tokenization, phrase concordance, KWIC windows, and frequency analysis.

## v5.46.0 — OCR, HTR & Transcription Lineage

- Source-media assets and OCR/HTR/transcription derivation runs.
- Engine/model/parameter provenance, page geometry or time spans, confidence measurements, and review state.

## v5.45.0 — Original-Language Corpus Ingestion & Preservation

- Canonical original-language capture with raw-byte and decoded-text fingerprints.
- Derived Unicode-normalized representations without overwriting the original source.

## v5.44.0 — Global Source Federation Registry & Connector Contracts

- Canonical source, institution, collection, and connector registry contracts.
- Original-language preservation and explicit source/connector provenance boundaries.

## v5.43.0 — Publication Embedding Maps & Semantic Knowledge Landscape

- Deterministic publication embedding maps and bounded semantic neighborhoods.
- Same-specification representation guardrails and stored-vector rendering.

## v5.42.0 — Neural Reranking & Retrieval Evaluation

- Second-stage neural reranking with baseline-rank preservation.
- Retrieval evaluation and explicit score/provenance separation from evidence or truth.

## v5.41.0 — Semantic Similarity & Representation Search

- Governed semantic similarity, representation search, and stored-representation lookup.

## v5.40.0 / v5.40.0.1 — Scientific Embedding Governance & Compute Handoff

- Governed embedding specifications and representation identity.
- Workspace compute handoff and timestamp-safe embedding backfill repair.

## Earlier releases

Use repository tags to inspect the exact historical tree, release notes, deployment guides, validation output, and implementation for earlier releases.
