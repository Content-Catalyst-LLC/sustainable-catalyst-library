# Sustainable Catalyst Library v1.18.0

Library v1.18.0 adds **Public API, Webhooks, and Developer Documentation** to the complete v1.17.0 Library platform.

## Developer infrastructure

The release provides a dedicated, versioned namespace:

```text
/wp-json/sustainable-catalyst-library/v1
```

Public routes expose only canonical public Library data. Protected operations require administrator-issued scoped keys whose plaintext is shown once and whose stored representation is a keyed hash.

## Public endpoints

```text
GET /status
GET /records
GET /records/{id}
GET /relationships
GET /graph
GET /roadmap
GET /schemas
GET /schemas/{name}
GET /openapi.json
```

## Protected endpoints

```text
GET  /protected/export-manifest   scope: exports:read
POST /protected/reindex           scope: index:write
POST /protected/webhooks/test     scope: webhooks:write
```

Keys can be supplied through `X-SC-Library-Key` or `Authorization: Bearer`.

## Signed webhooks

Webhook destinations must use safe HTTPS URLs. Delivery headers include:

```text
X-SC-Event
X-SC-Delivery
X-SC-Timestamp
X-SC-Signature: sha256=HMAC(secret, timestamp.payload)
```

Retries are bounded and recorded. Webhook signing secrets are encrypted at rest and shown only once when created.

## Developer portal

Create a WordPress page and add:

```text
[sc_library_developer_portal]
```

The portal links to the OpenAPI 3.1 document, JSON Schema registry, endpoint catalog, event catalog, authentication guidance, and examples.

## Portable data

Portable export schema:

```text
sc-library-portable-export/1.8
```

New normalized entities:

- `api_keys`
- `webhooks`
- `webhook_deliveries`

Exports omit key hashes, encrypted webhook secrets, full delivery payloads, and delivery signatures.

## Retained systems

- Research Librarian Workspace Orchestration
- Knowledge Graph and relationship intelligence
- Editorial collaboration and review
- Multimedia Studio and evidence reels
- Large-Library Index Tools
- Persistent account workspaces and optional Render synchronization
- Server-side book and PDF production
- Content Planner, release coordination, and public registry
- Research Notebook, matrices, boards, annotations, and books
- PostgreSQL, CSV, JSONL, and JSON portability

See `DEVELOPER_API_SETUP.md` and `RELEASE_NOTES_1.18.0.md`.
