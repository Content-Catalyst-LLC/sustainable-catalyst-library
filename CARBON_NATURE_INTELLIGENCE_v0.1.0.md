# Carbon & Nature Intelligence v0.1.0 — AFOLU & Nature-Based Solutions Knowledge Foundation

## Product boundary

Carbon & Nature Intelligence is a Library-native domain subsystem. Sustainable Catalyst Library remains on the v5.11.x application line while Carbon & Nature starts its own v0.1.0 subsystem line. Backend capability advances to v2.2.0.

## Scope

- stable concept identifiers for AFOLU, Nature-Based Solutions, carbon farming, SOC, MRV, evidence, and policy context
- greenhouse gases: CO2, CH4, N2O
- carbon pools: SOC, aboveground biomass, belowground biomass, dead wood, litter
- land/ecosystem systems: cropland, grassland, forest/woodland, wetland, peatland, agroforestry, livestock
- intervention families: rotations, reduced/minimum tillage, residues, cover crops, soil/nutrient management, agroforestry, grassland restoration, woodland establishment, wetland restoration, peatland restoration
- indicators and methodology families
- additionality, leakage, permanence, reversal risk, uncertainty
- co-benefit concepts that require evidence rather than automatic positive scoring
- policy/economic context objects
- explicit relationship registry and deterministic domain fingerprint
- Research Librarian context packet interface without claiming v0.5.0 domain reasoning yet

## Guardrails

This release is a research knowledge foundation. It does not issue credits, certify projects, calculate SOC or whole-farm GHG balances, determine additionality/permanence, construct project MRV protocols, or treat a concept relationship as causal proof.

## WordPress

Shortcode: `[sc_carbon_nature_intelligence]`

Public proxy routes live under `/wp-json/sc-library/v1/carbon-nature/...`.

## Backend

Backend v2.2.0 routes:

- `GET /v1/carbon-nature`
- `GET /v1/carbon-nature/concepts`
- `GET /v1/carbon-nature/concepts/{concept_key}`
- `GET /v1/carbon-nature/relationships`
- `GET /v1/carbon-nature/research-context?q=...`

No PostgreSQL migration and no new credential are required.
