# Energy Systems Intelligence v1.0.0

## Integrated Sustainable Energy Systems Platform

Energy Systems Intelligence v1.0.0 is the integration and certification release for the v0.1.0-v0.9.0 release line. It keeps Library v5.11.0 and Carbon & Nature Intelligence v0.5.0 unchanged while advancing the shared Library backend to v2.16.0.

The release does not introduce a new automatic decision engine. Instead, it makes the existing Energy Systems layers explicit as one governed platform surface:

1. v0.1.0 Sustainable Energy Knowledge Foundation
2. v0.2.0 Energy Units, Carbon Factors & Conversion Registry
3. v0.3.0 Energy Sustainability Indicators
4. v0.4.0 Renewable Technology & Resource Model
5. v0.5.0 Energy Balance & Systems Modeling
6. v0.6.0 Energy Scenario Economics
7. v0.7.0 Biological Carbon & Bioenergy Integration
8. v0.8.0 Global Energy Intelligence
9. v0.9.0 Energy Decision Intelligence

v1.0.0 adds a six-target cross-product contract registry covering Library, Research Librarian, Lab, Workbench, Site Intelligence, and Decision Studio. Library is the active host runtime. The five separate products are represented as contract-ready handoff targets only; this release does not claim that their runtimes execute the contracts.

A new integrated-study contract can package research context, source-bound numeric references, sustainability indicators, technologies/resources, energy-balance scenarios, economic results, bioenergy/carbon context, global observations, decision packets, uncertainty, provenance, assumptions, and review notes into one portable structure. Persistence is not implemented.

The platform certification endpoint performs repository/domain-contract coherence checks against the expected release identities, registry counts, model counts, and guardrails. A passing certification is not scientific validation, site-suitability analysis, financial advice, external-data validation, or a live deployment audit.

## New backend routes

- `GET /v1/energy-systems/platform-framework`
- `GET /v1/energy-systems/platform-contracts`
- `GET /v1/energy-systems/platform-study-template`
- `GET /v1/energy-systems/platform-certification`

## WordPress

The existing `[sc_energy_systems_intelligence]` shortcode now opens on an **Integrated Platform** view with release lineage, cross-product contracts, structural certification, and the blank integrated-study contract. All previous tabs remain available.

## Deployment

No database migration and no new environment variable or API secret are required.
