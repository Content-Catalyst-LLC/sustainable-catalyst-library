# Library v6.19.0 — Entity, Place & Historical Toponym Workspace

Library v6.19.0 exposes the existing v5.48 Python/PostgreSQL cross-language entity-resolution authority as a first-class research workspace.

Generations: Library 6.19.0 / Backend 3.19.0 / Web 2.19.0 / SDK 1.19.0 / API v1 stable.

Public route: `/research/entities`.

API base: `/api/library/v1/entity-place-workspace`.

Capabilities include non-persistent authority previews; multilingual, endonym, exonym, transliteration, and historical name forms; historical toponym timelines; temporal candidate resolution; candidate comparison matrices; explicit decision previews; persisted-case inspection; reproducible JSON exports; and signed persistence handoff previews.

The v5.48 cross-language resolution service remains durable authority. Candidate score is not probability, rank is not truth or winner selection, name similarity and historical overlap do not establish identity, and no automatic entity merge, resolution, translation, evidence promotion, truth promotion, or Platform Core promotion occurs.

No database migration. WordPress remains optional/non-authoritative.
