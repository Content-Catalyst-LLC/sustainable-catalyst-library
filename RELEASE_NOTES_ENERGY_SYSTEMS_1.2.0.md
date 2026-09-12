# Energy Systems Intelligence v1.2.0 — Target-Side Runtime Consumers

Energy Systems v1.2.0 activates real target-side contract-intake consumers for the v1.1 handoff gateway.

## Certified consumer baselines

- Research Librarian 8.1.0
- Lab 0.101.0
- Workbench 6.1.0
- Site Intelligence 4.40.0
- Decision Studio 2.3.0

Every consumer exposes `GET /v1/energy-runtime/consumer` and `POST /v1/energy-runtime/consume`. A consumer validates the packet schema, target identity, consumer contract, and exact target-specific payload sections; preserves provenance; and emits a deterministic receipt fingerprint.

Certification is deliberately narrow: contract intake and receipt are active. Automatic target execution, persistence, credential forwarding, scientific validation, hidden weighting, ranking, winner selection, investment recommendation, and policy recommendation remain disabled.
