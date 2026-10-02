# Knowledge Library v5.75.0 — Python Language Intelligence & Document Processing

Python/FastAPI is the authoritative service boundary for original-language preservation, OCR/HTR/transcription lineage, linguistic corpus analysis, cross-language entity resolution, translation/transliteration alignment, and scientific-document intelligence.

This release does not create replacement language stores. It composes the existing PostgreSQL-backed Python runtimes behind one stable API v1 family.

## Architectural principles

- Analyze original language first; preserve the original representation as canonical.
- Unicode normalization, OCR, HTR, transcription, translation, and transliteration are derived representations with lineage.
- Translation does not replace the source text.
- Entity-resolution candidate scores and alignment confidence are not truth probabilities.
- Automated language or document processing does not certify semantic correctness or research truth.
- Human review state remains explicit.
- WordPress is presentation/API-client compatibility only.
- No automatic Platform Core promotion occurs.
