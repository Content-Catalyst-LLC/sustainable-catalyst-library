# Changelog

## v5.57.0 — Global Knowledge Federation

- Certifies 13 federation, language, provenance, trust, artifact, pipeline, and compute layers as one interoperable Knowledge Library milestone.
- Preserves original-language canonicality, transformation lineage, source-quality/trust separation, and Platform Core governance boundaries.
- Adds deterministic federation certifications and append-only certification events.
- Adds active `federation.certify.global` Python research worker capability.

## v5.56.0 — Source Transparency, Quality Signals & User Trust Policies

- Adds provenance-backed descriptive source-quality signals and source-transparency profiles.
- Keeps user trust policies owner-scoped and separate from source signals.
- Adds stateless trust-policy evaluation without rewriting source history or quality signals.
- Prohibits aggregate credibility/trust scores, automatic source endorsement, evidence weighting, and Platform Core promotion.
- Adds `source.transparency.assess` to the active Python research worker.

## v5.55.0 — Cross-Civilizational Evidence & Scientific Data Linking

- Added provenance-bound links across textual/historical evidence and scientific datasets.
- Preserves language, chronology, geography, methods, units, uncertainty, provenance, and interpretation limits.
- Added active `knowledge.link.cross-civilizational` Python research worker capability.

## v5.54.0 — Translation & Transliteration Alignment Matrix

- Adds source/target text-representation alignment matrices bound to exact SHA-256 text identity.
- Supports 1:1, 1:N, N:1, N:M, omitted, added, partial, and uncertain alignment relations.
- Preserves BCP 47 language, ISO 15924 script, transformation identity, alignment method/engine, confidence signal, review state, and provenance.
- Adds signed persistence and a `language.align` Python worker capability through the durable compute fabric.
- Translation and transliteration remain derived representations; alignment never generates or canonically replaces source-language text.

## v5.53.0 — Distributed Research Compute Broker & Runtime Observability

- Adds a capability catalog spanning active and standby specialized runtime profiles.
- Adds deterministic operational placement based on compatibility, worker freshness, concurrency, queue pressure, and configured limits.
- Adds global queue/running limits and per-capability queue backpressure without creating a second scheduler.
- Adds worker-class affinity for brokered durable jobs while preserving backward compatibility for ordinary jobs.
- Adds descriptive runtime health, recent completion/failure, p50 latency, available-slot, and queue observability.
- Adds PostgreSQL placement-event lineage and optional persisted runtime observations.
- Keeps runtime metrics separate from research quality, evidence validity, truth, and Platform Core governance.

## v5.52.0 — Checkpointed Ingestion & Research Pipeline Engine

- Adds PostgreSQL-authoritative pipeline definitions, runs, stage runs, and pipeline event lineage.
- Validates acyclic stage DAGs and compiles ready stages into the existing durable research-job fabric.
- Adds deterministic stage-job idempotency, stage checkpoints, dependency checkpoint reuse, and resume-after-failure.
- Preserves v5.51 content-addressed artifact identities as pipeline stage outputs.
- Keeps completed checkpoints immutable by default and does not infer evidence truth from pipeline completion.

## v5.51.0 — Research Artifact & Object Storage Fabric
- Immutable SHA-256 content-addressed research artifact identities and storage keys.
- PostgreSQL-authoritative artifact metadata, provenance, lifecycle, derivation links, and integrity events.
- Shared filesystem object-store volume as the safe production baseline.
- S3-compatible adapter contract for MinIO, R2, AWS S3, and compatible providers without changing artifact identity.
- Signed persistence, lifecycle, and integrity-verification endpoints; raw artifact access remains admin/backend-only.
- Python worker integration for artifact persistence and verification.

## v5.50.0 — Specialized Worker Runtime & Failure Isolation
- Durable worker profiles/registrations with heartbeats and concurrency limits.
- Active Python research, Go ingestion-handoff, and Rust graph worker pools.
- Capability-aware leasing and worker quarantine.
- Dead-letter records for exhausted/permanent failures.
- PostgreSQL remains authoritative; Redis remains dispatch coordination only.

## v5.49.0 — Durable Research Job Queue & Execution State
- PostgreSQL-authoritative jobs, attempts, retries, leases, progress, cancellation, and events.
- Redis Streams for rebuildable dispatch/wake-up coordination.

For earlier releases, inspect the corresponding Git tag.
