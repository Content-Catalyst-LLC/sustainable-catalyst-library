# Energy Systems Intelligence v0.5.0 — Energy Balance & Systems Modeling

## Purpose

v0.5.0 moves Energy Systems Intelligence from structured knowledge and assessment contracts into bounded computation. It adds deterministic energy-balance arithmetic that operates only on explicit scenario inputs while preserving provenance, model boundaries, and the prior renewable, indicator, and source-bound numeric layers.

The release is intentionally not a power-system dispatch model. It does not infer technology efficiency, capacity factor, storage behavior, resource availability, grid reliability, costs, or preferred technologies from names or categories.

## Preserved foundation

- Sustainable Catalyst Library: v5.11.0
- Carbon & Nature Intelligence: v0.5.0
- Energy Systems v0.1.0: 75 concepts, 63 typed relationships, six domains, provenance sources, SDG mappings
- Energy Systems v0.2.0: source-bound units, conversion factors, historical direct-carbon factors, and gross heat-content factors
- Energy Systems v0.3.0: 30 EISD definitions and provenance-first observation contracts
- Energy Systems v0.4.0: seven renewable technology objects, six resource classes, and technology/resource assessment contracts

## Energy balance model registry

Four governed model definitions are active:

1. **Conversion chain** — applies one or more explicit efficiency percentages to an initial energy input and returns stage-by-stage output and loss.
2. **Supply–demand balance** — evaluates a transparent accounting identity across supply, imports, storage discharge, final demand, exports, storage charge, declared losses, and residual.
3. **Capacity-factor generation estimate** — calculates energy from explicit capacity, capacity factor, and hours.
4. **Energy balance scenario contract** — provides a portable scenario structure for future Lab, Workbench, and Decision Studio handoffs.

Three models execute deterministic arithmetic; the scenario contract is structural and non-persistent.

## Conversion-chain model

The model uses:

`stage output = stage input × stage efficiency`

`stage loss = stage input − stage output`

The next stage receives the prior stage output. Final output, total declared conversion loss, and overall chain efficiency are returned. Efficiencies are supplied by the user or a future evidence-backed scenario; the model never inserts technology-specific defaults.

## Supply–demand balance

Available supply is defined as:

`domestic supply + imports + storage discharge`

Accounted outflows are defined as:

`final demand + exports + storage charge + declared losses`

The residual is:

`available supply − accounted outflows`

A user-supplied tolerance determines whether the accounting balance is considered closed. A positive residual is an unallocated surplus and a negative residual is an unaccounted deficit. This is an accounting identity, not a grid reliability, adequacy, dispatch, or market-clearing model.

## Capacity-factor generation estimate

The deterministic calculation is:

`generation = capacity × hours × capacity factor`

Capacity factor is an explicit scenario input and is not inferred from renewable technology type, geography, or historical data. The result is therefore a scenario calculation, not a forecast.

## Portable scenario contract

The scenario contract organizes:

- identity, geography, and period;
- domestic supply, imports, storage discharge, and technology entries;
- conversion chains and declared losses;
- final demand, exports, storage charge, and end-use breakdown;
- balance tolerance and residual accounting;
- source, methodology, factor, resource-observation, and indicator-observation references;
- assumptions, sensitivity parameters, and quality notes.

The contract references all seven renewable technology keys from v0.4.0. Persistence, optimization, ranking, and recommendation are explicitly disabled.

## Cross-product handoffs

- **Lab:** portable energy-balance scenario and conversion-chain contracts are now available for later model execution and uncertainty/sensitivity work.
- **Workbench:** deterministic energy-balance calculation contracts are now available for later calculator integration.
- **Site Intelligence:** renewable resource-observation contracts remain available for future geographically bound inputs.
- **Decision Studio:** remains a later consumer for trade-off and policy decision packets; v0.5.0 does not generate recommendations.

This release does not modify those separate products.

## Interfaces

Backend GET routes:

- `/v1/energy-systems/balance-framework`
- `/v1/energy-systems/conversion-chain`
- `/v1/energy-systems/supply-demand-balance`
- `/v1/energy-systems/generation-estimate`
- `/v1/energy-systems/balance-scenario-template`

WordPress:

- `[sc_energy_systems_intelligence]` now opens on **Energy Balance**.
- Technologies & Resources, Sustainability Indicators, Numeric Registry, Knowledge Map, Concept Registry, Sources & Provenance, and Platform Handoffs remain available.

## Explicit non-capabilities

- No technology-specific efficiency or capacity-factor inference.
- No current renewable-resource data ingestion.
- No time-series dispatch or unit-commitment model.
- No storage-physics simulation.
- No grid reliability or adequacy calculation.
- No economic optimization in this release.
- No persistent scenario store.
- No automatic technology ranking or policy recommendation.
- Model output is not a forecast or site-suitability determination.

## Next

v0.6.0 — Energy Scenario Economics.
