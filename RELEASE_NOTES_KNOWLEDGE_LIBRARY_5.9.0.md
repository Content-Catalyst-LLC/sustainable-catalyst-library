# Sustainable Catalyst Library v5.9.0

## Biomedical Evidence Graph & Evidence Synthesis

Library v5.9.0 advances the Python backend from v1.7.0 to v1.8.0 and connects the biomedical stack built in v5.8.0–v5.8.4 into a bounded evidence graph.

### Added

- New backend `BiomedicalEvidenceGraphEngine`.
- Provenance-backed nodes for research questions, PubMed publications, ClinicalTrials.gov trials, conditions, interventions, outcomes, terminology candidates, and FDA regulatory records.
- Explicit trial-to-publication linkage using ClinicalTrials.gov PMID references.
- Trial-to-condition, trial-to-intervention, and trial-to-outcome relationships from registry metadata.
- Candidate terminology context from ICD-11, MeSH, and RxNorm without semantic-equivalence claims.
- FDA regulatory context preserved as separate evidence classes.
- Descriptive evidence synthesis with study-design distribution, results-bearing trial counts, exact PMID linkage counts, integrity flags, and evidence gaps.
- Trial-specific graph-neighborhood endpoint.
- WordPress shortcode `[sc_biomedical_evidence_graph]` with a compact SVG relationship view and evidence-synthesis panel.

### Governance

v5.9.0 does not automatically generate formal GRADE certainty categories, formal risk-of-bias judgments, pooled effects, heterogeneity statistics, causal relationships, comparative-effectiveness conclusions, treatment rankings, or clinical recommendations.

### Deployment

- WordPress plugin: 5.9.0
- Library backend: 1.8.0
- PostgreSQL migration: none
- New credentials: none
- Existing optional WHO/openFDA/NCBI credentials remain supported
