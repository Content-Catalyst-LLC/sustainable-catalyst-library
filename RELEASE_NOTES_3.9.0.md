# Sustainable Catalyst Knowledge Library v3.9.0

## Public API, Export, and Federation Hardening

v3.9.0 establishes a hardened public metadata API, deterministic export system, and governed federation layer for the Knowledge Library.

## Versioned public API

Public contracts use:

```text
/wp-json/sc-library/v1
```

Core schemas:

```text
sc-library-public-api/1.0
sc-library-api-capabilities/1.0
sc-library-export-manifest/1.0
sc-library-federation-node/1.0
sc-library-federation-peer/1.0
sc-library-signed-webhook/1.0
sc-library-federation-import/1.0
sc-library-api-audit/1.0
```

The public API supports opaque cursor pagination, bounded page sizes, ETags, conditional requests, stable version headers, content-type hardening, redacted output, and no-store private responses.

## Scoped access tokens

Tokens are generated from 32 random bytes and displayed only once.

Only SHA-256 hashes and short prefixes are retained.

Scopes:

```text
catalog:read
documents:read
projects:read
exports:create
exports:read
federation:read
federation:import
webhooks:manage
admin:read
```

Tokens can expire, be revoked, and receive per-minute rate limits.

## Public catalog

The catalog can expose public metadata for:

```text
Documents
Research Projects
Research Sources
Knowledge Pathways
Institutional Collections
Research Publications
```

Public records exclude raw private content, internal author IDs, token data, reviewer deliberations, private paths, and administrative metadata.

## Export jobs

Supported formats:

```text
JSON
JSON-LD
NDJSON
CSV
ZIP research bundle
```

Supported scopes:

```text
Documents
Projects
Sources
Pathways
Collections
Publications
Complete catalog
```

Jobs run in bounded batches and preserve cursor, total, processed count, filters, status, errors, manifest, file location, completion time, and expiration.

## Deterministic manifests

Every completed export records:

- export UUID;
- format and scope;
- API and plugin versions;
- federation node metadata;
- record count;
- per-record SHA-256 hashes;
- combined records SHA-256;
- manifest SHA-256;
- generation time.

Records are sorted before hashing.

## Private export storage

Export files are written beneath:

```text
wp-content/uploads/sc-library-private-exports
```

The directory receives an index file and Apache access-denial file. Downloads are served through an authenticated REST route unless the export was deliberately marked public.

## Federation governance

Federation peers record:

- stable UUID;
- HTTPS base URL;
- remote node ID;
- status;
- trust level;
- allowed scopes;
- public key or verification note;
- shared-secret presence;
- last check and success;
- capability document;
- error state.

Trust levels:

```text
Untrusted
Discovery only
Metadata exchange
Verified institutional peer
```

Peer discovery uses safe remote requests, HTTPS-only URLs, bounded redirects, timeouts, compatibility validation, and private-network rejection.

## Signed webhooks

Webhook deliveries use:

```text
HMAC-SHA256(timestamp + "." + body)
```

Headers:

```text
X-SC-Webhook-ID
X-SC-Webhook-Timestamp
X-SC-Webhook-Signature
```

Deliveries use HTTPS, no redirects, bounded exponential retry, failure archives, and redacted audit records.

## Federation import quarantine

Incoming federation data is never imported directly into public Knowledge Library records.

It is first:

1. size checked;
2. schema checked;
3. record checked;
4. peer trust checked;
5. SHA-256 hashed;
6. stored in quarantine;
7. reviewed by an administrator.

Available decisions:

```text
Approve metadata
Reject
Archive
```

Approval still does not create public content automatically.

## Admin workspace

```text
SC Library → API, Export & Federation
```

The workspace includes token issuance, export creation, export status, peer status, webhook counts, import quarantine, migration controls, capability discovery, and redacted audit history.

## Public shortcodes

```text
[sc_library_api_capabilities]
[sc_library_public_catalog type="documents" limit="20"]
[sc_library_federation_status]
[sc_library_export_register]
```

## REST API

```text
GET      /wp-json/sc-library/v1/capabilities
GET      /wp-json/sc-library/v1/catalog
GET      /wp-json/sc-library/v1/catalog/{type}
GET      /wp-json/sc-library/v1/records/{type}/{id}

POST     /wp-json/sc-library/v1/exports
GET/POST /wp-json/sc-library/v1/exports/{id}
GET      /wp-json/sc-library/v1/exports/{id}/download

GET      /wp-json/sc-library/v1/federation/node
GET      /wp-json/sc-library/v1/federation/peers
POST     /wp-json/sc-library/v1/federation/peers/{id}/check
POST     /wp-json/sc-library/v1/federation/imports
GET      /wp-json/sc-library/v1/federation/imports/{id}

GET      /wp-json/sc-library/v1/api-export-federation/dashboard
GET/POST /wp-json/sc-library/v1/api-export-federation/migration
```

## WP-CLI

```text
wp sc-library api token-create
wp sc-library api token-revoke TOKEN_ID
wp sc-library export create
wp sc-library export run JOB_ID
wp sc-library federation peer-check PEER_ID
wp sc-library federation import-decide IMPORT_ID
wp sc-library api migrate --limit=20
wp sc-library api dashboard
```

## Compatibility

v3.9.0 retains v3.8.0 Collaborative Review and Research Publishing, v3.7.0 Document Intelligence, v3.6.0 Collections and Archives, v3.5.0 Quality and Governance, v3.4.0 handoffs, v3.3.0 pathways, v3.2.0 semantics, v3.1.0 source integrity, and all earlier Knowledge Library systems.
