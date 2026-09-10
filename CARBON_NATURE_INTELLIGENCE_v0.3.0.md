# Carbon & Nature Intelligence v0.3.0 — Carbon Evidence & Methodology Graph

## Product boundary

Carbon & Nature Intelligence remains a Library-native domain subsystem. Sustainable Catalyst Library stays on the v5.11.x application line while Carbon & Nature advances from v0.2.0 to v0.3.0. The Library backend advances from v2.3.0 to v2.4.0.

v0.3.0 preserves the AFOLU/Nature-Based Solutions ontology and Carbon Sequestration Measure Registry, then adds a governed evidence-methodology layer between Library knowledge and later scientific/project tooling.

## What v0.3.0 adds

The release introduces four linked object families:

- Carbon & Nature concepts from v0.1.0
- Carbon sequestration measures from v0.2.0
- methodology profiles describing measurement, sampling, modeling, hybrid MRV, biomass inventory, wetland/peatland multi-gas monitoring, and whole-system GHG accounting
- evidence records that preserve source identity, authority class, year/version context, jurisdiction, scope, and explicit links to measures, concepts, and methodologies

Typed graph edges connect those objects without enabling automatic inference. Every graph edge retains an explicit subject type, subject key, predicate, object type, object key, provenance requirement, and `inference_allowed=false` guardrail.

## Initial methodology profiles

1. SOC Direct Measurement
2. SOC Sampling Design
3. Modeled Carbon Stock Change
4. Hybrid Measurement & Modeling MRV
5. Biomass Inventory Measurement
6. Wetland & Peatland Multi-Gas Monitoring
7. Whole-System GHG Accounting

These are governed methodology profiles and research-navigation objects. They are not universal protocols, certifications, or project-eligibility determinations.

## Initial evidence records

The graph seeds reference objects for:

- the 2021 EU carbon-farming technical-guidance handbook used to establish the domain build path
- the 2006 IPCC Guidelines for National Greenhouse Gas Inventories, Volume 4: AFOLU
- the 2019 Refinement to the 2006 IPCC Guidelines, AFOLU context
- a Sustainable Catalyst measure-specific research-evidence bundle template for future source ingestion

Reference presence does not establish endorsement, current regulatory entitlement, methodology approval, or claim validity. Version, jurisdiction, project context, and source rights remain review requirements.

## New backend surfaces

Backend v2.4.0 adds:

- `GET /v1/carbon-nature/evidence`
- `GET /v1/carbon-nature/evidence/{evidence_key}`
- `GET /v1/carbon-nature/methodologies`
- `GET /v1/carbon-nature/methodologies/{methodology_key}`
- `GET /v1/carbon-nature/evidence-graph`
- `GET /v1/carbon-nature/evidence-graph/neighborhood/{node_key}`

Existing manifest, concept, relationship, measure, comparison, and research-context routes remain available. Measure detail now carries linked methodology and evidence context. Research-context packets advance to `sc-carbon-nature-research-context/1.2` and include concepts, measures, methodologies, evidence records, and graph edges.

## WordPress

The existing shortcode remains:

`[sc_carbon_nature_intelligence]`

The interface now opens on the Carbon Evidence & Methodology Graph and preserves the v0.2.0 Measure Registry as a second explorer. The browser still calls WordPress only; WordPress proxies bounded read requests to the Library backend.

## Guardrails

v0.3.0 does **not**:

- automatically validate scientific or carbon-market claims
- automatically grade evidence quality
- select an approved methodology or determine methodology eligibility
- determine project suitability, additionality, leakage, permanence, or credit eligibility
- provide generic tCO2e/ha/yr sequestration values
- calculate SOC or whole-farm GHG balances
- construct a project-specific MRV protocol
- issue carbon credits or represent Sustainable Catalyst as a verification/certification body

The evidence graph expresses governed associations and research context. Scientific calculation belongs in later Lab/Workbench releases; project eligibility and decision logic belong in later Decision Studio/MRV releases.

## Migration

No PostgreSQL migration and no new credentials are required. v0.3.0 continues to use deterministic application data so the evidence/methodology graph contract can stabilize before v0.4.0 introduces Carbon Project objects and persisted provenance.
