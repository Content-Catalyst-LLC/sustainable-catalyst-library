# Knowledge Library v5.48.0 — Cross-Language Entity, Name & Historical Toponym Resolution

## Purpose
v5.48.0 adds a governed identity-resolution layer for multilingual and historical research. It connects names across languages, scripts, declared transliterations, aliases, endonyms/exonyms, and historical toponyms while preserving uncertainty and provenance.

## Core objects
- Cross-language entity authority
- Entity name form
- Historical toponym validity window
- Resolution case
- Resolution candidate
- Explicit resolution decision

Name forms preserve the supplied text, BCP 47 language identity, ISO 15924 script identity, relation type, optional transliteration system, language/orthography variants, source reference, and optional historical validity window.

## Matching model
Matching uses comparison-only Unicode normalization and optional diacritic folding. Those comparison keys never replace source text. Transliteration is supported only when a transliterated form is explicitly supplied and identified; v5.48.0 does not generate transliterations automatically.

Candidate ranking can expose signals such as normalized-name match, diacritic-fold match, declared-transliteration match, language/script match, entity-type match, and historical-validity match/conflict. Scores are bounded ranking signals, not probabilities.

## Historical toponyms
Historical place names may carry `valid_from_year` and `valid_to_year`. Query-year context can raise a temporally compatible form or flag a temporal conflict. A conflict does not delete the candidate because historically inconsistent references can themselves be research-relevant.

## Adjudication
The system does not auto-merge entities or auto-resolve a case. A persistent accepted resolution requires an explicit signed decision referencing one of the candidates and a rationale. `ambiguous`, `unresolved`, and `rejected` decisions preserve the absence of a selected candidate.

## Guardrails
- candidate score != probability
- candidate rank != identity or truth
- name similarity != identity
- historical-name overlap != identity
- no automatic entity merge
- no automatic resolution
- no automatic translation/transliteration
- ambiguity preserved
- no automatic evidence/truth/Core promotion

## Lineage
v5.48.0 consumes the v5.47 linguistic-corpus layer and preserves v5.46 OCR/HTR/transcription lineage plus v5.45 original-language capture lineage. It establishes the entity/name layer needed for v5.49 translation/transliteration alignment.
