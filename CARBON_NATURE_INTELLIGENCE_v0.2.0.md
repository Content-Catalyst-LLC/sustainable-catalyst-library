# Carbon & Nature Intelligence v0.2.0 — Carbon Sequestration Measure Registry

## Product boundary

Carbon & Nature Intelligence remains a Library-native domain subsystem. Sustainable Catalyst Library stays on the v5.11.x application line while the Carbon & Nature subsystem advances from v0.1.0 to v0.2.0. The Library backend advances from v2.2.0 to v2.3.0.

## What v0.2.0 adds

The release turns the v0.1.0 AFOLU and Nature-Based Solutions ontology into a structured measure registry. Each measure profile has a stable measure key and explicit links to:

- measure family and primary domain
- intervention concepts and applicable land/agricultural systems
- target carbon pools
- relevant greenhouse gases
- outcome types
- MRV methodology families
- additionality, leakage, permanence, and uncertainty review dimensions
- co-benefit contexts and risk contexts
- evidence requirements and implementation metadata

## Initial registry coverage

1. Improved Crop Rotation
2. Cover Crop System
3. Reduced or Minimum Tillage
4. Crop Residue Management
5. Soil & Nutrient Management
6. Agroforestry Establishment & Management
7. Grassland Restoration & Improved Management
8. Woodland Establishment
9. Wetland Restoration
10. Peatland Restoration & Rewetting

The registry is deliberately a governed knowledge layer rather than a table of generic sequestration rates. A measure can be discoverable without being suitable, additional, permanent, quantifiable, creditable, or methodologically eligible for a specific project.

## New backend surfaces

Backend v2.3.0 adds:

- `GET /v1/carbon-nature/measures`
- `GET /v1/carbon-nature/measures/{measure_key}`
- `GET /v1/carbon-nature/measures/compare?keys=...`

Existing concept, relationship, manifest, and research-context routes remain available. Research-context packets now include bounded measure matches as well as concepts.

## WordPress

The existing shortcode remains:

`[sc_carbon_nature_intelligence]`

The interface now opens the measure registry by default and provides bounded filters for family, land system, carbon pool, and greenhouse gas. The browser continues to call WordPress only; WordPress proxies read requests to the Library backend.

## Guardrails

v0.2.0 does **not**:

- rank measures or choose a preferred intervention
- provide generic tCO2e/ha/yr values
- calculate SOC or whole-farm GHG balances
- determine project suitability, additionality, leakage, or permanence
- select or certify an MRV methodology
- issue carbon credits or represent Sustainable Catalyst as a verification/certification body
- infer that a co-benefit exists because a measure is associated with a co-benefit context

Project-specific scientific and economic evaluation belongs in later Lab, Workbench, Decision Studio, and MRV releases.

## Migration

No PostgreSQL migration and no new credentials are required. The measure registry is deterministic application data in v0.2.0 so the schema can mature before persistence and project-object modeling are introduced.
