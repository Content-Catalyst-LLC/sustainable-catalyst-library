# Sustainable Catalyst Library v5.12.0 — Platform Core Research Bridge

Release class: cross-product research infrastructure  
WordPress: 5.12.0  
Python backend: 2.23.0  
Platform Core target: 3.3.0  
Database: additive PostgreSQL schema migration through idempotent `schema.sql`  
New external credentials: no new credential type; Library receives the existing Platform Core server write key

## Added

- Platform Core capability discovery against the live Core research/reasoning surfaces.
- Durable Library ↔ Core object bindings.
- Idempotent Core synchronization outbox with payload hashing, retry state, result capture, and returned Core object identity.
- Reviewed operation allowlist instead of arbitrary Core proxying.
- Core binding reconciliation against canonical Core internal URIs.
- New health capabilities for the Platform Core bridge.
- WordPress Python Backend admin visibility for Core bridge readiness.
- Deployment-time Core credential handoff that never prints the Core write secret.

## Architecture boundary

Library retains raw documents, source normalization, chunks, search indexes, connector state, and retrieval. Core remains authoritative for governed research objects, evidence/provenance, claims/findings, reasoning, synthesis, visual reasoning, statistical reasoning, scholarly packages, and exchange objects.

No raw-chunk mirroring and no automatic truth/claim promotion are enabled.

## Versioning correction

The supplied repository already contains Knowledge Library v5.10.0 and is currently v5.11.0. The Platform Core Research Bridge scope originally discussed as v5.10.0 is therefore released as v5.12.0 rather than overwriting release history.

## Compatibility

- Retains v5.11.0 Private Organizational Knowledge Foundation.
- Retains v5.10.0 Institutional Research Network II.
- Retains current Energy Systems, Carbon & Nature, biomedical, public discovery, Publications, and Research Network behavior.
- Does not require a Platform Core code change; it consumes the supplied Core v3.3.0 contracts.
