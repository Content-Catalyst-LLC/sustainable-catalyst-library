# Energy Systems Intelligence v0.2.0

## Purpose

Turn the supplied energy and carbon-conversion material into governed Sustainable Catalyst infrastructure rather than static notes. v0.2.0 adds a numerical registry while preserving the knowledge graph introduced in v0.1.0.

## Registry model

Each numerical record carries a stable key, unit, source key, source year, geography/source context, status, and methodology note. Carbon factors additionally carry an emissions boundary and accounting-scope note. Heat-content factors carry a calorific-value basis.

The activated source is explicitly historical:

```text
source_key: carbon-trust-conversion-2020
source_year: 2020
status: historical-source-bound
current_default: false
```

## Activated unit conversions

The source supplies conversion factors to kWh for therms, Btu, MJ, and tonnes of oil equivalent. Reverse and cross-unit conversions are derived arithmetically through kWh and report that derivation path.

## Activated carbon factors

The registry contains direct kgCO2e-per-unit records for UK grid electricity, natural gas, LPG, gas oil, fuel oil, burning oil, diesel, petrol, industrial coal, and wood pellets where the supplied table provides a numeric value.

The registry does not create a numeric renewable-electricity factor because the supplied guide instead gives location-based/market-based accounting guidance.

## Activated heat-content factors

The registry contains default gross calorific values for the unambiguous values supplied in the guide. Supplier-specific values take precedence where the source directs. Ambiguous parsed volume values are not activated.

## Calculation contracts

```text
GET /v1/energy-systems/convert
GET /v1/energy-systems/carbon-estimate
GET /v1/energy-systems/heat-content-estimate
```

Calculations require explicit units or factor keys. There is no hidden "latest" factor selection.

## Governance

A successful calculation means only that the arithmetic correctly applies the selected source-bound record. It does not establish a current emissions inventory, lifecycle footprint, Scope 3 value, technology suitability, or policy conclusion.

## Next

v0.3.0 — Energy Sustainability Indicators.
