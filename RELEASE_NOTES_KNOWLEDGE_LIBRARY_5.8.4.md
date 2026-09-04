# Release Notes — Sustainable Catalyst Library v5.8.4

**Release:** v5.8.4 — Biomedical Evidence Grading & Study Design Intelligence  
**Backend:** v1.7.0  
**Database migration:** none

## Added
- New backend `evidence_grading.py` evidence-profile engine.
- PubMed publication-type design classification.
- ClinicalTrials.gov design profiling using study type, allocation, masking, phases, enrollment, results state and publication/retraction signals.
- Evidence-body mapping across PubMed and ClinicalTrials.gov.
- Certainty-domain readiness model: risk of bias, inconsistency, indirectness, imprecision, publication bias.
- Confidence-interval fields in normalized ClinicalTrials.gov aggregate analyses.
- New WordPress shortcode `[sc_biomedical_evidence_grading]`.
- New WordPress proxy routes for evidence grading.
- New evidence grading CSS/JS presentation layer.

## Governance
- No formal GRADE category is generated automatically.
- No formal RoB 2, ROBINS-I or equivalent risk-of-bias judgment is generated automatically.
- Registry design metadata is not treated as verification of study conduct.
- Evidence-body summaries are descriptive evidence maps, not clinical recommendations.
- Research-only / no patient-specific diagnosis or treatment boundary remains in force.

## Compatibility
- Preserves v5.8.3 Clinical Study & Trial Intelligence.
- Preserves v5.8.2 Medical Terminology & Disease Classification.
- Preserves v5.8.1 FDA Drug & Regulatory Intelligence.
- Preserves v5.8.0 Biomedical & Clinical Evidence Foundation.
- Preserves v5.7.x institutional research source line.
