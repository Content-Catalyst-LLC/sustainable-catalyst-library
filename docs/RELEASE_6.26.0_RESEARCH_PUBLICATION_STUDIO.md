# Library v6.26.0 — Research Publication Studio

Library v6.26.0 adds a Research Publication Studio on top of the v6.25.0 Research Package Composer.

Generations: Library 6.26.0 / Backend 3.26.0 / Web 2.26.0 / SDK 1.26.0 / API v1 stable.

Public route: `/research/publication`.

API base: `/api/library/v1/research-publication`.

The Studio consumes an explicit Research Package Composer composition and organizes it into a governed publication draft. It adds publication profiles, structured sections, contributors, figures, tables, appendices, citation inventories, editorial audits, publication-readiness audits, deterministic draft export, and a preview-only handoff to the existing Research Package Publishing service.

The Studio does not generate research prose automatically, rewrite package components, become citation or evidence authority, publish externally, register DOIs, persist artifacts, or replace the existing Research Package Composer, Research Package Publishing service, reproducibility service, PostgreSQL state authority, or content-addressed artifact store.

Publication-profile readiness is an editorial completeness signal only. It is not a truth score, peer-review result, evidence-strength score, source-validity judgment, or publication acceptance decision.

No database migration. WordPress remains optional/non-authoritative.
