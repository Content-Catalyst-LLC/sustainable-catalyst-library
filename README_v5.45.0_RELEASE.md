# Knowledge Library v5.45.0 Release

**Original-Language Corpus Ingestion & Preservation**

This release advances the v5.44 Global Source Federation into original-language corpus preservation. Backend 2.56.0 stores byte-exact source payloads and decoded source text with SHA-256 fingerprints, BCP 47 language identity, ISO 15924 script identity, language/orthography variants, canonical original representations, and explicit transformation lineage.

Unicode normalization is a derived representation. Translation, transliteration, OCR/HTR and transcription do not replace the original. No automatic translation, evidence promotion, truth promotion or Platform Core promotion occurs.

Run `tests/run_v5450_validation.sh` before synchronization or deployment. Production deployment compiles the retained Rust runtime and validates the new PostgreSQL tables and API contracts without inserting a sample source capture.
