# Release Notes — Sustainable Catalyst Library 5.45.0

## Added
- Original-language source capture with exact raw-byte and decoded-text preservation.
- BCP 47 language, ISO 15924 script, language-variant and orthography-variant identity.
- Deterministic capture, representation and transformation IDs.
- Canonical `original` representation and separately stored Unicode-normalized derivatives.
- SHA-256 preservation fingerprints for raw payloads and decoded text.
- Signed capture-ingestion and capture-retrieval backend endpoints.
- Public readiness, validation and non-persisting package endpoints.
- Research Corpus Builder fields for original-language lineage.
- WordPress status shortcode: `[sc_original_language_corpus_status]`.

## Preserved
- v5.44 Global Source Federation Registry & Connector Contracts.
- v5.43 Publication Embedding Maps & Semantic Knowledge Landscape.
- v5.42 Neural Reranking & Retrieval Evaluation.
- v5.41 Semantic Similarity & Representation Search.
- v5.40 embedding governance and Workspace compute handoff.
- Python/Go/Rust runtime continuity.

## Guardrails
- Original-language source content remains canonical.
- Unicode normalization is derived and does not overwrite the original.
- Translation remains derived and is not automatic.
- Original-language capture is not itself a truth, quality, causal or evidence judgment.
- No automatic Platform Core promotion.
