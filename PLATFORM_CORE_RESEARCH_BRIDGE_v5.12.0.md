# Sustainable Catalyst Library v5.12.0 — Platform Core Research Bridge

## Release intent

This release makes Platform Core a first-class governed dependency of the Knowledge Library without collapsing the two products into one service.

The responsibility boundary is explicit:

- **Knowledge Library / Python backend** owns acquisition, parsing, normalized records, chunks, indexes, source metadata, retrieval, connectors, and document intelligence.
- **Platform Core** owns governed research/evidence objects, provenance and lineage, findings/claims/arguments, synthesis, reproducibility, visual reasoning, statistical-reasoning objects, and cross-product exchange.

Raw Library chunks are **not** mirrored into Core. Promotion is explicit, typed, idempotent, and auditable.

## Versioning note

The requested roadmap label was v5.10.0, but the supplied repository already contains an earlier **Knowledge Library v5.10.0 — Institutional Research Network II** release and is currently **v5.11.0**. To preserve monotonic release history and avoid a downgrade/re-tag collision, this implementation is correctly versioned **v5.12.0**. The Python backend advances from the supplied repository's v2.22.0 identity to **v2.23.0**.

## New backend contracts

Public/read-only:

- `GET /v1/platform-core/readiness`
- `GET /v1/platform-core/capabilities`

Server-authenticated:

- `POST /v1/platform-core/bindings`
- `GET /v1/platform-core/bindings/{library_record_id}`
- `POST /v1/platform-core/outbox`
- `GET /v1/platform-core/outbox`
- `POST /v1/platform-core/outbox/run-once`
- `POST /v1/platform-core/reconcile/{library_record_id}`

Authenticated routes reuse the Library backend's existing bearer + timestamp + HMAC request-signature boundary. Platform Core writes use Core's existing `X-SC-API-Key` server credential. Neither secret is returned to WordPress or the browser.

## Core capability discovery

The bridge probes the Core v3.3.0 surfaces that matter to the Library roadmap:

1. Research Object & Model Foundation
2. Research Lineage & Provenance Graph
3. Finding, Claim & Evidence Intelligence
4. Cross-Product Evidence Exchange
5. Scholarly Interoperability / Research Packaging
6. Unified Research Runtime Contract
7. Unified Visual Reasoning Engine
8. Statistical Reasoning Object Model

A missing/disabled capability is reported as unavailable rather than silently treated as present.

## Durable binding registry

`library_core_bindings` records stable relationships between Library objects and canonical Core objects.

Key fields include:

- Library record/object identity
- Core object identity/type
- Core canonical URI
- Library content hash at sync time
- sync state and timestamps
- bounded metadata

Bindings are idempotent on `(library_record_id, core_object_id)`.

## Idempotent Core outbox

`library_core_sync_outbox` provides recoverable, at-least-once delivery semantics for governed promotion operations. The outbox records:

- allowlisted operation
- payload + SHA-256 payload fingerprint
- deterministic idempotency key
- attempt count / retry state
- HTTP result
- Core object ID returned by successful creation
- created/updated/processed timestamps

Arbitrary Core URLs are not accepted. v5.12.0 allowlists only reviewed operations:

- `research-object.create`
- `exchange-package.create`
- `scholarly-package.create`
- `runtime-contract.create`

Later Library releases can add higher-level mappers and additional reviewed Core operations without opening a generic proxy.

## Promotion policy

The bridge deliberately does **not**:

- send every raw chunk to Core;
- turn extracted text into a verified finding;
- turn an extracted sentence into a trusted claim;
- automatically assign truth, confidence, or authority;
- overwrite Core objects as an incidental side effect of Library search;
- expose Core or Library write credentials to JavaScript.

A future extraction pipeline may generate *candidates*. Promotion to governed Core meaning remains an explicit workflow.

## WordPress integration

The existing Python Backend admin screen now surfaces Platform Core bridge readiness through the Library backend. WordPress remains the public/editorial interface and never talks to Core with the Core write key.

## Environment

New Library backend environment settings:

```bash
SC_LIBRARY_PLATFORM_CORE_URL=https://core.sustainablecatalyst.com
SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY=<same server-side write credential used by Core>
SC_LIBRARY_PLATFORM_CORE_TIMEOUT_SECONDS=8
SC_LIBRARY_PLATFORM_CORE_MAX_ATTEMPTS=5
```

The deployment script can import the Core write key from the running `sc-core` container when the Library backend variable is absent, without printing the secret.

## Roadmap consequence

Beginning with this release, each major Knowledge Library build should name the specific Platform Core contract it consumes. Library should implement source-near intelligence; durable research meaning should be promoted into Core rather than duplicated.
