# Knowledge Library v5.44.0 — Global Source Federation Registry & Connector Contracts

## Purpose

v5.44.0 establishes one canonical Library-side registry for global research-source identity and connector capabilities. It does not replace the existing connector implementations. Instead, it gives the existing v2.6 scholarly/library connectors, Python institutional/biomedical/regulatory connectors, browser handoffs, and v4.8 federation transport a shared source/institution/collection/connector identity layer.

## Contracts

- `sc-library-global-source-federation-registry/1.0`
- `sc-library-global-source-institution/1.0`
- `sc-library-global-source/1.0`
- `sc-library-global-source-collection/1.0`
- `sc-library-global-source-connector-contract/1.0`
- `sc-library-global-source-connector-validation/1.0`
- `sc-library-global-source-federation-readiness/1.0`

## Registry contents

The initial registry contains 26 source descriptors, 26 primary connector contracts, 17 institution/steward descriptors, and six research-source collections:

1. Scholarly Metadata
2. Biomedical & Clinical
3. Institutional Repositories
4. Archives & Libraries
5. Books & Access
6. Regulatory & Public Data

Each source has one primary connector contract and may expose compatibility aliases for older WordPress or backend adapter keys.

## Connector contract

A connector contract declares:

- stable connector and source IDs;
- execution authority;
- transport;
- capabilities;
- authentication mode (never credentials);
- pagination mode;
- rate-limit policy;
- required provenance fields;
- source-language preservation behavior;
- translation behavior;
- raw-source preservation;
- import/promotion guardrails.

The registry does not store secrets and v5.44.0 does not rotate or add connector credentials.

## Ownership and compatibility

- Existing connector implementations continue to execute where they already live.
- Backend v2.55.0 is authoritative for the normalized federation registry and connector contracts.
- WordPress exposes bounded public REST proxies and `[sc_global_source_registry]` without duplicating connector execution or credentials.
- The v4.8 Global Research Federation remains the governed references-only federation transport.
- v5.44.0 does not create a parallel connector execution stack.

## Original-language forward compatibility

v5.44.0 does not perform language normalization or translation. Connector contracts require `language_policy=preserve-as-received` and `translation_behavior=none`. This creates a clean handoff into the v5.45 original-language ingestion release without making translation a canonical source representation.

## Guardrails

The following are explicit contract rules:

- registry membership does not imply endorsement;
- registry membership does not imply partnership;
- connector health does not imply source quality;
- connector health does not imply evidence truth;
- no source-quality score is assigned by the federation registry;
- no user-trust score is assigned by the federation registry;
- no automatic import;
- no automatic evidence promotion;
- no automatic truth promotion;
- no automatic Platform Core promotion;
- original source language is preserved as received;
- no automatic translation.

## API

Backend v2.55.0 exposes:

- `GET /v1/global-source-federation/readiness`
- `GET /v1/global-source-federation/registry`
- `GET /v1/global-source-federation/collections`
- `GET /v1/global-source-federation/sources/{source_id}`
- `GET /v1/global-source-federation/connectors/{connector_id}`
- `POST /v1/global-source-federation/connectors/validate`

The registry endpoint supports bounded filtering by source family, connector capability, collection, execution authority, and text query.

## Release lineage

- WordPress plugin: **5.44.0**
- Python backend: **2.55.0**
- Go ingestion runtime: **0.1.0**
- Rust graph runtime: **0.2.0**
