# Carbon & Nature Intelligence v0.4.0 — Carbon Project Object Model & Provenance

## Release position

Carbon & Nature Intelligence remains a Library-native domain subsystem. Sustainable Catalyst Library stays on the v5.11.x application line while the Python Library backend advances to v2.5.0. v0.4.0 preserves the v0.1 AFOLU/Nature-Based Solutions ontology, v0.2 Carbon Sequestration Measure Registry, and v0.3 Carbon Evidence & Methodology Graph.

## What v0.4.0 adds

v0.4.0 establishes a stable project-data contract so later Site Intelligence, Lab, Workbench, Decision Studio, Workspace, and Research Librarian capabilities can refer to the same research objects without collapsing evidence, measurement, modeling, review, and project claims into one record type.

The governed project object registry contains ten object types:

- `project` — root project scope, jurisdiction, and boundary;
- `farm` — managed agricultural unit;
- `parcel` — stable spatial research unit;
- `baseline` — bounded pre-intervention or counterfactual state;
- `intervention` — project-specific implementation linked to a governed measure key;
- `observation` — timestamped measured or reported value with unit and method context;
- `sample` — physical/analytical sample identity with collection and custody context;
- `model-run` — reproducible model execution with inputs, assumptions, software identity, and outputs;
- `monitoring-record` — bounded monitoring-period packet linking observations, samples, methods, and QA/QC;
- `verification-record` — human review/verification record with scope, reviewer reference, evidence considered, finding, and limitations.

Every project object uses a common versioned envelope containing stable `object_id`, `object_type`, root `project_id`, version, lifecycle status, payload, source references, provenance-event references, and optional parent/evidence/methodology/measure links. Deterministic SHA-256 content fingerprints are available for integrity checking.

## Provenance model

The provenance registry defines nine explicit event types: created, imported, observed, sampled, transformed, modeled, reviewed, verified, and superseded. Events can carry a `previous_event_fingerprint` so packet validation can detect provenance-chain breaks for the same object while retaining prior history.

Fingerprints are integrity checks, not digital signatures. `actor_ref` is an identifier, not proof of identity. Review or verification events do not by themselves establish certification, methodology eligibility, project eligibility, or carbon-credit entitlement.

## Project links

Nine non-inferential link predicates describe explicit project relationships: contains, baseline-for, intervention-on, observation-of, sample-of, input-to-model-run, derived-from, monitoring-for, and verification-of. Link profiles constrain allowed subject/object types and never imply causal proof.

## Stateless project packet validation

Backend v2.5.0 includes a signed server-to-server `POST /v1/carbon-nature/project-packets/validate` route. Validation is bounded and stateless. It checks:

- packet schema and collection limits;
- one root project and project-ID continuity;
- unique object/event/link identifiers;
- required payload fields by object type;
- lifecycle state and parent-type constraints;
- governed measure and methodology references;
- provenance-event/object continuity;
- optional object and event fingerprint consistency;
- optional same-object provenance-chain continuity;
- project-link endpoint and type constraints.

The validator returns structural errors, unresolved-reference warnings, deterministic object/event fingerprints, and a packet fingerprint. It does not persist the packet.

## Public Library surface

`[sc_carbon_nature_intelligence]` now opens on a Project Objects & Provenance explorer. The retained Evidence & Methodology Graph and Measure Registry remain available as secondary tabs. Public WordPress routes expose the read-only object model, object-type registry, provenance-event registry, and packet template. The signed validation endpoint remains a backend server-to-server capability rather than an anonymous browser write surface.

## Boundaries

v0.4.0 does not calculate SOC or GHG outcomes, build or approve an MRV protocol, infer additionality/leakage/permanence, determine project or methodology eligibility, persist project packets, verify or certify projects, create digital signatures, or issue carbon credits. Those capabilities require later governed scientific, decision, and operational layers.

## Next planned release

**v0.5.0 — AFOLU Research Librarian Intelligence** can consume the v0.1–v0.4 concepts, measures, evidence/methodology graph, and project-object/provenance context to provide domain-aware research guidance while preserving the same non-inference boundaries.
