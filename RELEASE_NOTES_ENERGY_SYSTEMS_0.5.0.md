# Release Notes — Energy Systems Intelligence v0.5.0

**Release:** Energy Balance & Systems Modeling  
**Library:** v5.11.0 (unchanged)  
**Carbon & Nature:** v0.5.0 (preserved)  
**Library backend:** v2.11.0

## Added

- Four-model Energy Balance & Systems registry with three executable deterministic models and one portable scenario contract.
- Conversion-chain calculation with explicit stage efficiencies, stage labels, stage outputs, stage losses, final output, total loss, and overall efficiency.
- Supply–demand energy accounting across domestic supply, imports, storage discharge, final demand, exports, storage charge, declared losses, tolerance, and residual.
- Capacity-factor generation estimate from explicit capacity, capacity factor, and hours.
- Portable balance-scenario contract referencing the seven governed renewable technology keys.
- GET-only backend routes and matching WordPress REST facades for all new calculations and contracts.
- Energy Balance default interface in `[sc_energy_systems_intelligence]`.
- Four JSON Schemas and a machine-readable Energy Balance model export.
- Computational handoff contracts for later Lab and Workbench integration.
- Explicit guardrails separating accounting/scenario arithmetic from forecasting, dispatch, reliability, optimization, ranking, and policy recommendation.

## Preserved

- 75 Energy Systems concepts and 63 typed relationships.
- Source-bound v0.2.0 unit, conversion, direct-carbon, and heat-content registries/calculators.
- All 30 v0.3.0 EISD definitions and observation contracts.
- All seven v0.4.0 renewable technology objects and six resource classes.
- Carbon & Nature Intelligence v0.5.0.
- Sustainable Catalyst Library v5.11.0 application line.

## Explicit non-capabilities

- No inferred technology efficiency or capacity factor.
- No current renewable-resource dataset.
- No time-series dispatch, unit commitment, market clearing, storage-physics simulation, or grid reliability/adequacy model.
- No economic optimization or scenario persistence.
- No automatic site suitability, technology ranking, or policy recommendation.
- Scenario results are not forecasts.
