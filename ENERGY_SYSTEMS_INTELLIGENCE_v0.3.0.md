# Energy Systems Intelligence v0.3.0 — Energy Sustainability Indicators

## Purpose

v0.3.0 turns the indicator framework represented in the supplied Vera & Langlois (2007) article into governed Sustainable Catalyst objects without inventing methodology that is not present in the supplied source set.

## Preserved foundation

- Sustainable Catalyst Library: v5.11.0
- Carbon & Nature Intelligence: v0.5.0
- Energy Systems Intelligence knowledge foundation: 75 concepts, 63 typed relationships, 6 domains, 6 source records, 9 module SDG mappings
- v0.2.0 numeric registry: 8 units, 4 energy conversions, 24 historical direct-carbon factors, 16 gross heat-content factors

## New indicator registry

The release represents 30 EISD indicators:

- Social: SOC1-SOC4 (4)
- Economic: ECO1-ECO16 (16)
- Environmental: ENV1-ENV10 (10)

Classification preserves the article's three dimensions, seven themes, and nineteen subthemes.

## Methodology boundary

The supplied article states that separate methodology sheets describe definitions, alternative definitions, methods, components, units, construction instructions, data issues and sources, availability, and relevance to sustainable development. Those methodology sheets are not part of the supplied source set. Consequently, v0.3.0 stores indicator names/classifications and observation contracts but leaves official formula execution disabled.

## Observation contracts

Every indicator exposes a contract requiring provenance and methodology context. Common fields include geography, period, value, unit, data source, methodology reference, and quality note. Indicator-specific fields preserve denominator, disaggregation, system boundary, sector, fuel, pollutant, or threshold context where relevant.

An observation contract is not a claim that a value is correct, current, harmonized, or comparable.

## Interfaces

Backend:

- `GET /v1/energy-systems/indicator-framework`
- `GET /v1/energy-systems/indicators`
- `GET /v1/energy-systems/indicators/{indicator_code}`
- `GET /v1/energy-systems/indicator-observation-template/{indicator_code}`

WordPress:

- `[sc_energy_systems_intelligence]` opens on Sustainability Indicators
- matching GET-only `sc-library/v1/energy-systems/...` proxy routes

## Cross-product posture

The machine-readable registry and observation schemas can later support Site Intelligence, Workbench, Lab, and Decision Studio. v0.3.0 does not modify those separate products and does not assert current country indicator values.

## Next

v0.4.0 — Renewable Technology & Resource Model.
