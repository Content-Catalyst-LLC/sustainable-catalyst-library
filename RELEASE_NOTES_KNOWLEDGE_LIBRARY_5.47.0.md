# Release Notes — Knowledge Library v5.47.0

**Linguistic Corpus Objects, Concordance & KWIC**

Knowledge Library v5.47.0 pairs WordPress plugin **5.47.0** with Python backend **2.58.0**. It builds directly on v5.45 original-language preservation and v5.46 OCR/HTR/transcription lineage.

### Added

- Governed linguistic corpus, document, and token objects.
- Deterministic Unicode tokenizer specification and fingerprint.
- Stable token character offsets and representation lineage.
- Phrase-aware concordance and KWIC queries.
- Case-sensitive and Unicode case-folded matching.
- Deterministic query fingerprints.
- Descriptive corpus frequency tables with retained surface variants.
- PostgreSQL persistence for corpora/documents/tokens.
- Signed corpus creation and persisted KWIC backend routes.
- Research Corpus Builder linguistic-lineage fields.
- WordPress readiness/admin analysis proxies and `[sc_linguistic_corpus_status]`.

### Preserved

- v5.46 OCR, HTR & transcription source/engine/segment lineage.
- v5.45 original-language canonical source preservation.
- v5.44 global source federation registry/connector contracts.
- v5.43 publication embedding maps.
- v5.42 neural reranking.
- v5.41 semantic similarity/representation search.
- v5.40 embedding governance.

### Guardrails

Tokenization, concordance, KWIC, and frequency are analytical representations. They do not silently infer morphology, lemma, part-of-speech, syntax, author intent, semantic meaning, evidence status, truth, causality, consensus, or importance.
