# Sustainable Catalyst Knowledge Library v5.45.0
## Original-Language Corpus Ingestion & Preservation

### Release identity
- WordPress: 5.45.0
- Python backend: 2.56.0
- Go ingestion runtime: 0.1.0
- Rust graph runtime: 0.2.0

## Purpose
v5.45.0 establishes original-language text as a first-class preserved research object. The Library captures source bytes and decoded source text without replacing them, records language/script/variant identity, and creates explicitly derived text representations only through traceable transformations.

## Contracts
- `sc-library-original-language-corpus/1.0`
- `sc-library-original-language-capture/1.0`
- `sc-library-text-representation/1.0`
- `sc-library-text-transformation/1.0`
- `sc-library-original-language-validation/1.0`
- `sc-library-original-language-corpus-readiness/1.0`

## Preservation model
Each capture stores:
- deterministic capture ID and SHA-256 fingerprint;
- optional Global Source Federation `source_id`;
- source record ID and source URI;
- BCP 47 language tag;
- optional ISO 15924 script code;
- language and orthography variants;
- media type and charset;
- exact source bytes (`bytea`) with SHA-256;
- decoded source text with SHA-256;
- source metadata, provenance and retrieval time.

The canonical text representation is always `representation_kind=original`, `canonical_original=true`, `derived=false`.

## Derived representations
Unicode normalization is stored as a separate `unicode-normalized` representation with an explicit `unicode-normalization` transformation. The original is never overwritten. Transliteration, translation, OCR, HTR, transcription and editorial normalization are reserved representation kinds for explicit later lineage; v5.45 does not silently create them.

## API
Public/read-only contract surfaces:
- `GET /v1/original-language-corpus/readiness`
- `POST /v1/original-language-corpus/validate`
- `POST /v1/original-language-corpus/package`

Signed administrative persistence surfaces:
- `POST /v1/admin/original-language-corpus/captures`
- `GET /v1/admin/original-language-corpus/captures/{capture_id}`

Raw source bytes/text are never exposed through the public WordPress status surface.

## Database
New tables:
- `library_original_language_captures`
- `library_text_representations`
- `library_text_transformations`

Capture-to-representation and transformation links use `ON DELETE RESTRICT` to preserve lineage.

## Research Corpus Builder integration
Dataset/corpus exports may explicitly include:
- `original_language`
- `script_iso15924`
- `language_variant`
- `orthography_variant`
- `original_language_capture_id`
- `original_language_representation_id`

## Governance
- Original language is canonical.
- Translation is a derived representation.
- Normalized text never replaces the original capture.
- Automatic translation is disabled.
- Capture existence does not imply evidence quality or truth.
- No automatic evidence, truth or Platform Core promotion occurs.
- Source-quality signals remain separate from user trust choices.

## Next lineage
v5.46.0 adds OCR, HTR & Transcription Lineage on top of this preserved original-language substrate. v5.47.0 adds linguistic corpus objects, concordance and KWIC.
