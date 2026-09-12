# Energy Systems Intelligence v1.3.0 — Energy Workbench Runtime

Energy Systems Intelligence v1.3.0 advances the cross-product runtime from validated target intake to explicit calculation execution in Sustainable Catalyst Workbench v6.2.0.

## Activated runtime

Workbench accepts the existing `sc-energy-runtime-workbench-handoff/1.0` packet and exposes explicit planning, execution, and result-validation routes. Fourteen operations are supported across unit conversion, energy balance and generation, scenario economics, and bioenergy/carbon arithmetic.

## Execution boundary

`POST /v1/energy-runtime/consume` remains intake-only. Execution occurs only through `POST /v1/energy-runtime/execute`. Missing values are rejected rather than inferred. The runtime does not fetch market prices, persist studies, rank alternatives, select winners, make recommendations, infer avoided emissions, or issue carbon-credit claims.

## Release identity

- Energy Systems Intelligence: 1.3.0
- Library backend: 2.19.0
- Library WordPress plugin: 5.11.0 (Energy Systems subsystem 1.3.0)
- Workbench: 6.2.0

Other v1.2 target runtimes remain unchanged.
