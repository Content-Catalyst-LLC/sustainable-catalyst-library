# Release Notes — Energy Systems Intelligence v0.4.0

**Release:** Renewable Technology & Resource Model  
**Library:** v5.11.0 (unchanged)  
**Carbon & Nature:** v0.5.0 (preserved)  
**Library backend:** v2.10.0

## Added

- Seven governed renewable technology objects: solar photovoltaic, solar thermal, wind, hydropower, tidal, wave, and bioenergy.
- Six normalized renewable resource classes: solar, wind, hydrological, tidal, wave, and biomass feedstock.
- One provenance-first technology-assessment contract per technology.
- One provenance-first resource-observation contract per resource class.
- Renewable technology comparison schema with explicit ranking disabled.
- GET-only backend routes and matching WordPress REST facades for technology/resource discovery and contract retrieval.
- A Technologies & Resources default view in `[sc_energy_systems_intelligence]`.
- JSON Schemas for technology definitions, resource classes, technology assessments, and resource observations.
- Machine-readable renewable technology/resource export.
- Contract-ready handoffs to Lab and Site Intelligence.
- Two methodology guardrails separating technology class/resource observation from performance, potential, and suitability claims.

## Preserved

- 75 Energy Systems concepts and 63 typed relationships.
- Source-bound v0.2.0 unit, conversion, direct-carbon, and heat-content registries/calculators.
- All 30 v0.3.0 EISD definitions and observation contracts.
- Carbon & Nature Intelligence v0.5.0.
- Sustainable Catalyst Library v5.11.0 application line.

## Explicit non-capabilities

- No current renewable-resource-potential dataset.
- No populated universal efficiency, capacity-factor, output, cost, lifecycle-emissions, land, water, or maturity profile.
- No automatic site suitability, technology ranking, project feasibility, scenario modeling, or policy recommendation.
- No claim that renewable classification means zero impact.
