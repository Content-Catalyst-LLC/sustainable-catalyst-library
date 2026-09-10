# Energy Systems Intelligence v0.1.0

## Sustainable Energy Knowledge Foundation

Energy Systems Intelligence v0.1.0 establishes a governed, read-only sustainable-energy domain inside Sustainable Catalyst Library. The release turns the supplied module scope and reading material into structured concepts, typed relationships, source provenance, SDG mappings, and explicit cross-platform handoffs without prematurely turning historical readings into current numerical truth.

## Release identity

- Sustainable Catalyst Library: **5.11.0**
- Carbon & Nature Intelligence: **0.5.0** retained
- Energy Systems Intelligence: **0.1.0**
- Library Python backend: **2.7.0**
- Database migration: **none**
- New secrets: **none**
- Data mode: **read-only knowledge foundation**

## Source basis

The v0.1.0 source registry records six provenance sources:

1. University College Dublin sustainable-energy module description and learning outcomes supplied for this build.
2. Prof. Kevin McDonnell, *Introduction to Sustainable Energy — Lecture 1*.
3. Ivan Vera and Lucille Langlois (2007), *Energy indicators for sustainable development*.
4. Carbon Trust, *Conversion factors: Energy and carbon conversions 2020 update*, based on BEIS 2020 factors.
5. Alan AtKisson (2009), *Pushing “Reset” on Sustainable Development*.
6. United Nations Department of Economic and Social Affairs (2010), *Trends in Sustainable Development: Towards Sustainable Consumption and Production*.

The source registry stores bibliographic/provenance metadata and bounded conceptual roles. It does **not** redistribute the source PDFs.

## Knowledge model

The foundation contains **75 concepts** and **63 typed relationships** across six domains:

- Energy & Sustainable Development
- Resources, Conversion & End Use
- Renewable Energy Technologies
- Biological Carbon Capture, Storage & Bioenergy
- Efficiency, Economics & Analysis
- Sustainability Metrics & Environmental Impacts

The concept registry includes the module's required historical/global framing; energy systems, sources, resources, reserves, forms, conversion and end use; fossil and renewable source families; solar PV, solar thermal, wind, hydro, tidal, wave, bioenergy and geothermal concepts; biological carbon pathways including soil carbon, forest carbon/ecology, anaerobic digestion, digestate, biochar, biomass-to-oil and CO2-to-energy; energy efficiency, balance, cost-benefit and cost-efficiency concepts; and EISD-derived accessibility, affordability, intensity, security, mix, resource/reserve ratio, emissions, air/water/land, and decoupling concepts.

Relationships are source-keyed and explicitly `inference_allowed = false`. A graph edge records a source-grounded association; it does not establish causation, project suitability, policy validity, or technology superiority.

## Source-vintage boundary

The 2007, 2009, 2010 and 2020 readings are retained with their years. Historical numerical values are not promoted to current platform truth. In particular, the Carbon Trust/BEIS 2020 conversion guide is registered as an `inactive-historical-numeric-source`.

v0.1.0 contains the concepts `energy-unit-conversion`, `calorific-value`, and `emission-factor`, but contains no active table of historical conversion factors. The versioned numerical registry is reserved for **v0.2.0 — Energy Units, Carbon Factors & Conversion Registry**.

## Carbon & Nature integration

Two existing Carbon & Nature targets are available as semantic handoffs:

- `soil-carbon` -> `soil-organic-carbon`
- `forest-carbon` / `forest-ecology` -> `forest-woodland`

The release does not fabricate targets for capabilities that Carbon & Nature v0.5.0 does not yet expose. Anaerobic digestion/digestate, biochar, biomass-to-oil and CO2-to-energy therefore carry `planned-extension` status with empty target references.

## SDG mapping

The module-supplied coverage values are preserved exactly for SDGs 4, 6, 7, 9, 11, 12, 13 and 14. The supplied excerpt also lists SDG 15 but does not show a numeric coverage value; v0.1.0 stores that coverage as `null` rather than inferring a value.

## API

Backend GET routes:

- `/v1/energy-systems`
- `/v1/energy-systems/concepts`
- `/v1/energy-systems/concepts/{concept_key}`
- `/v1/energy-systems/relationships`
- `/v1/energy-systems/sources`
- `/v1/energy-systems/sources/{source_key}`
- `/v1/energy-systems/knowledge-map`
- `/v1/energy-systems/handoffs`

WordPress mirrors the same read-only domain under `/wp-json/sc-library/v1/energy-systems...`.

## Public interface

Shortcode:

```text
[sc_energy_systems_intelligence]
```

The interface provides four views: Knowledge Map, Concept Registry, Sources & Provenance, and Platform Handoffs. It is deliberately a knowledge/research surface rather than an engineering calculator.

## Guardrails

v0.1.0 explicitly prevents or disclaims:

- treating concept matches as evidence;
- treating graph relationships as causal proof;
- interpreting a renewable label as zero environmental impact;
- treating historical sources as current-state evidence;
- inferring energy-resource or reserve values;
- activating historical conversion factors;
- calculating sustainability indicators;
- running energy scenarios;
- automatically ranking energy technologies;
- automatically recommending policy; and
- generating a single automatic sustainability score.

## Roadmap handoffs

- **v0.2.0** — Energy Units, Carbon Factors & Conversion Registry
- **v0.3.0** — Energy Sustainability Indicators
- **v0.4.0** — Renewable Technology & Resource Model
- **v0.5.0** — Energy Balance & Systems Modeling
- **v0.6.0** — Energy Scenario Economics

Later releases can activate governed Workbench calculations, Lab modeling, Site Intelligence country/resource views, and Decision Studio analysis without changing the source/provenance boundary established here.
