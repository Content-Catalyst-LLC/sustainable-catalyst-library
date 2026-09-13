# Energy Systems Intelligence v1.6.0 — Grid, Storage & Reliability Analysis

This release adds governed grid-topology, storage-operation, adequacy, and reliability contracts. Library remains the evidence/contract layer. Workbench 6.3.0 performs deterministic calculations only from explicit inputs. Lab 0.103.0 plans and analyzes seeded adequacy uncertainty studies without automatically invoking Workbench. Site Intelligence 4.41.0 remains the spatial evidence source and is unchanged.

## Added
- Grid topology evidence contract (connectivity only; not power-flow).
- Storage operating scenario contract with explicit capacity, state-of-charge, power, efficiency, and timestep inputs.
- Reliability evidence contract covering reserve margin, peak-demand coverage, loss-of-load hours/events, energy not served, and maximum shortfall.
- Seven governed Workbench operations.
- Seeded Lab adequacy uncertainty contract.

## Boundaries
No hidden storage defaults, inferred forced-outage rates, automatic outage prediction, real-grid reliability declaration, unit commitment, economic dispatch, technology ranking, or recommendation is enabled.
