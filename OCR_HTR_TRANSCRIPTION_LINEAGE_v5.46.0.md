# Knowledge Library v5.46.0 — OCR, HTR & Transcription Lineage

Backend: **v2.57.0**  
Go ingestion runtime: **v0.1.0**  
Rust graph runtime: **v0.2.0**

## Purpose

v5.46.0 extends the original-language preservation substrate introduced in v5.45.0 so machine-derived text from scanned pages, handwriting, audio, and video can be represented without confusing the derived text with the source artifact.

## Scientific lineage model

The release separates four layers:

1. **Source media asset** — exact binary source payload (image, PDF, audio, video, or other media), SHA-256 fingerprint, source URI, global-source identity, record identity, and provenance.
2. **Derivation run** — OCR, HTR, or transcription execution identity, engine/provider/model/version, parameters, output hash, language/script identity, confidence summary, and review state.
3. **Derived text representation** — the generated text is a derived `sc-library-text-representation/1.1` object. It is never canonical original text.
4. **Segments** — page/line/block geometry for OCR/HTR or millisecond time spans and speaker labels for transcription, each with text hash, confidence measurement, and review state.

A derivation may originate from a preserved source-media asset or an existing text representation. This avoids the false assumption that OCR and transcription always begin with text.

## Contracts

- `sc-library-ocr-htr-transcription-lineage/1.0`
- `sc-library-source-media-asset/1.0`
- `sc-library-text-derivation-run/1.0`
- `sc-library-text-derivation-segment/1.0`
- `sc-library-text-derivation-validation/1.0`
- `sc-library-ocr-htr-transcription-readiness/1.0`
- `sc-library-text-representation/1.1`

## Persistence

New tables:

- `library_source_media_assets`
- `library_text_derivation_runs`
- `library_text_derivation_segments`

`library_text_representations` is extended so derived OCR/HTR/transcription text may reference a media asset without requiring an original-language text capture. Existing v5.45 original-language representations remain unchanged and retain their capture linkage.

## Guardrails

- OCR/HTR/transcription output is derived text, not original source text.
- Confidence is an engine measurement, not a probability that the text is true.
- Machine-derived text is not automatically evidence, truth, consensus, or a Platform Core object.
- No automatic translation is performed.
- No derived output replaces or mutates a preserved original-language capture.
- Human review state remains explicit and independently inspectable.
- Source-media bytes are never exposed through the public WordPress status surface.

## Corpus continuity

Research Corpus Builder can carry:

- `text_derivation_kind`
- `text_derivation_run_id`
- `text_derivation_source_asset_id`
- `text_derivation_engine_fingerprint`
- `text_derivation_review_state`

These fields prepare v5.47.0 linguistic corpus/concordance/KWIC objects to distinguish original-language text from machine-derived text.
