# Energy Systems Intelligence v1.1.0

## Cross-Product Runtime Activation Gateway

Energy Systems Intelligence v1.1.0 advances the v1.0.0 Integrated Sustainable Energy Systems Platform from static cross-product contracts to an executable **Library-side, stateless runtime handoff gateway**.

### Release identity

- Sustainable Catalyst Library: **5.11.0**
- Carbon & Nature Intelligence: **0.5.0**
- Energy Systems Intelligence: **1.1.0**
- Shared Library Python backend: **2.17.0**
- Database migration: **none**
- New required secret: **none**

## What v1.1.0 activates

Five target-specific packet builders are available for:

1. Research Librarian
2. Lab
3. Workbench
4. Site Intelligence
5. Decision Studio

Each adapter receives the governed v1.0.0 integrated-energy-study structure and emits only the sections appropriate to that target. The packet includes deterministic identity, source/target contract references, a target-specific payload, readiness metadata, and the v1.1.0 runtime guardrails.

The packet builders are deterministic: the same normalized study payload and target produce the same handoff ID. They are also stateless: the Library does not persist a handoff packet or perform an outbound write when the packet is built.

## Activation boundary

v1.1.0 activates the **Library gateway**, not the independent target product runtimes. The release does not claim that Research Librarian, Lab, Workbench, Site Intelligence, or Decision Studio currently consumes, stores, executes, or validates the emitted packet. Target-side consumer code remains a separate deployment responsibility.

Accordingly:

- outbound push delivery is disabled;
- cross-product persistence is disabled;
- credentials are not forwarded;
- target runtime consumption is not certified;
- model execution is not automatically triggered;
- decision ranking, winner selection, and recommendation remain disabled.

## Runtime handoff surfaces

The shared backend exposes read-only/stateless endpoints:

- `GET /v1/energy-systems/runtime-framework`
- `GET /v1/energy-systems/runtime-targets`
- `GET /v1/energy-systems/runtime-targets/{target_key}`
- `GET /v1/energy-systems/runtime-handoff-template/{target_key}`
- `GET /v1/energy-systems/runtime-handoff/{target_key}?study={json}`
- `GET /v1/energy-systems/runtime-readiness/{target_key}?study={json}`

The WordPress facade mirrors the same contracts under `/wp-json/sc-library/v1/energy-systems/...` and remains GET-only.

## Target payload boundaries

### Research Librarian
Carries identity, research context, sustainability indicators, dated global context, provenance, and review notes.

### Lab
Carries identity, technologies/resources, energy-balance context, economics, bioenergy/carbon, uncertainty, provenance, and review notes.

### Workbench
Carries identity, numeric-registry references, energy-balance context, economics, bioenergy/carbon, provenance, and review notes.

### Site Intelligence
Carries identity, technologies/resource observations, dated global context, provenance, and review notes.

### Decision Studio
Carries identity, decision references, economics, sustainability indicators, dated global context, uncertainty, provenance, and review notes.

## Readiness inspection

Runtime readiness reports whether the Library-side packet has meaningful target-relevant content. It surfaces missing study identity, question, provenance, and target-specific context. Readiness is **not** a scientific-quality score, target-runtime health check, model validation, decision-quality score, or proof of target-side availability.

## Preserved v1.0.0 certification baseline

The v1.0.0 structural certification remains available and continues to pass its original **20/20** repository/domain-contract checks. v1.1.0 does not rewrite that certification as a claim about external runtime execution.
