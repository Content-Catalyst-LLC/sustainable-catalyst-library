# Evidence Matrix & Claim Intelligence — v4.3.32

## Purpose

v4.3.32 adds a private project-facing evidence review layer above the existing v2.7 Evidence Notes and Research Claims system. It keeps source material, reading notes, evidence relationships, claim statements, and interpretation visibly separate.

## Records

- `sc_evidence_matrix` — private account-owned matrix container
- `sc-library-matrix-claim/1.0` — user-authored working claim rows
- `sc-library-matrix-evidence-link/1.0` — explicit evidence-to-claim relationships
- `sc-library-claim-intelligence-diagnostics/1.0` — deterministic descriptive diagnostics

Stable identifiers use UUID-backed URNs.

## Evidence relationships

Relationships are explicit and user-created: supports, qualifies, contradicts, contextualizes, and unresolved/follow-up. Sources can be selected from account-persistent Reading Notes, Source Annotations, canonical v2.7 Evidence Notes, project references/source bundles, or an external source entered by the user. Underlying records and private binaries are referenced rather than copied.

## Claim Intelligence

Diagnostics report only mechanically observable matrix conditions: evidence-link count, relation totals, unique source-key count, unresolved references, wording/locator check coverage, support-only/mixed/contradiction-heavy/context-only patterns, and visible gaps. Diagnostics do not determine truth, infer a conclusion, change claim status, or change confidence.

## Notebook promotion boundary

A Reading Note or Source Annotation becomes part of an evidence matrix only when the user explicitly links it. v4.3.31's `automatic_evidence_promotion = false` contract remains intact.

## REST API

Authenticated current-user endpoints begin at `/wp-json/sc-library/v1/evidence-matrices`. Matrix manifests include a SHA-256 checksum and exclude private binary payloads.

## Non-goals

No automatic claim generation, evidence promotion, truth scoring, confidence scoring, publication, or Workspace write.
