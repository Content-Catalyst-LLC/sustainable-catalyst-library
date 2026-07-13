# Library v1.18.0 Public API, Webhooks, and Developer Documentation

## Install

1. Upload `sustainable-catalyst-library-v1.18.0.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm **Sustainable Catalyst Library 1.18.0**.
4. Open **SC Library → Developer API**.

No Library index rebuild is required solely for this upgrade. API record results reflect the current Library index, so confirm Index Tools is healthy before external integration.

## Developer portal

Create a public page named **Developers** and add:

```text
[sc_library_developer_portal]
```

Publish it and save the page URL under Developer API settings.

## API base

```text
https://YOUR-SITE/wp-json/sustainable-catalyst-library/v1
```

OpenAPI:

```text
/wp-json/sustainable-catalyst-library/v1/openapi.json
```

JSON Schema registry:

```text
/wp-json/sustainable-catalyst-library/v1/schemas
```

Public collections are paginated with `page` and `per_page` parameters and return `X-WP-Total` headers. Public multimedia evidence reels are available at:

```text
/wp-json/sustainable-catalyst-library/v1/media/reels
```

Roadmap records are available at `/roadmap` and can be filtered by Library collection slug.

## API keys

Create one key per integration and select only the scopes it needs. The plaintext key is shown once. Store it in the receiving service's secret manager, not JavaScript or public WordPress content.

Headers:

```text
X-SC-Library-Key: scl_live_...
```

or:

```text
Authorization: Bearer scl_live_...
```

## Webhook verification

For each delivery, calculate:

```text
expected = HMAC_SHA256(secret, X-SC-Timestamp + "." + raw_request_body)
```

Compare it in constant time with the hexadecimal value following `sha256=` in `X-SC-Signature`. Reject stale timestamps according to your replay window, normally five minutes.

## Privacy boundary

Public API and webhooks do not expose private workspaces, editorial comments, participant email addresses, invitation tokens, internal planning notes, API-key hashes, webhook secrets, full delivery payload archives, or provider credentials.

## Portable export

Schema `sc-library-portable-export/1.8` adds developer API metadata. Secrets and hashes remain excluded.
