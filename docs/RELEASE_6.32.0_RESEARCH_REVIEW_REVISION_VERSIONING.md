# Library v6.32.0 — Research Review, Revision & Versioning System

**Library:** 6.32.0
**Backend:** 3.32.0
**Web:** 2.32.0
**SDK:** 1.32.0
**Route:** `/research/project/review`
**API:** `/api/library/v1/research-review-versioning`

## Purpose

Add an explicit review and versioning composition layer over existing Sustainable Catalyst research objects. The release creates content-fingerprinted version snapshots, deterministic field-level comparisons, human review packets, explicit review decisions, revision proposals, version histories, chain validation, and portable exports.

## Authority boundary

This release does not become a new research-object, evidence, citation, truth, execution, or project-persistence authority. PostgreSQL research state remains authoritative for persistent projects. Review/versioning objects are composition outputs until a future signed persistence workflow explicitly accepts them.

## Guardrails

- Approval does not imply truth or scientific validity.
- Rejection does not imply falsity.
- Revision count and change count do not imply quality or materiality.
- Review decisions are explicit human assertions; no automatic decision is generated.
- Revision proposals are never automatically applied.
- Version snapshots preserve the originating object's authority.
- Supersession/version links are explicit lineage handoffs only.
- No automatic claim, evidence, truth, or Platform Core promotion.
- No database migration.
- WordPress is not required.

## Operations

- `snapshot`
- `compare`
- `review-packet`
- `revision-proposal`
- `review-decision`
- `version-history`
- `validate-chain`
- `export`

## Next release

**v6.33.0 — Research Package Validation & Publication Readiness**
