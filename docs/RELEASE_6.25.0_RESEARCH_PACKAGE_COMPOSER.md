# Library v6.25.0 — Research Package Composer

Library v6.25.0 adds a Research Package Composer that assembles typed outputs from investigation, synthesis, evidence, statistical, geospatial, citation, annotation, timeline, corpus, entity/place, primary-source, dataset, record, artifact, and publication workflows into an explicit draft package.

Generations: Library 6.25.0 / Backend 3.25.0 / Web 2.25.0 / SDK 1.25.0 / API v1 stable.

Public route: `/research/package`.

API base: `/api/library/v1/research-package-composer`.

Capabilities include typed component assembly, explicit section ordering, explicit dependency maps, completeness audits against user-declared requirements, provenance audits, reference inventories, deterministic draft export, portable publishing handoff preview, and signed reproducibility-package handoff preview.

The Composer is not a replacement for the existing Research Package reproducibility service, Research Package Publishing service, PostgreSQL structured state, or the content-addressed artifact store. It does not rewrite component payloads, infer dependencies, publish externally, persist packages, or convert completeness/integrity into truth, source validity, or evidence strength.

No database migration. WordPress remains optional/non-authoritative.
