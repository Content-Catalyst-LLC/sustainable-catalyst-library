# Library v6.20.0 — Research Synthesis Workspace

Library v6.20.0 adds a first-class research synthesis workspace that composes existing Library authorities without creating a new truth store.

Generations: Library 6.20.0 / Backend 3.20.0 / Web 2.20.0 / SDK 1.20.0 / API v1 stable.

Public route: `/research/synthesis`.

API base: `/api/library/v1/research-synthesis`.

Capabilities include source and claim inventories, explicit source↔claim relationships, evidence matrices, contradiction ledgers, convergence summaries, source attribution, unresolved-question preservation, gap analysis, reproducible synthesis exports, and research-package publishing handoff previews.

The workspace never treats source counts, citation counts, agreement, support counts, or convergence patterns as truth probabilities or quality scores. Contradictions remain unresolved unless explicitly adjudicated outside this composition layer. No automatic claim/evidence/truth/Platform Core promotion occurs.

No database migration. WordPress remains optional/non-authoritative.
