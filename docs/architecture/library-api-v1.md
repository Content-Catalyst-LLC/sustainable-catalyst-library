# Independent Library API v1

Knowledge Library v5.59.0 publishes the first stable service contract that is explicitly independent of WordPress.

## Stable boundary

Base path: `/api/library/v1`

The API v1 contract includes service metadata, health/readiness, capability and route discovery, public search/record reads, descriptive subsystem readiness, and signed research-job submission.

## Compatibility

- Breaking changes require a new major API version.
- Additive fields may be introduced within v1.
- Clients must ignore unknown fields.
- Existing `/v1/*` endpoints remain compatibility/internal surfaces; they are not automatically covered by API v1 stability guarantees.
- WordPress may proxy or display API v1 but does not own or implement its authority.

## Authentication

Public read routes may be rate limited. Mutating service routes use the existing Sustainable Catalyst signed-service request contract: `Authorization`, `X-SC-Timestamp`, and `X-SC-Signature`, with the signature bound to method, request path, timestamp, and raw body.

## Error and pagination contracts

Errors use `sc-library-api-error/1.0`. Collection paging uses `sc-library-api-page/1.0`.

## Authority

API v1 is a façade over the authoritative v5.58 runtime. PostgreSQL, the Python backend, jobs, workers, artifacts, pipelines, compute broker, native runtimes, federation, and Platform Core contracts remain authoritative. WordPress remains a non-authoritative client adapter.
