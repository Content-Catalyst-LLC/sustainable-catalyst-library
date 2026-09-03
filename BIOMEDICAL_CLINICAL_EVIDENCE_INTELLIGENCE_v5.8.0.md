# Sustainable Catalyst Library v5.8.0 — Biomedical & Clinical Evidence Intelligence Foundation

## Scope

This release establishes a governed biomedical evidence layer shared by Library, Research Librarian, and Lab. It does not provide patient-specific diagnosis, treatment recommendations, or clinical decision support.

## First-wave sources

- PubMed — NCBI E-utilities
- PubMed Central — NCBI E-utilities
- ClinicalTrials.gov — REST API v2
- MeSH — 2026 RDF Lookup API
- RxNorm — RxNav REST API

## Backend contracts

- `GET /v1/biomedical-sources`
- `GET /v1/biomedical-sources/{source_key}/search?q=...`
- `GET /v1/biomedical/search?q=...&sources=...&limit=...`

The normalized record distinguishes literature, registered clinical studies, controlled vocabulary concepts, and drug concepts. Evidence-design classification is explicitly metadata-derived and human review remains required.

## WordPress

Shortcode: `[sc_biomedical_evidence]`

Proxy routes mirror the backend under `/wp-json/sc-library/v1/biomedical...`. The browser does not call biomedical upstreams directly.

## NCBI operations

Configure `SC_LIBRARY_NCBI_TOOL`, `SC_LIBRARY_NCBI_EMAIL`, and optionally `SC_LIBRARY_NCBI_API_KEY`. The API key is not required for ordinary low-rate operation but should be used for higher request rates.

## Attribution

This product uses publicly available data from the U.S. National Library of Medicine (NLM), National Institutes of Health, Department of Health and Human Services; NLM is not responsible for the product and does not endorse or recommend this or any other product.
