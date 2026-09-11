# Energy Systems Intelligence v0.8.0 — Global Energy Intelligence

## Release identity

- Sustainable Catalyst Library: **v5.11.0**
- Carbon & Nature Intelligence: **v0.5.0**
- Energy Systems Intelligence: **v0.8.0**
- Shared Library Python backend: **v2.14.0**
- Database migration: **none**
- New secrets: **none**

## Purpose

v0.8.0 adds a provenance-first global observation layer to Energy Systems Intelligence. It does not ship a frozen country database. Instead, it defines governed metric contracts and activates one live, read-only connector that can retrieve country/region time series while preserving the provider's observation year and metadata.

## Live data boundary

The first active connector is the World Bank Indicators API v2. The connector uses explicit indicator codes and Python standard-library HTTP only, so no new backend dependency or API secret is introduced. Requests are read-only, bounded by country code and year range, and cached only in-process for a short TTL.

Provider failure is surfaced as an error. The system does not fabricate fallback values, interpolate missing years, harmonize across unrelated sources, or relabel the latest observation as a current-year fact.

## Global metric registry

Nine metrics are registered:

| Sustainable Catalyst metric | Provider code | Unit / scope | Contextual EISD mapping |
| --- | --- | --- | --- |
| Access to electricity | `EG.ELC.ACCS.ZS` | % of population | SOC1 |
| Renewable energy consumption | `EG.FEC.RNEW.ZS` | % of total final energy consumption | ECO13 |
| Energy imports, net | `EG.IMP.CONS.ZS` | % of energy use | ECO15 |
| Energy use per capita | `EG.USE.PCAP.KG.OE` | kg oil equivalent per capita | ECO1 |
| Fossil fuel energy consumption | `EG.USE.COMM.FO.ZS` | % of total energy use | ECO11/ECO12 |
| Renewable electricity output | `EG.ELC.RNEW.ZS` | % of total electricity output | ECO13 |
| Electric power consumption per capita | `EG.USE.ELEC.KH.PC` | kWh per capita | ECO1 |
| Transmission and distribution losses | `EG.ELC.LOSS.ZS` | % of output | ECO4 |
| Energy productivity | `EG.GDP.PUSE.KO.PP.KD` | constant 2021 PPP $ per kg oil equivalent | ECO2 |

These mappings provide context only. They are not claims that World Bank series implement the official EISD methodology sheets.

## Source registry

Four source contracts are registered:

1. **World Bank WDI / Indicators API v2** — active, read-only, no authentication.
2. **Ember Energy Data API** — connector contract only; API key required; not activated.
3. **U.S. EIA API v2** — connector contract only; API key required; not activated.
4. **IEA data explorers** — reference/future connector; access and licensing remain dataset-specific.

## Runtime contracts

The backend exposes GET-only routes for the framework, sources, metrics, profile template, live country profile, and multi-country comparison. Country profiles retain series-level observations and the exact latest available year for each metric. Comparisons keep country-specific latest years rather than forcing values into an artificial common current year.

The portable country-profile contract requires provenance and is marked ready for downstream handoff to Site Intelligence, Lab, Workbench, and Decision Studio. Those separate applications are not claimed to execute the connector in this release.

## WordPress interface

`[sc_energy_systems_intelligence]` now opens on **Global Intelligence**. The interface provides:

- country profile lookup;
- metric-level time-series summaries and lightweight sparklines;
- source year and observation-lag visibility;
- one-metric country comparisons;
- the nine-metric registry;
- the source/connector registry.

The existing Bioenergy & Carbon, Scenario Economics, Energy Balance, Technologies & Resources, Sustainability Indicators, Numeric Registry, Knowledge Map, Concepts, Sources, and Handoffs remain available.

## Guardrails

- Latest available observation ≠ current-year fact.
- Missing values are not interpolated.
- Provider failure does not produce fabricated values.
- Cross-source harmonization is not assumed.
- Selected World Bank indicators are not asserted to be official EISD formulas.
- No automatic sustainability score, policy recommendation, or country ranking is produced.
- No current country observations are embedded in the repository or release bundle.

## Next release

**v0.9.0 — Energy Decision Intelligence** should turn the governed data, model, economics, bioenergy, and global-observation layers into evidence-bound comparison and decision packets without introducing automatic policy or technology recommendations.
