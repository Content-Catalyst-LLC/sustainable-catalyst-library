# Sustainable Catalyst Knowledge Library v2.6.1

## Connector and Holdings Reliability

This patch hardens the v2.6.0 scholarly and library connector system without changing its Source schema, public Source routes, provider endpoints, shortcodes, or REST namespace.

## Provider health and recovery

Each network connector now maintains an operational state:

```text
Unknown
Healthy
Degraded
Open
Half-open
```

The health record stores:

- successful and failed request counts
- consecutive failures
- last success and failure timestamps
- last HTTP status
- last error
- rolling average latency
- cooldown expiration
- captured rate-limit and concurrency headers
- a bounded recent event history

After repeated failures, the circuit breaker pauses the affected provider. Other providers remain available.

## Retry and backoff

The transport now supports bounded retries for:

```text
408
425
429
500
502
503
504
```

Retries use short exponential delays with jitter and respect brief `Retry-After` values. Longer provider cooldowns open the circuit instead of blocking a WordPress request for an extended period.

## Conditional requests

Provider responses can retain:

```text
ETag
Last-Modified
JSON response body
```

Later requests can send:

```text
If-None-Match
If-Modified-Since
```

A valid `304 Not Modified` response reuses the retained body. If a validator remains but the cached body is missing, the connector removes the stale validators and retries unconditionally.

## Stale-cache recovery

Successful normalized searches are retained beyond the normal fresh-cache window.

When a provider is unavailable, rate limited, or in circuit-breaker cooldown, the connector can return the retained result set with:

```text
cache_state: stale
stale: true
recovery_notice
live_error
```

Stale results are clearly identified and receive new user-bound import tokens before display.

## Import idempotency

AJAX and REST imports now support idempotency keys.

The browser creates one key per import attempt and reuses it during a retry. The REST endpoint accepts:

```text
Idempotency-Key
```

or:

```json
{
  "idempotency_key": "client-generated-key"
}
```

A repeated request returns the recorded import result before attempting to consume the one-time discovery token again.

The provider/import fingerprint is also checked before creating a new Source. A matching imported Source is reused when the current user may edit it.

## Metadata conflicts

The default `fill_empty` mode still protects populated researcher-edited fields.

When a provider supplies a different value, the system records a conflict instead of silently discarding the comparison.

Conflicts cover structured fields plus:

```text
Title
Abstract
```

Editors can choose:

```text
Use Provider Value
Keep Current Value
Dismiss
```

Using the provider value returns the Source to an unverified state and rebuilds citation indexes, duplicate indexes, and reliability results.

## Holdings freshness

Stored access locations now include:

```text
checked_at
fresh_for_seconds
stale_after
stale
verification
last_http_status
failure_count
```

Freshness policies vary by location type. Library catalogs, OpenURL resolvers, proxy actions, and interlibrary-loan actions receive shorter recheck windows than canonical records and general discovery handoffs.

The Source editor shows:

- total locations
- fresh locations
- stale locations
- open-access locations
- last checked time
- next recheck time
- manual recheck control

## Library profile validation

Library profiles are validated for:

- HTTPS
- valid external hosts
- rejection of localhost and `.local`
- rejection of private and reserved IP addresses
- supported catalog-template tokens
- useful access actions

Only published, enabled, and structurally valid profiles can appear on public Source pages.

The plugin still stores no library passwords and performs no library authentication.

## Scheduled maintenance

An hourly, locked maintenance job:

- moves expired circuits to half-open
- rechecks a bounded number of due holdings
- incrementally migrates v2.6.0 location records
- prevents overlapping maintenance requests

The migration processes 40 Source records at a time.

## Admin diagnostics

The Provider screen now includes **Connector Health and Recovery** with:

- health state
- last success
- last failure
- consecutive failures
- latency
- cooldown
- last error
- provider-state reset
- retained-cache clearing

## New REST endpoints

```text
GET  /wp-json/sc-library/v1/connectors/health
POST /wp-json/sc-library/v1/connectors/{provider}/reset

GET  /wp-json/sc-library/v1/sources/{id}/holdings
POST /wp-json/sc-library/v1/sources/{id}/holdings/recheck

GET  /wp-json/sc-library/v1/sources/{id}/connector-conflicts
POST /wp-json/sc-library/v1/sources/{id}/connector-conflicts/{conflict}

GET  /wp-json/sc-library/v1/library-profiles/{id}/validation
```

These endpoints require appropriate WordPress permissions.

## Compatibility

The patch preserves:

- v2.6.0 scholarly and library connectors
- v2.5.1 citation formatting and source reliability
- v2.5.0 Source and Research Project records
- v2.4.x OCR systems
- v2.3.x public document repository routes
- v2.2.x PDF conversion and bulk import
