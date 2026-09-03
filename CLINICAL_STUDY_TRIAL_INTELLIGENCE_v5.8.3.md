# Sustainable Catalyst Library v5.8.3 — Clinical Study & Trial Intelligence

## Purpose
v5.8.3 turns the existing ClinicalTrials.gov connector into a structured research-intelligence surface for study design, eligibility, interventions, endpoints, results state, sponsors, locations, linked publications, retraction signals, and descriptive multi-trial comparison.

## Backend
Library backend v1.6.0 adds:
- `GET /v1/clinical-trials`
- `GET /v1/clinical-trials/search`
- `GET /v1/clinical-trials/{nct_id}`
- `GET /v1/clinical-trials/compare?nct_ids=NCT...,NCT...`

Search supports general terms plus condition, intervention, sponsor, location, recruitment status, phase, and study type. Comparison is bounded to eight unique NCT IDs.

## Evidence boundaries
- A registry record is not treated as equivalent to a peer-reviewed publication.
- Posted registry results remain distinct from linked publications.
- Absence of a linked publication does not prove that a study is unpublished.
- Retraction signals attached to ClinicalTrials.gov references are preserved.
- No participant-level data are exposed by the normalized v5.8.3 object.
- Comparisons are descriptive; no comparative-effectiveness or treatment recommendation is generated.

## WordPress
Shortcode: `[sc_clinical_trial_intelligence]`

Recommended placement on the Research Library page after biomedical evidence, FDA regulatory intelligence, and medical terminology.
