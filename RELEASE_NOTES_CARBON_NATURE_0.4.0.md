# Release Notes — Carbon & Nature Intelligence v0.4.0

**Release:** Carbon Project Object Model & Provenance  
**Library application:** 5.11.0  
**Library backend:** 2.5.0  
**Migration:** No PostgreSQL schema migration required  
**Credentials:** No new credentials required

## Added

- Ten governed Carbon Project object-type profiles covering projects, farms, parcels, baselines, interventions, observations, samples, model runs, monitoring records, and verification records.
- A common versioned project-object envelope with stable identity, lifecycle status, source/provenance references, external measure/methodology/evidence references, and deterministic content fingerprints.
- Nine provenance-event types and optional previous-event fingerprint chaining.
- Nine explicit, non-inferential project link predicates with type constraints.
- Read-only project object-model, object-type, provenance-event, and packet-template APIs.
- Signed, bounded, stateless project-packet validation on the existing Library server-to-server authorization boundary.
- Project-object and provenance hints in Carbon & Nature Research Librarian context packets.
- A new Project Objects & Provenance tab in `[sc_carbon_nature_intelligence]` while preserving the v0.2 Measure Registry and v0.3 Evidence & Methodology Graph.

## Preserved guardrails

- No project-packet persistence.
- No automatic project claim generation or eligibility determination.
- No automatic methodology selection/eligibility, evidence-quality grading, or claim validation.
- No sequestration or whole-farm GHG calculation.
- No MRV protocol approval, certification, credit issuance, or digital-signature service.
- Object/provenance validation is structural integrity validation, not scientific or regulatory verification.

## Validation

Release validation passes 16 v0.4-specific tests, 120 retained backend/Library regressions, and 26 retained public-interface regressions, plus Python compile, PHP syntax, and JavaScript syntax checks: **162 tests passed** across the governed validation runner.
