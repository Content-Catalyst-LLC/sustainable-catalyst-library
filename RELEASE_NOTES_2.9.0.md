# Library Backend v2.9.0

Adds Energy Systems Intelligence v0.3.0 indicator-definition and observation-contract endpoints while preserving v2.8.0 source-bound numerical capabilities and Carbon & Nature v0.5.0.

New GET endpoints:

- `/v1/energy-systems/indicator-framework`
- `/v1/energy-systems/indicators`
- `/v1/energy-systems/indicators/{indicator_code}`
- `/v1/energy-systems/indicator-observation-template/{indicator_code}`

Official EISD calculation remains disabled because the methodology sheets cited by the source article are not present in the supplied materials.
