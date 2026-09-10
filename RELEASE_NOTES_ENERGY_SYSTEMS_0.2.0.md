# Energy Systems Intelligence v0.2.0

## Energy Units, Carbon Factors & Conversion Registry

This release preserves the v0.1.0 Sustainable Energy Knowledge Foundation and adds a governed, source-bound numerical registry derived only from the supplied 2020 Carbon Trust/BEIS conversion guide.

### Added

- 8 unit records used by the activated registry.
- 4 quoted energy-unit conversions to kWh: therm, Btu, MJ and toe.
- 24 direct kgCO2e factors for UK grid electricity and listed fuels.
- 16 gross calorific-value factors for listed fuels.
- 5 methodology rules covering direct/indirect boundaries, CO2e aggregation, renewable-electricity accounting, and gross calorific basis.
- Read-only backend and WordPress REST routes for registry discovery and source-bound calculations.
- A Numeric Registry tab in `[sc_energy_systems_intelligence]` with energy conversion, direct-carbon, and gross-heat-content calculators.
- A Workbench-ready handoff contract without modifying or claiming execution in the separate Workbench product.

### Provenance boundary

All activated numerical records are bound to `carbon-trust-conversion-2020`, source year 2020. The registry does not promote those values to present-day defaults. Direct emissions are kept distinct from indirect/lifecycle emissions. The renewable-electricity methodology rule is retained without inventing a universal zero or green-tariff factor.

### Preserved

- Sustainable Catalyst Library v5.11.0.
- Carbon & Nature Intelligence v0.5.0.
- 75 Energy Systems concepts.
- 63 source-grounded typed relationships.
- Six Energy Systems knowledge domains, six provenance sources, module SDG mappings, and governed platform handoffs.

### Not included

- Current grid or fuel factors.
- Automatic source refresh.
- EISD indicator calculation.
- Energy-scenario modeling or optimization.
- Technology ranking or policy recommendation.
- Direct execution inside Workbench.

The shared Library Python backend advances from v2.7.0 to v2.8.0.
