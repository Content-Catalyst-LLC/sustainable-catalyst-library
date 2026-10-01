# Library SDK, Client & Integration Framework — v5.68.0

The Knowledge Library now provides first-party Python and JavaScript/TypeScript clients for API v1. Clients call the Library service directly; WordPress is not a runtime dependency.

## Contracts

- Python package: `clients/python/sustainable_catalyst_library`
- JavaScript/TypeScript package: `clients/javascript`
- Backend discovery: `GET /api/library/v1/client-framework`
- Readiness: `GET /api/library/v1/client-framework/readiness`
- API compatibility remains v1.0; unknown additive fields must be ignored by clients.

## Signed writes

Both clients implement the API v1 HMAC request contract: method, request path, Unix timestamp, and SHA-256 of the exact request body. Credentials remain caller-supplied and are never persisted by the SDK.

## Cross-product adapters

Adapters are provided for Research Librarian AI, Workspace, Research Lab, Workbench, Decision Studio, and Site Intelligence. They consume the existing backend-governed product contracts and do not bypass scope, ownership, provenance, or Platform Core boundaries.

## Retry boundary

Retries are bounded and transport-only for rate limiting and transient gateway/service failures. Retry success does not imply research quality, evidence validity, or truth.
