# Energy Systems Intelligence v0.9.0 — Energy Decision Intelligence

## Release identity

- Sustainable Catalyst Library: **v5.11.0**
- Carbon & Nature Intelligence: **v0.5.0**
- Energy Systems Intelligence: **v0.9.0**
- Shared Library Python backend: **v2.15.0**
- Database migration: **none**
- New secret/API key: **none**

## What v0.9.0 adds

Energy Decision Intelligence converts the existing Energy Systems stack into portable, evidence-bound comparison packets without turning the platform into an automatic decision-maker.

The release adds:

- 12 governed decision criteria across 9 dimensions;
- a portable decision packet contract with identity, decision context, alternatives, criteria observations, evidence references, uncertainty, assumptions, gaps, and open questions;
- a neutral comparison matrix that displays values exactly as supplied;
- unit- and period-compatibility flags;
- a readiness inspector for identity completeness, provenance coverage, uncertainty coverage, observed criteria, explicit gaps, and comparison incompatibilities;
- Decision Studio handoff references for packet, matrix, and readiness contracts;
- a WordPress Decision Intelligence surface inside `[sc_energy_systems_intelligence]`;
- four JSON Schemas and a machine-readable decision registry export.

## Decision dimensions

The twelve criteria cover system performance, economics, social access, energy security, energy mix, climate, environmental/resource context, system integration, and implementation/governance. Each criterion retains evidence references back to the existing Energy Systems layers such as v0.5.0 balance models, v0.6.0 economics, v0.7.0 bioenergy/carbon bridges, v0.8.0 country context, and the v0.3.0 sustainability indicator registry.

## Explicit boundary

The comparison matrix is **not** a decision, score, ranking, winner, or recommendation. v0.9.0 does not normalize unlike quantities, assign hidden weights, infer preferences, calculate a composite sustainability score, select a technology, recommend an investment, or make a policy choice. Readiness measures packet structure and evidence visibility only; it is not an assessment of which alternative is better.

## GET-contract boundary

To preserve the existing read-only Energy Systems surface, v0.9.0 accepts a compact JSON decision packet through bounded GET query parameters for matrix/readiness inspection. The packet is not persisted. Larger/persistent decision workflows belong in Decision Studio integration rather than the Library facade.

## Preserved capabilities

All v0.1.0-v0.8.0 Energy Systems capabilities remain present, including the knowledge foundation, numeric registry, EISD indicator definitions, renewable technology/resource model, energy balance models, scenario economics, biological carbon/bioenergy integration, and Global Energy Intelligence.
