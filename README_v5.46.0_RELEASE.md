# Sustainable Catalyst Knowledge Library v5.46.0

**OCR, HTR & Transcription Lineage**  
Backend **v2.57.0**

This release extends v5.45.0 original-language preservation with source-media preservation and reproducible lineage for optical character recognition, handwritten-text recognition, and audio/video transcription.

The release does not perform OCR, HTR, or transcription by itself. It defines and persists the governed lineage around outputs produced by compatible runtimes/providers so the Library can preserve exactly what source asset was processed, which engine/model and parameters were used, what text and segments were produced, what confidence measurements were reported, and what review state applies.

Recognition/transcription output is always marked as derived text. Confidence is not truth probability, and no automatic evidence/truth/Core promotion or translation occurs.

Run `tests/run_v5460_validation.sh` before packaging or synchronization.
