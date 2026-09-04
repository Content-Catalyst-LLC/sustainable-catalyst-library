# Sustainable Catalyst Library v5.8.4
## Biomedical Evidence Grading & Study Design Intelligence

v5.8.4 adds a governed biomedical evidence-intelligence layer above PubMed and ClinicalTrials.gov.

### Core capabilities
- Metadata-derived study-design classification for PubMed literature.
- Structured design profiling for ClinicalTrials.gov records.
- Evidence-body mapping across literature and registered trials.
- Integrity signals for retraction-related, correction-related, and prepublication metadata.
- Certainty-domain readiness for risk of bias, inconsistency, indirectness, imprecision, and publication bias.
- Confidence-interval preservation from posted ClinicalTrials.gov aggregate analyses when available.
- Human-review handoffs for Research Librarian and downstream evidence workflows.

### Critical governance boundary
Sustainable Catalyst does **not** generate a formal GRADE certainty category, a formal RoB 2/ROBINS-I judgment, or an automated clinical recommendation from bibliographic/registry metadata.

Design classification is an evidence-navigation signal. Formal certainty assessment remains outcome-specific and requires structured human appraisal of methods, effect estimates, directness, consistency, precision, missing evidence, and study limitations.

### Study-design families
- evidence-synthesis
- randomized-interventional
- controlled-interventional
- interventional
- observational
- descriptive
- guideline-or-consensus
- narrative-secondary-research
- prepublication
- unclassified

### New backend endpoints
- `GET /v1/evidence-grading`
- `GET /v1/evidence-grading/search?q=...`
- `GET /v1/evidence-grading/trial/{nct_id}`

### WordPress shortcode
`[sc_biomedical_evidence_grading]`

Recommended Library sequence:
1. `[sc_biomedical_evidence]`
2. `[sc_fda_regulatory_intelligence]`
3. `[sc_medical_terminology]`
4. `[sc_clinical_trial_intelligence]`
5. `[sc_biomedical_evidence_grading]`
