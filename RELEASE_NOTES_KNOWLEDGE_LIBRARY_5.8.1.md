# Release Notes — Sustainable Catalyst Library v5.8.1

## FDA Drug & Regulatory Intelligence

- Library backend advances from 1.3.0 to 1.4.0.
- Adds a governed FDA regulatory source registry backed by `https://api.fda.gov`.
- Adds native search/normalization for Drugs@FDA, FDA Drug Labeling, NDC Directory, FAERS adverse-event reports, Drug Recall Enforcement Reports, Drug Shortages, and the FDA Orange Book.
- Preserves regulatory evidence class on every result: approval, label, listing, safety report, enforcement event, supply signal, or therapeutic-equivalence reference.
- Adds harmonized drug identifiers where openFDA supplies them, including application numbers, NDCs, RxCUIs, brand/generic names, substances, routes, and dosage forms.
- FAERS results explicitly state that spontaneous reports do not establish causality and cannot by themselves establish incidence or risk.
- Adds `/v1/fda-sources`, `/v1/fda-sources/{source_key}/search`, `/v1/fda/search`, and `/v1/biomedical/intelligence/search`.
- Adds WordPress `[sc_fda_regulatory_intelligence]` plus REST proxy routes.
- Adds FDA Drug & Regulatory Data to the existing Research Network and homepage source ticker.
- Preserves the v5.8.0 PubMed/PMC/ClinicalTrials.gov/MeSH/RxNorm biomedical foundation.
- No PostgreSQL migration, DNS change, Caddy change, or port change.
- Research-only boundary remains explicit; the system is not patient-specific diagnosis, treatment, or clinical decision support.
