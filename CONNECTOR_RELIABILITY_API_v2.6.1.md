# Connector Reliability API — v2.6.1

Base namespace:

```text
/wp-json/sc-library/v1
```

## Provider health

```http
GET /connectors/health
```

Requires `edit_posts`.

Response includes provider health records, cache-record count, schema, version, and check time.

## Reset provider

```http
POST /connectors/{provider}/reset
```

Requires `manage_options`.

Resets the local circuit, counters, cooldown, and error state.

## Holdings

```http
GET /sources/{id}/holdings
```

Requires permission to edit the Source.

Returns the current freshness summary and normalized access locations.

## Recheck holdings

```http
POST /sources/{id}/holdings/recheck
```

Requires permission to edit the Source.

Runs a live locator refresh and returns the resulting summary.

## Conflicts

```http
GET /sources/{id}/connector-conflicts
```

Returns unresolved conflicts.

Resolve:

```http
POST /sources/{id}/connector-conflicts/{conflict}
```

Body:

```json
{
  "resolution": "use_provider"
}
```

Supported resolutions:

```text
use_provider
keep_local
dismiss
```

## Library profile validation

```http
GET /library-profiles/{id}/validation
```

Requires permission to edit the profile.

## Discovery import idempotency

Existing endpoint:

```http
POST /discovery/import
```

Header:

```text
Idempotency-Key: unique-import-key
```

JSON alternative:

```json
{
  "token": "short-lived-discovery-token",
  "idempotency_key": "unique-import-key",
  "mode": "fill_empty"
}
```

A replay returns:

```json
{
  "idempotent_replay": true
}
```

The idempotency record is checked before the one-time discovery token is consumed.
