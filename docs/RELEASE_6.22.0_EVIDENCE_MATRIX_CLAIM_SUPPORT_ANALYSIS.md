# Library v6.22.0 — Evidence Matrix & Claim Support Analysis

Library v6.22.0 adds a dedicated claim-evidence analysis workspace over existing Library evidence, provenance, citation, synthesis, and investigation structures.

Generations: Library 6.22.0 / Backend 3.22.0 / Web 2.22.0 / SDK 1.22.0 / API v1 stable.

Public route: `/research/evidence`.

API base: `/api/library/v1/evidence-matrix`.

Capabilities include claim and evidence inventories, explicit claim↔evidence relationships, claim-evidence matrices, descriptive support profiles, contradiction/qualification analysis, provenance coverage, source-dependency and independence-group analysis, evidence-gap analysis, reproducible JSON export, and non-executing handoff previews to Research Synthesis and Research Investigation.

Support counts, contradiction counts, directness, provenance completeness, and independent-source counts are descriptive only. They are never truth probabilities, confidence scores, quality scores, or automatic verdicts. Contradictions and qualifications remain explicit. Source dependence is never silently treated as independence.

No database migration. WordPress remains optional/non-authoritative.
