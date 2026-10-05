# Sustainable Catalyst Library v6.29.0 — Cross-Product Research Handoff & Contract Certification

## Purpose
v6.29.0 turns the existing Library cross-product integration fabric into an explicit research handoff and contract-certification layer spanning Research Librarian AI, Workspace, Research Lab, Workbench, Site Intelligence, Decision Studio, and Platform Core.

## What is certified
- typed object handoff envelopes and stable contract identifiers;
- schema/version compatibility rules;
- originating-product authority preservation;
- provenance preservation;
- explicit signed-write persistence boundaries;
- fail-closed behavior for unavailable targets, mismatches, unsupported types, missing provenance, unsigned writes, authority conflicts, partial results, and duplicate delivery;
- backward-compatibility policy and runtime-observation framework.

## Important boundary
Structural contract certification is not a claim that every remote product runtime was contacted. Live runtime certification requires explicit runtime observations from the target product. Missing observations remain visible and do not silently become PASS.

## Versions
- Library 6.29.0
- Backend 3.29.0
- Web 2.29.0
- SDK 1.29.0
- API v1 stable

## Surface
- Web: `/research/integration-certification`
- API: `/api/library/v1/cross-product-certification`

## Guardrails
No automatic cross-product push, remote execution, result import, artifact persistence, claim/evidence/truth promotion, or Platform Core promotion. No database migration. WordPress remains optional.

## Next
v6.30.0 — Unified Research Project Workspace.
