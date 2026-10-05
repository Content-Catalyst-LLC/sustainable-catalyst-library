# Library v6.18.0 — Corpus & Computational Linguistics Workspace

Library v6.18.0 exposes the existing durable v5.47 linguistic-corpus foundation as a first-class researcher-facing workspace without creating a second corpus store.

Generations: Library 6.18.0 / Backend 3.18.0 / Web 2.18.0 / SDK 1.18.0 / API v1 stable.

Public route: `/research/corpus`.

API base: `/api/library/v1/corpus-workspace`.

Capabilities include non-persistent corpus preview, persisted-corpus analysis, token/frequency analysis, KWIC/concordance, n-grams, co-occurrence, representation-lineage visibility, JSON analysis export, and explicit persistence handoff preview.

The v5.47 Python/PostgreSQL linguistic-corpus service remains durable corpus authority. Persistence requires an explicit signed request through `/api/library/v1/admin/language/corpora`; the workspace does not auto-persist preview corpora.

Guardrails: original-language representations remain canonical; derived representations remain explicit; tokenization is not morphology, lemmatization, POS tagging, or syntax parsing; frequency does not imply importance; KWIC does not establish meaning or intent; n-gram frequency does not establish phrase significance; co-occurrence does not establish semantic relationship, causation, or statistical significance; no automatic evidence, truth, or Platform Core promotion.

No database migration. WordPress remains optional/non-authoritative.
