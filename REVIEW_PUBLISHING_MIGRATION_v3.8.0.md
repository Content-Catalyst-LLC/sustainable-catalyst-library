# Review and Publishing Migration — v3.8.0

## Scope

The migration normalizes existing v3.8.0 Research Review and Publication Package records.

## Review defaults

```text
UUIDv4
Draft status
Editorial review type
One required approval
Document snapshots
Initial readiness evaluation
```

Existing values are preserved.

## Package defaults

```text
UUIDv4
Draft status
Initial readiness evaluation
Publication manifest
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

The migration does not:

- edit Knowledge Library document content;
- change document publication status;
- create reviewer decisions;
- disclose reviewer identity;
- publish packages;
- send invitations;
- register external identifiers;
- delete review history.
