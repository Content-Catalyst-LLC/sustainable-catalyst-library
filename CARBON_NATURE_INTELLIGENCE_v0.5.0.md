# Carbon & Nature Intelligence v0.5.0 — AFOLU Research Librarian Intelligence

## Release position

Carbon & Nature Intelligence remains a Library-native domain subsystem. Sustainable Catalyst Library stays on the v5.11.x application line while the Python Library backend advances to v2.6.0. v0.5.0 preserves the v0.1 AFOLU/Nature-Based Solutions ontology, v0.2 Carbon Sequestration Measure Registry, v0.3 Carbon Evidence & Methodology Graph, and v0.4 Carbon Project Object Model & Provenance.

## What v0.5.0 adds

v0.5.0 gives the Carbon & Nature subsystem a deterministic AFOLU research-intelligence layer. It does not generate scientific conclusions. Instead it interprets a research question against the governed domain model and returns a transparent routing packet containing detected research intents, research-question frames, source-role requirements, matched domain context, evidence gaps, freshness warnings, handoffs, and explicit answer boundaries.

Eleven governed research intents are included:

- identify AFOLU / nature-based measures;
- assess measure viability;
- compare measures without ranking them;
- interpret MRV and methodology questions;
- assess evidence and uncertainty;
- interpret national GHG inventory accounting;
- assess policy / mitigation-target contribution;
- interpret monetisation and carbon-finance questions;
- assess nature-based co-benefits and safeguards;
- structure project data and provenance;
- recognize natural-versus-engineered removal questions while explicitly flagging that the engineered-removal registry is not yet built.

Eight source roles define what evidence should be sought: authoritative methodology/technical guidance, peer-reviewed research, national inventory/policy sources, program/market rules, project primary data, spatial/environmental data, economic/finance evidence, and safeguards/stakeholder evidence.

## Freshness and evidence-gap intelligence

Time-sensitive research intents—especially national inventory, policy, program/market, spatial-data, and economic questions—carry a current-source requirement. The guidance packet flags matched registry sources that predate the current freshness window rather than silently treating older guidance as current law, policy, market rules, or data.

The engine also emits deterministic evidence-gap diagnostics. Examples include missing matched evidence, reference seeds that still require source retrieval, method context missing from an MRV/accounting question, project/jurisdiction-specific evidence requirements, stale source checks, and the not-yet-built engineered-removal registry.

These diagnostics identify research work still required; they are not truth judgments or evidence-quality grades.

## Research Librarian integration

The existing Carbon & Nature research-context packet advances to `sc-carbon-nature-research-context/1.4` and now embeds AFOLU Research Librarian intelligence.

The WordPress Carbon & Nature module also augments the existing private Project-Aware Research Librarian packet when the question is clearly AFOLU/carbon/nature related. Only the user's research question is sent to the first-party Library Python backend to obtain domain routing. Private project notes, notebooks, evidence matrices, source-bundle contents, claims, and other private project context are not sent to that backend by the Carbon & Nature augmentation. The optional remote Research Librarian synthesis handoff continues to receive only what the existing Research Librarian contract permits; the Carbon & Nature domain packet is not added to that remote handoff automatically.

## API surface

Backend v2.6.0 adds:

- `GET /v1/carbon-nature/research-librarian`
- `GET /v1/carbon-nature/research-librarian/intents`
- `GET /v1/carbon-nature/research-librarian/source-roles`
- `GET /v1/carbon-nature/research-librarian/guidance?q=...&limit=...`

The existing `GET /v1/carbon-nature/research-context` is preserved and enriched.

WordPress exposes read-only proxy routes under `sc-library/v1/carbon-nature/research-librarian/...` and the `[sc_carbon_nature_intelligence]` interface now opens on an AFOLU Research Librarian tab. Project Objects & Provenance, the Evidence & Methodology Graph, and the Measure Registry remain available as preserved secondary tabs.

## Boundaries

v0.5.0 does not:

- invent or calculate sequestration rates;
- rank measures or declare project suitability;
- approve or select methodologies;
- assert current policy, inventory, market, or program rules without current source verification;
- assume nature-based co-benefits;
- calculate SOC or whole-farm GHG outcomes;
- build or approve a project-specific MRV protocol;
- establish additionality, leakage, permanence, or project eligibility;
- verify or certify a project;
- issue or imply carbon credits.

## Next planned release

**v0.6.0 — Soil Organic Carbon Lab Foundation** begins the scientific-computation phase: explicit SOC stocks, horizons, depth, bulk density, concentration, baseline calculations, units, assumptions, and reproducible calculation packets. That work should consume the governed Library objects created in v0.1–v0.5 rather than duplicating them.
