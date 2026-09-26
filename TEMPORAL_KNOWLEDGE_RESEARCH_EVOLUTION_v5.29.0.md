# Temporal Knowledge & Research Evolution — v5.29.0

## Purpose

The temporal layer answers a different question from ordinary publication-year visualization: **what entered the recorded research corpus when, and how did the recorded status of that material change?**

## Temporal model

Each source record may contribute explicit dated events such as `published`, `indexed`, `version-recorded`, `corrected`, `retracted`, `withdrawn`, `superseded`, `dataset-updated`, `supplement-added`, `evidence-reviewed`, and `status-changed`. Events retain a source field and provenance payload.

### Historical availability

The `historical-availability` lens uses only records and status events available by the cutoff. A correction issued in 2025 is not projected into a 2022 snapshot.

### Retrospective status

The `retrospective-status` lens preserves the 2022 record set while allowing the interface to annotate that a record was later corrected, retracted, withdrawn, or superseded. This is explicitly retrospective and is not represented as knowledge that existed in 2022.

## Change sets

A temporal change set compares two snapshots and reports added records, removed records, and status transitions. It does not infer why the change happened and does not label the change as a consensus shift.

## Research objects

Claims, findings, evidence objects, hypotheses, datasets, and scientific objects enter the temporal event stream only when their upstream objects carry explicit dates such as `reviewed_at`, `accepted_at`, `created_at`, or `published_at`. Missing dates are not invented.

## Product boundary

Knowledge Library owns source chronology, source versions/status, corpus snapshots, and temporal retrieval/display. Platform Core remains responsible for governed evidence/claim objects, reasoning, uncertainty, synthesis, and cross-product research-object authority.
