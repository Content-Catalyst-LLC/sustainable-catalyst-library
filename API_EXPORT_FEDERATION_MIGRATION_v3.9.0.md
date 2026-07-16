# API, Export, and Federation Migration — v3.9.0

## Scope

The migration normalizes API tokens, export jobs, peers, webhooks, and import records.

## Defaults

Tokens:

```text
Default rate limit
Not revoked
```

Exports:

```text
Stable UUID
Queued status when absent
```

Peers:

```text
Stable UUID
Pending status
Untrusted trust level
```

Webhooks:

```text
Stable UUID
Inactive status
```

Imports:

```text
Stable UUID
```

## Reliability

```text
20 records per batch
Stable post-ID cursor
180-second lock
Bounded failure history
Hourly cron
AJAX
REST
WP-CLI
```

## Non-destructive behavior

The migration does not issue tokens, publish exports, activate peers, activate webhooks, approve imports, rewrite documents, or expose private data.
