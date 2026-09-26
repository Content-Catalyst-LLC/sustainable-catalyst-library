# Knowledge Library v5.30.0 — Evidence Quality & Methodology Intelligence

**WordPress:** 5.30.0  
**Python backend:** 2.41.0

## Purpose

v5.30.0 makes research methodology a first-class, provenance-aware Library object without turning study design or metadata completeness into a universal evidence-quality ranking.

The Library structures methodology **only from explicit supported fields** supplied by ingestion, parser, connector, registry, or reviewed metadata. It does not infer missing methods from a title, abstract, chart, or graph topology.

## Structured methodology profile

A profile can preserve explicitly reported:

- study design / study type
- study population / participants / cohort
- sample size
- geography and study sites
- study / observation / follow-up period
- interventions, exposures, treatments
- comparators / control groups
- outcomes / endpoints
- variables / covariates / predictors
- statistical / analytical methods and models
- uncertainty reporting
- randomization and masking
- limitations / caveats
- funding / conflicts / competing interests
- preregistration / trial or protocol registration
- data availability
- code / software availability
- supplementary / replication materials
- replication state when explicitly supplied
- ethics / IRB metadata

Each normalized field retains the source field path used to construct it.

## Descriptive design families

The runtime maps explicit design labels into neutral descriptive families such as evidence synthesis, randomized interventional, quasi-experimental, observational cohort, case-control, cross-sectional, qualitative, mixed-methods, computational/modeling, econometric, and descriptive.

These are **not** an evidence hierarchy and are never assigned a quality rank.

## Method reporting coverage

The Library calculates documentation coverage across fourteen reporting dimensions. This measures how much supported structured methodology information is present in the Library record. It does **not** measure whether the methods were scientifically appropriate or correctly executed.

## Appraisal readiness

Profiles expose review-readiness signals and domains that require human appraisal. v5.30.0 does not generate:

- a formal quality grade
- a formal risk-of-bias judgment
- a causal-validity judgment
- a truth score
- a recommendation

## Research graph integration

Records with supported methodology receive `methodology-profile` nodes connected with `describes-methodology` edges. These relations are queryable research structure but are intentionally excluded from default evidence-path traversal.

This prevents a shared design, larger sample, richer reporting, or common method from being interpreted as support for a claim.

## Platform boundary

Knowledge Library owns extraction, normalization, provenance, comparison, and methodology-document intelligence. Platform Core remains the durable authority for governed research/evidence objects, claims, findings, synthesis, uncertainty, causal/statistical reasoning objects, and cross-product exchange.

## Integrity rules

- Reporting coverage is not quality.
- Study-design family is not an evidence hierarchy.
- A methodology profile does not determine truth.
- A methodology profile does not prove causality.
- Missing structured metadata does not prove a method was absent.
- Human methodological appraisal remains required.
