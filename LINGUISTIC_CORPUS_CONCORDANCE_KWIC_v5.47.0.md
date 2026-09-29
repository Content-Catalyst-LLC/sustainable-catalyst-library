# Knowledge Library v5.47.0 — Linguistic Corpus Objects, Concordance & KWIC

## Release identity

- Knowledge Library: **5.47.0**
- Python backend: **2.58.0**
- Go ingestion runtime: **0.1.0**
- Rust graph runtime: **0.2.0**

## Purpose

v5.47.0 turns the text-preservation lineage created in v5.45 and v5.46 into a reproducible linguistic-analysis substrate. A corpus is no longer an implicit bag of strings. It is a governed collection of text representations with explicit document identities, token identities, tokenizer specification, source lineage, character offsets, and reproducible query fingerprints.

The release supports deterministic concordance, Key Word In Context (KWIC), phrase matching, case-sensitive or case-insensitive retrieval, and descriptive frequency tables.

## Object model

### Linguistic corpus

Contract: `sc-library-linguistic-corpus/1.0`

A corpus records:

- stable `corpus_id` and SHA-256 corpus fingerprint;
- title/description and caller metadata;
- exact tokenizer specification and fingerprint;
- document/token counts;
- language, script, and source-kind distributions;
- constituent linguistic-document objects;
- analytical guardrails.

### Linguistic document

Contract: `sc-library-linguistic-document/1.0`

Every document is bound to a specific `library_text_representations` object. It therefore retains whether the analyzed text is:

- canonical `original` source text;
- `unicode-normalized` derived text;
- OCR output;
- HTR output;
- transcription output; or
- another explicit derived representation.

Where available, the document also carries capture ID, source-media asset ID, derivation-run ID, review state, language/script/variant identity, record ID, SHA-256 text fingerprint, character count, and token count.

### Linguistic token

Contract: `sc-library-linguistic-token/1.0`

Tokens preserve:

- stable deterministic token ID;
- document and source-representation identity;
- sequence number;
- exact surface form;
- Unicode case-folded lookup form;
- token kind (`word` or `punctuation`);
- start/end character offsets into the source representation;
- SHA-256 token-text fingerprint.

## Tokenizer contract

Contract: `sc-library-tokenizer-specification/1.0`

v5.47.0 ships `unicode-word-v1`, a deterministic Unicode-aware operational tokenizer. It emits word and punctuation tokens and preserves character offsets.

It deliberately does **not** claim to provide:

- language-specific word segmentation;
- morphology;
- lemmatization;
- part-of-speech tagging;
- syntax parsing;
- semantic disambiguation.

Those capabilities require explicit later annotation/runtime layers rather than silent inference.

## Concordance & KWIC

Contracts:

- `sc-library-concordance-query/1.0`
- `sc-library-kwic-result/1.0`

KWIC supports:

- token and multi-token phrase queries;
- case-sensitive or Unicode case-folded matching;
- configurable left/right token window (0–50 tokens);
- result pagination through offset/limit;
- character and token offsets;
- source representation, language/script, source-kind, derivation-run, and review-state lineage;
- deterministic query fingerprints tied to corpus ID, tokenizer fingerprint, query, and options.

A KWIC occurrence is a contextual observation. It does not establish the intended meaning of the passage, author intent, evidence status, truth, causality, agreement, or importance.

## Frequency tables

Contract: `sc-library-corpus-frequency-table/1.0`

Frequency output is deterministic and descriptive. Surface-form variants are retained under the normalized case-folded token form. Frequency is not treated as importance, relevance, evidence strength, consensus, or truth.

## Persistence

Backend v2.58.0 adds three PostgreSQL tables:

- `library_linguistic_corpora`
- `library_linguistic_documents`
- `library_linguistic_tokens`

Persisted corpora can only be created through the signed backend-admin write path. During persistent creation, representation text is hydrated from the existing authoritative `library_text_representations` table rather than accepting an untraceable replacement string.

## Research Corpus Builder integration

Research Corpus Builder can now carry:

- `linguistic_corpus_id`
- `linguistic_document_id`
- `linguistic_tokenizer_fingerprint`
- `linguistic_token_count`

These extend the v5.45/v5.46 language and derivation fields so exported datasets can retain linguistic-analysis lineage.

## WordPress boundary

WordPress v5.47.0 exposes:

- public readiness status;
- administrator-only validation/package/KWIC/frequency proxies;
- shortcode `[sc_linguistic_corpus_status]`.

WordPress does not persist corpus token streams or source text. Signed persistent corpus creation remains backend-authoritative.

## Guardrails

v5.47.0 explicitly enforces:

- tokenization ≠ morphology;
- tokenization ≠ POS tagging;
- tokenization ≠ syntax parsing;
- frequency ≠ importance;
- KWIC context ≠ verified meaning or intent;
- corpus occurrence ≠ evidence or truth;
- original/derived source lineage is preserved;
- no automatic translation or transliteration;
- no automatic evidence/truth promotion;
- no automatic Platform Core promotion.
