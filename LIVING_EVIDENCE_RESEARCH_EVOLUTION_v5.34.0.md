# Knowledge Library v5.34.0 — Living Evidence & Research Evolution

v5.34.0 extends the reproducible literature-review engine into a living-evidence workflow while preserving immutable historical review states.

## Contracts
- `sc-library-living-evidence/1.0`
- `sc-library-living-evidence-update-candidate/1.0`
- `sc-library-research-evolution/1.0`
- `sc-library-living-review-surveillance/1.0`

## Capabilities
The engine compares a baseline review snapshot with a current review snapshot and produces deterministic, reviewable change objects. It can surface newly observed records, records not present in the current snapshot, source/content/version changes, explicit publication-status changes, explicit correction/retraction/withdrawal/supersession events, screening-decision changes, extraction changes, and review-protocol changes.

Research evolution is represented as descriptive chronology from explicit publication dates and explicit change events. Surveillance plans preserve search strategies and optional cadence metadata but do not execute searches or screening automatically.

## Review-update candidates
Every detected change is represented as a `living-evidence-update-candidate` with an evidence basis, suggested review action, `automatic_state_change: false`, and `human_review_required: true`. Candidate nodes and edges are analytical only and are excluded from default evidence paths.

## Governance boundaries
- A new record is not automatically included.
- A record absent from a later snapshot is not automatically withdrawn, false, or deleted from history.
- Newer evidence is not automatically better or more true.
- A correction/retraction/status event does not automatically determine the fate of related claims or conclusions.
- Screening and extraction changes do not overwrite prior review snapshots.
- Chronology does not imply causality or field-wide consensus change.
- Living-review surveillance does not imply continuous automated searching.
- No automatic meta-analysis or consensus inference occurs.
- Platform Core remains the authority for governed research objects and synthesis.

## Runtime continuity
Backend v2.45.0 carries forward the corrected Rust graph runtime v0.1.0. Rust remains a graph-compute accelerator; Python owns living-evidence semantics, review lineage, guardrails, and update-candidate construction.
