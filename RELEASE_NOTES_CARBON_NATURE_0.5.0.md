# Release Notes — Carbon & Nature Intelligence v0.5.0

**Release:** AFOLU Research Librarian Intelligence  
**Library application:** 5.11.0  
**Library backend:** 2.6.0  
**Migration:** No PostgreSQL schema migration required  
**Credentials:** No new credentials required

## Added

- Eleven governed AFOLU / carbon / nature research-intent profiles.
- Eight governed research source-role profiles with source-specific minimum metadata and freshness sensitivity.
- Deterministic intent detection and multi-intent research routing.
- Research-question framing tied to the detected domain intents.
- Source-role planning for methodology guidance, peer-reviewed evidence, inventories/policy, market/program rules, project data, spatial data, economics, and safeguards.
- Evidence-gap diagnostics that distinguish missing evidence, source-retrieval needs, method gaps, project/jurisdiction dependencies, stale-source checks, and not-yet-built engineered-removal coverage.
- Time-sensitive source freshness review for current policy, inventory, market/program, economic, and data questions.
- Governed cross-product handoffs to Library, Research Librarian, Site Intelligence, Lab, Workbench, Decision Studio, and Workspace according to the research intent.
- Enriched `sc-carbon-nature-research-context/1.4` packets containing AFOLU Research Librarian intelligence.
- Read-only Research Librarian manifest, intent, source-role, and guidance backend APIs plus WordPress proxy routes.
- AFOLU Research Librarian tab in `[sc_carbon_nature_intelligence]` with intent, question-frame, source-plan, evidence-gap, freshness, handoff, and answer-contract views.
- Project-Aware Research Librarian packet augmentation for carbon/nature questions. The augmentation sends the question only to the Library backend and does not forward private project context.

## Preserved

- v0.1 AFOLU / Nature-Based Solutions knowledge foundation.
- v0.2 ten-profile Carbon Sequestration Measure Registry.
- v0.3 seven-profile methodology registry, four evidence/reference records, and typed evidence-methodology graph.
- v0.4 ten project object types, provenance-event registry, project links, packet template, deterministic fingerprints, and stateless structural validation.
- Sustainable Catalyst Library application identity remains 5.11.0.

## Guardrails

- Research routing is not a research conclusion.
- Intent detection is not an answer, ranking, suitability determination, methodology approval, or evidence-quality grade.
- Current policy, market, inventory, program, and data claims require current source verification.
- Co-benefits are not assumed.
- No SOC/GHG quantification, project MRV approval, project eligibility determination, certification, or credit issuance.
- The optional remote Research Librarian synthesis handoff does not automatically receive the Carbon & Nature domain packet.

## Validation

Release validation passes **22 v0.5-specific tests, 120 retained backend/Library regressions, and 26 retained public-interface regressions: 168 tests passed**, plus Python compile, PHP syntax, and JavaScript syntax checks.
