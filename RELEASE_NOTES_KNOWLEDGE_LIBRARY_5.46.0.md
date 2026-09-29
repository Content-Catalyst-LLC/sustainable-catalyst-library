# Release Notes — Knowledge Library v5.46.0

## Added

- Preserved source-media asset contract and storage for image/PDF/audio/video inputs.
- OCR, HTR, and transcription derivation-run objects.
- Engine/provider/model/version and parameter fingerprints.
- Page/line/block segment geometry and transcription timecode/speaker lineage.
- Confidence summaries and segment confidence as descriptive measurements.
- Explicit review states: `unreviewed`, `in-review`, `human-reviewed`, `accepted`, `rejected`.
- Derived `sc-library-text-representation/1.1` objects linked to source media.
- Signed persistence and signed retrieval endpoints for derivation runs.
- Public validation/package endpoints and readiness endpoint.
- WordPress readiness proxy and `[sc_ocr_htr_transcription_lineage_status]` shortcode.
- Research Corpus Builder derivation-lineage fields.

## Preserved

- v5.45.0 byte-exact original-language source preservation.
- v5.44.0 global source federation registry and connector contracts.
- v5.43.0 publication embedding maps.
- v5.42.0 neural reranking and evaluation.
- v5.41.0 governed similarity search.
- v5.40.x scientific embedding governance.

## Non-goals

- No OCR/HTR/ASR provider is invoked during deployment.
- No external model credentials are introduced.
- No automatic translation.
- No automatic evidence/truth/Platform Core promotion.
- No confidence-to-truth conversion.
