# Sustainable Catalyst Library v5.9.0 — Biomedical Evidence Graph & Evidence Synthesis

## Purpose

v5.9.0 connects the biomedical capabilities introduced in v5.8.0–v5.8.4 into a provenance-backed evidence graph. The graph is designed for research navigation, evidence review, and downstream Research Librarian/Lab handoff. It is not clinical decision support.

## Connected evidence families

- PubMed biomedical literature and publication-type metadata
- ClinicalTrials.gov trial design, conditions, interventions, outcomes, posted-results state, and explicit PMID references
- WHO ICD-11, MeSH, and RxNorm terminology candidates
- FDA/openFDA regulatory evidence classes, including approval, labeling, safety-report, and therapeutic-equivalence context
- v5.8.4 evidence-profile metadata and integrity-review signals

## Graph node types

- research-question
- publication
- clinical-trial
- condition
- intervention
- outcome
- terminology-concept
- regulatory-record

## Provenance-backed edge types

- retrieved-for-question
- studies-condition
- evaluates-intervention
- measures-outcome
- registry-links-publication
- candidate-concept-for-question
- regulatory-record-for-question

The engine creates a trial-publication edge only when ClinicalTrials.gov supplies a PMID reference. Terminology results are parallel candidates and are not automatically treated as equivalent. Regulatory records remain in their original evidence classes.

## Descriptive evidence synthesis

The synthesis layer summarizes:

- graph node and relationship counts
- study-design distribution
- trials with posted aggregate results
- explicit registry PMID relationships
- terminology candidate coverage
- regulatory-context coverage
- integrity-review signals
- missing-source and linkage gaps

It does not automatically generate a pooled effect, heterogeneity statistic, formal GRADE certainty rating, formal risk-of-bias judgment, causal relationship, treatment ranking, comparative-effectiveness conclusion, or clinical recommendation.

## API

- `GET /v1/biomedical-evidence-graph`
- `GET /v1/biomedical-evidence-graph/build?q=...`
- `GET /v1/biomedical-evidence-graph/synthesis?q=...`
- `GET /v1/biomedical-evidence-graph/trial/{nct_id}`

## WordPress

Shortcode:

`[sc_biomedical_evidence_graph]`

Recommended biomedical sequence:

1. `[sc_biomedical_evidence]`
2. `[sc_fda_regulatory_intelligence]`
3. `[sc_medical_terminology]`
4. `[sc_clinical_trial_intelligence]`
5. `[sc_biomedical_evidence_grading]`
6. `[sc_biomedical_evidence_graph]`

## Governance

- Research-only
- No patient-specific diagnosis or treatment
- No automatic clinical decision support
- No inferred semantic equivalence between terminologies
- No inferred causality from graph adjacency
- No automatic pooled-effect meta-analysis
- Human review required for evidence conclusions
