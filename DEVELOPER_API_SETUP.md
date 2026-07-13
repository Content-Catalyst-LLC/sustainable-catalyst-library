# Library v1.19.0 Public API, Webhooks, and Preservation Routes

## Install

1. Upload `sustainable-catalyst-library-v1.19.0.zip` in WordPress.
2. Choose **Replace current with uploaded**.
3. Confirm **Sustainable Catalyst Library 1.19.0**.
4. Open **SC Library → Developer API** and **SC Library → Preservation & Archive**.

No Library index rebuild is required solely for this upgrade. API record results reflect the current Library index, while archive routes reflect completed preservation snapshots.

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

## Institutional Archive routes

Public archive routes expose frozen snapshots only while the canonical WordPress record remains published and not password protected:

```text
/wp-json/sustainable-catalyst-library/v1/archive
/wp-json/sustainable-catalyst-library/v1/archive/{uuid}
/wp-json/sustainable-catalyst-library/v1/archive/{uuid}/manifest
```

The internal Library namespace also provides:

```text
/wp-json/sustainable-catalyst/v1/library/preservation/status
/wp-json/sustainable-catalyst/v1/library/archive
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}
/wp-json/sustainable-catalyst/v1/library/archive/{uuid}/manifest
```

Administrative preservation diagnostics require an authenticated administrator.

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

## Preservation webhook events

The webhook catalog includes:

```text
preservation.snapshot.created
integrity.audit.completed
```

Each delivery uses the same timestamped HMAC signature as other Library events.

## Webhook verification

For each delivery, calculate:

```text
expected = HMAC_SHA256(secret, X-SC-Timestamp + "." + raw_request_body)
```

Compare it in constant time with the hexadecimal value following `sha256=` in `X-SC-Signature`. Reject stale timestamps according to your replay window, normally five minutes.

## Privacy boundary

Public API and webhooks do not expose private workspaces, editorial comments, participant email addresses, invitation tokens, internal planning notes, API-key hashes, webhook secrets, full delivery payload archives, provider credentials, or snapshots whose canonical records are no longer public.

## Portable export

Schema `sc-library-portable-export/2.0` includes preservation snapshots, integrity outcomes, authority history, and nonsecret developer metadata. Credentials, hashes, and signing secrets remain excluded.

## Foundation Document routes

Public API clients can list Foundation Documents, retrieve a record, inspect extracted page text, and request citation formats. Public routes expose only published records.
